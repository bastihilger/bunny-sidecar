<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny\Dtos;

/**
 * Aggregated Shield events of one WAF rule (event log search grouped by ruleId).
 */
final readonly class ShieldRuleGroup
{
    /**
     * @param  int  $firstSeen  Unix milliseconds
     * @param  int  $lastSeen  Unix milliseconds
     */
    public function __construct(
        public string $ruleId,
        public int $count,
        public int $blocked = 0,
        public int $challenged = 0,
        public int $logged = 0,
        public int $firstSeen = 0,
        public int $lastSeen = 0,
    ) {}

    /**
     * OWASP core rule set (WAF) rule ids all start with 9; other ids belong
     * to rate limits and custom rules.
     */
    public function isWafRule(): bool
    {
        return str_starts_with($this->ruleId, '9');
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
     * @param  array<string, mixed>  $group  one entry of the search response's "groups"
     */
    public static function fromApi(array $group): self
    {
        $context = $group['context'] ?? [];

        return new self(
            ruleId: (string) ($group['key']['ruleId'] ?? ''),
            count: (int) ($group['count'] ?? 0),
            blocked: (int) ($context['blocked'] ?? 0),
            challenged: (int) ($context['challenged'] ?? 0),
            logged: (int) ($context['logged'] ?? 0),
            firstSeen: (int) ($group['firstSeen'] ?? 0),
            lastSeen: (int) ($group['lastSeen'] ?? 0),
        );
    }
}
