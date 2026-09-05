<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Models;

use BunnySidecar\BunnySidecar\Database\Factories\DnsZoneGrantFactory;
use BunnySidecar\BunnySidecar\Dtos\ZoneGrantPermissions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Connects a member to one DNS zone of a bunny account. The zone is
 * identified by its domain name; zones themselves are not stored locally.
 *
 * @property int $id
 * @property int $user_id
 * @property int $bunny_account_id
 * @property string $zone_domain
 * @property ZoneGrantPermissions $permissions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'bunny_account_id', 'zone_domain', 'permissions'])]
class DnsZoneGrant extends Model
{
    /** @use HasFactory<DnsZoneGrantFactory> */
    use HasFactory;

    protected $table = 'bunny_sidecar_zone_grants';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => ZoneGrantPermissions::class,
        ];
    }

    protected static function newFactory(): DnsZoneGrantFactory
    {
        return DnsZoneGrantFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BunnyAccount, $this>
     */
    public function bunnyAccount(): BelongsTo
    {
        return $this->belongsTo(BunnyAccount::class);
    }
}
