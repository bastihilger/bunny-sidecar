<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny;

use RuntimeException;
use Throwable;

class BunnyApiException extends RuntimeException
{
    public function __construct(
        public readonly string $accountName,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "bunny.net API request for account [{$accountName}] failed".($status !== null ? " with status {$status}" : ''),
            previous: $previous,
        );
    }

    /**
     * A message safe to show in the UI (never contains the API key).
     */
    public function userMessage(): string
    {
        return match (true) {
            $this->status === 401 => __('bunny.net rejected the API key of account ":account". Please check the key.', ['account' => $this->accountName]),
            $this->status === 429 => __('bunny.net is throttling requests for account ":account". Please try again in a moment.', ['account' => $this->accountName]),
            $this->status !== null => __('bunny.net returned an error (:status) for account ":account".', ['status' => $this->status, 'account' => $this->accountName]),
            default => __('Could not reach the bunny.net API for account ":account".', ['account' => $this->accountName]),
        };
    }
}
