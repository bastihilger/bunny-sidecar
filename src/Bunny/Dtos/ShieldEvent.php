<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny\Dtos;

use BunnySidecar\BunnySidecar\Bunny\TriggerMatchFormatter;
use Illuminate\Support\Carbon;

/**
 * One WAF event log entry from the bunny.net Shield event log search.
 * Includes the TriggerMatch (the fragment the rule matched on) and the
 * form-encoded request body, neither of which the bunny dashboard shows.
 */
final readonly class ShieldEvent
{
    /**
     * bunny.net cuts TriggerMatch values off after this many characters.
     */
    public const int TRIGGER_MATCH_LIMIT = 1000;

    /**
     * @param  int  $timestamp  Unix milliseconds
     * @param  list<array{name: string, value: string}>  $headers
     */
    public function __construct(
        public string $logId,
        public int $timestamp,
        public ?string $ruleId,
        public ?string $action,
        public ?string $method,
        public ?string $country,
        public ?string $remoteIp,
        public ?string $url,
        public ?string $message,
        public ?string $triggerMatchKey,
        public ?string $triggerMatchValue,
        public ?string $urlEncodedBody,
        public ?string $userAgent,
        public array $headers = [],
    ) {}

    public function occurredAt(): Carbon
    {
        return Carbon::createFromTimestampMs($this->timestamp)->setTimezone(config()->string('app.timezone'));
    }

    /**
     * The matched data, pretty-printed when it is (possibly truncated) JSON.
     */
    public function formattedTriggerMatch(): ?string
    {
        return app(TriggerMatchFormatter::class)->format($this->triggerMatchValue) ?? $this->triggerMatchValue;
    }

    public function triggerMatchIsTruncated(): bool
    {
        return $this->triggerMatchValue !== null && mb_strlen($this->triggerMatchValue) >= self::TRIGGER_MATCH_LIMIT;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $data  the shape produced by toArray()
     */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    /**
     * @param  array<string, mixed>  $row  one entry of the search response's "rows"
     */
    public static function fromApi(array $row): self
    {
        $fields = $row['fields'] ?? [];
        $log = is_string($row['log'] ?? null) ? (json_decode($row['log'], true) ?: []) : ($row['log'] ?? []);
        $trigger = is_array($log['TriggerMatch'] ?? null) ? $log['TriggerMatch'] : [];

        return new self(
            logId: (string) ($row['logId'] ?? ''),
            timestamp: (int) ($row['timestamp'] ?? 0),
            ruleId: self::stringOrNull($fields['ruleId'] ?? null),
            action: self::stringOrNull($fields['action'] ?? null),
            method: self::stringOrNull($fields['method'] ?? null),
            country: self::stringOrNull($fields['country'] ?? null),
            remoteIp: self::stringOrNull($log['RemoteIp'] ?? $fields['ip'] ?? null),
            url: self::stringOrNull($log['Url'] ?? $fields['url'] ?? null),
            message: self::stringOrNull($log['Message'] ?? null),
            triggerMatchKey: self::stringOrNull($trigger['Key'] ?? null),
            triggerMatchValue: self::stringOrNull($trigger['Value'] ?? null),
            urlEncodedBody: self::stringOrNull($log['UrlEncodedBody'] ?? null),
            userAgent: self::stringOrNull($fields['ua'] ?? null),
            headers: self::headersFromApi($log['Headers'] ?? []),
        );
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private static function headersFromApi(mixed $headers): array
    {
        $result = [];

        foreach (is_array($headers) ? $headers : [] as $header) {
            if (! is_array($header) || ! isset($header['Name'])) {
                continue;
            }

            $result[] = [
                'name' => (string) $header['Name'],
                'value' => (string) ($header['Value'] ?? ''),
            ];
        }

        return $result;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }
}
