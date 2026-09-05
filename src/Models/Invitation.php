<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Models;

use BunnySidecar\BunnySidecar\Database\Factories\InvitationFactory;
use BunnySidecar\BunnySidecar\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property UserRole $role
 * @property string $token sha256 hash of the plain token sent by mail
 * @property int|null $invited_by
 * @property list<array{bunny_account_id: int, zone_domain: string, permissions: array<string, mixed>}>|null $zone_grants
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'name', 'role', 'token', 'invited_by', 'zone_grants', 'expires_at', 'accepted_at'])]
#[Hidden(['token'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    public const int EXPIRES_AFTER_DAYS = 7;

    protected $table = 'bunny_sidecar_invitations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'zone_grants' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): InvitationFactory
    {
        return InvitationFactory::new();
    }

    /**
     * Create an invitation and return it together with the plain token,
     * which is only ever exposed through the invitation mail.
     *
     * @param  list<array{bunny_account_id: int, zone_domain: string, permissions: array<string, mixed>}>  $zoneGrants
     * @return array{0: self, 1: string}
     */
    public static function issue(string $email, ?string $name, UserRole $role, array $zoneGrants, User $invitedBy): array
    {
        $plainToken = Str::random(64);

        $invitation = self::create([
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'token' => hash('sha256', $plainToken),
            'invited_by' => $invitedBy->id,
            'zone_grants' => $role === UserRole::Member ? $zoneGrants : null,
            'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
        ]);

        return [$invitation, $plainToken];
    }

    public static function findByPlainToken(string $plainToken): ?self
    {
        return self::query()->where('token', hash('sha256', $plainToken))->first();
    }

    /**
     * Regenerate the token and extend the expiry; returns the new plain token.
     */
    public function reissue(): string
    {
        $plainToken = Str::random(64);

        $this->update([
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
        ]);

        return $plainToken;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
