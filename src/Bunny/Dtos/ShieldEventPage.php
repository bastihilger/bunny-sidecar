<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny\Dtos;

/**
 * One page of a bunny.net Shield event log search (pages are zero-based).
 */
final readonly class ShieldEventPage
{
    /**
     * @param  list<ShieldEvent>  $events
     */
    public function __construct(
        public array $events,
        public int $page,
        public int $totalPages,
        public int $total,
    ) {}

    public function hasMorePages(): bool
    {
        return $this->page + 1 < $this->totalPages;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        return new self(
            events: array_map(ShieldEvent::fromApi(...), array_values($data['rows'] ?? [])),
            page: (int) ($data['page'] ?? 0),
            totalPages: (int) ($data['totalPages'] ?? 0),
            total: (int) ($data['total'] ?? 0),
        );
    }
}
