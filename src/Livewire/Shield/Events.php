<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Shield;

use BunnySidecar\BunnySidecar\Bunny\BunnyApiException;
use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldEvent;
use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldRuleGroup;
use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldZone;
use BunnySidecar\BunnySidecar\Bunny\ShieldDirectory;
use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Livewire computed properties, accessible as properties in the component and its view.
 *
 * @property Collection<int, ShieldRuleGroup> $ruleOptions
 * @property array{events: list<array<string, mixed>>, error: ?string, from: string, to: string}|null $results
 * @property Collection<int, ShieldEvent> $events
 * @property array{account: BunnyAccount, zone: ShieldZone}|null $searchedTarget
 */
#[Layout('bunny-sidecar::layouts.app')]
#[Title('WAF Events')]
class Events extends Component
{
    use ResolvesCurrentUser;

    public string $shieldZone = '';

    public string $ruleId = '';

    #[Locked]
    public ?string $searchedShieldZone = null;

    #[Locked]
    public ?string $searchedRuleId = null;

    public function mount(): void
    {
        abort_unless($this->currentUser()->isAdmin(), 403);

        $this->shieldZone = (string) session('bunny-sidecar.shield-events.shield_zone', '');
        $this->ruleId = (string) session('bunny-sidecar.shield-events.rule_id', '');
    }

    public function updatedShieldZone(): void
    {
        unset($this->ruleOptions);

        if (! $this->ruleOptions->contains(fn (ShieldRuleGroup $group): bool => $group->ruleId === $this->ruleId)) {
            $this->ruleId = '';
        }
    }

    /**
     * The WAF rules (OWASP ids starting with 9) that fired on the selected
     * shield zone in the window, sorted by rule id. Empty when no zone is
     * selected or bunny.net fails.
     *
     * @return Collection<int, ShieldRuleGroup>
     */
    #[Computed]
    public function ruleOptions(): Collection
    {
        $target = $this->target($this->shieldZone);

        if ($target === null) {
            return collect();
        }

        try {
            return app(ShieldDirectory::class)
                ->rulesFor($target['account'], $target['zone']->id, ...$this->window())
                ->filter(fn (ShieldRuleGroup $group): bool => $group->isWafRule())
                ->sortBy(fn (ShieldRuleGroup $group): string => $group->ruleId)
                ->values();
        } catch (BunnyApiException) {
            return collect();
        }
    }

    public function search(): void
    {
        $this->validate([
            'shieldZone' => ['required', 'string', 'regex:/^\d+:\d+$/'],
            'ruleId' => ['required', 'string', 'regex:/^\d{1,12}$/'],
        ], attributes: [
            'shieldZone' => __('shield zone'),
            'ruleId' => __('rule ID'),
        ]);

        $this->searchedShieldZone = $this->shieldZone;
        $this->searchedRuleId = $this->ruleId;

        session([
            'bunny-sidecar.shield-events.shield_zone' => $this->shieldZone,
            'bunny-sidecar.shield-events.rule_id' => $this->ruleId,
        ]);

        unset($this->results);
    }

    /**
     * The searched events, kept server-side between requests so the payload
     * stays small. Stored as plain arrays so any cache store can hold them.
     *
     * @return array{events: list<array<string, mixed>>, error: ?string, from: string, to: string}|null
     */
    #[Computed(persist: true, seconds: 900)]
    public function results(): ?array
    {
        $target = $this->searchedTarget();

        if ($target === null || $this->searchedRuleId === null) {
            return null;
        }

        [$from, $to] = $this->window();
        $events = [];
        $error = null;

        try {
            $events = array_values(app(ShieldDirectory::class)
                ->eventsForRule($target['account'], $target['zone']->id, $this->searchedRuleId, $from, $to)
                ->map(fn (ShieldEvent $event): array => $event->toArray())
                ->all());
        } catch (BunnyApiException $exception) {
            $error = $exception->userMessage();
        }

        return ['events' => $events, 'error' => $error, 'from' => $from->toIso8601String(), 'to' => $to->toIso8601String()];
    }

    /**
     * @return Collection<int, ShieldEvent>
     */
    #[Computed]
    public function events(): Collection
    {
        return collect($this->results['events'] ?? [])->map(ShieldEvent::fromArray(...));
    }

    /**
     * @return array{account: BunnyAccount, zone: ShieldZone}|null
     */
    #[Computed]
    public function searchedTarget(): ?array
    {
        return $this->target($this->searchedShieldZone);
    }

    /**
     * Resolves an "accountId:shieldZoneId" select value.
     *
     * @return array{account: BunnyAccount, zone: ShieldZone}|null
     */
    protected function target(?string $value): ?array
    {
        if ($value === null || preg_match('/^\d+:\d+$/', $value) !== 1) {
            return null;
        }

        [$accountId, $shieldZoneId] = array_map(intval(...), explode(':', $value));
        $account = BunnyAccount::query()->find($accountId);
        $zone = $account === null ? null : app(ShieldDirectory::class)->find($account, $shieldZoneId);

        return $zone === null ? null : ['account' => $account, 'zone' => $zone];
    }

    /**
     * The searched time window ending now.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function window(): array
    {
        $to = now();

        return [$to->copy()->subHours(config()->integer('bunny-sidecar.api.shield_events_hours')), $to];
    }

    public function render(ShieldDirectory $directory): View
    {
        $zonesError = null;
        $shieldZones = collect();

        try {
            $shieldZones = $directory->all();
        } catch (BunnyApiException $exception) {
            $zonesError = $exception->userMessage();
        }

        return view('bunny-sidecar::livewire.shield.events', [
            'shieldZones' => $shieldZones,
            'zonesError' => $zonesError,
            'showAccountNames' => $shieldZones->pluck('account.id')->unique()->count() > 1,
            'windowHours' => config()->integer('bunny-sidecar.api.shield_events_hours'),
        ]);
    }
}
