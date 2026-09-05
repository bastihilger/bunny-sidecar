<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny;

use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsRecord;
use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsZone;
use BunnySidecar\BunnySidecar\Bunny\Dtos\PullZone;
use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldEventPage;
use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldRuleGroup;
use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldZone;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the bunny.net DNS and Shield APIs for one bunny account.
 */
class BunnyClient
{
    public function __construct(private readonly BunnyAccount $account) {}

    public static function for(BunnyAccount $account): self
    {
        return new self($account);
    }

    /**
     * All DNS zones of the account (without records).
     *
     * @return Collection<int, DnsZone>
     */
    public function zones(): Collection
    {
        $zones = collect();
        $page = 1;

        do {
            $data = $this->request(fn (PendingRequest $http): Response => $http->get('/dnszone', [
                'page' => $page,
                'perPage' => 100,
            ]))->json();

            foreach ($data['Items'] ?? [] as $item) {
                $zones->push(DnsZone::fromApi($item));
            }

            $hasMore = (bool) ($data['HasMoreItems'] ?? false);
            $page++;
        } while ($hasMore);

        return $zones;
    }

    /**
     * A single zone including its records.
     */
    public function zone(int $zoneId): DnsZone
    {
        $data = $this->request(fn (PendingRequest $http): Response => $http->get("/dnszone/{$zoneId}"))->json();

        return DnsZone::fromApi($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRecord(int $zoneId, array $payload): DnsRecord
    {
        $data = $this->request(
            fn (PendingRequest $http): Response => $http->put("/dnszone/{$zoneId}/records", $payload),
        )->json();

        return DnsRecord::fromApi($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateRecord(int $zoneId, int $recordId, array $payload): void
    {
        $this->request(
            fn (PendingRequest $http): Response => $http->post("/dnszone/{$zoneId}/records/{$recordId}", $payload),
        );
    }

    public function deleteRecord(int $zoneId, int $recordId): void
    {
        $this->request(
            fn (PendingRequest $http): Response => $http->delete("/dnszone/{$zoneId}/records/{$recordId}"),
        );
    }

    /**
     * All CDN pull zones of the account (without certificates).
     *
     * @return Collection<int, PullZone>
     */
    public function pullZones(): Collection
    {
        $pullZones = collect();
        $page = 1;

        do {
            $data = $this->request(fn (PendingRequest $http): Response => $http->get('/pullzone', [
                'page' => $page,
                'perPage' => 100,
                'includeCertificate' => 'false',
            ]))->json();

            foreach ($data['Items'] ?? [] as $item) {
                $pullZones->push(PullZone::fromApi($item));
            }

            $hasMore = (bool) ($data['HasMoreItems'] ?? false);
            $page++;
        } while ($hasMore);

        return $pullZones;
    }

    /**
     * All Shield (WAF) zones of the account. Labels are not part of this
     * response; merge in pullZones() for readable names.
     *
     * @return Collection<int, ShieldZone>
     */
    public function shieldZones(): Collection
    {
        $shieldZones = collect();
        $page = 1;

        do {
            $data = $this->request(fn (PendingRequest $http): Response => $http->get('/shield/shield-zones', [
                'page' => $page,
                'perPage' => 100,
            ]))->json();

            foreach ($data['data'] ?? [] as $item) {
                $shieldZones->push(ShieldZone::fromApi($item));
            }

            $nextPage = $data['page']['nextPage'] ?? null;
            $page = is_numeric($nextPage) && (int) $nextPage > $page ? (int) $nextPage : null;
        } while ($page !== null);

        return $shieldZones;
    }

    /**
     * One page of the Shield event log search. Time bounds are Unix
     * milliseconds, pages are zero-based and the query is free text.
     */
    public function searchShieldEvents(int $shieldZoneId, int $fromMs, int $toMs, string $query, int $page = 0, int $pageSize = 100): ShieldEventPage
    {
        $data = $this->request(fn (PendingRequest $http): Response => $http->post("/shield/event-logs/{$shieldZoneId}/search", [
            'from' => $fromMs,
            'to' => $toMs,
            'query' => $query,
            'page' => $page,
            'pageSize' => $pageSize,
        ]))->json();

        return ShieldEventPage::fromApi(is_array($data) ? $data : []);
    }

    /**
     * Shield events in the window aggregated per WAF rule, most frequent first.
     *
     * @return Collection<int, ShieldRuleGroup>
     */
    public function shieldRuleGroups(int $shieldZoneId, int $fromMs, int $toMs): Collection
    {
        $groups = collect();
        $page = 0;

        do {
            $data = $this->request(fn (PendingRequest $http): Response => $http->post("/shield/event-logs/{$shieldZoneId}/search", [
                'from' => $fromMs,
                'to' => $toMs,
                'query' => '',
                'page' => $page,
                'pageSize' => 100,
                'groupBy' => ['ruleId'],
            ]))->json();

            foreach ($data['groups'] ?? [] as $group) {
                $groups->push(ShieldRuleGroup::fromApi($group));
            }

            $page++;
        } while ($page < (int) ($data['totalPages'] ?? 0));

        return $groups
            ->filter(fn (ShieldRuleGroup $group): bool => $group->ruleId !== '')
            ->sortByDesc(fn (ShieldRuleGroup $group): int => $group->count)
            ->values();
    }

    /**
     * @param  callable(PendingRequest): Response  $callback
     *
     * @throws BunnyApiException
     */
    private function request(callable $callback): Response
    {
        try {
            return $callback($this->http())->throw();
        } catch (ConnectionException $exception) {
            throw new BunnyApiException($this->account->name, previous: $exception);
        } catch (RequestException $exception) {
            throw new BunnyApiException($this->account->name, $exception->response->status(), $exception);
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config()->string('bunny-sidecar.api.base_url'))
            ->withHeader('AccessKey', $this->account->api_key)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }
}
