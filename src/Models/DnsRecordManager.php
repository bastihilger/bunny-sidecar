<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Models;

use BunnySidecar\BunnySidecar\Database\Factories\DnsRecordManagerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An explicit assignment that lets one member edit and delete one specific
 * bunny.net DNS record, regardless of who created it or what the member's
 * zone grant allows. Assigned by admins on the zone page.
 *
 * @property int $id
 * @property int $bunny_account_id
 * @property string $zone_domain
 * @property int $record_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['bunny_account_id', 'zone_domain', 'record_id', 'user_id'])]
class DnsRecordManager extends Model
{
    /** @use HasFactory<DnsRecordManagerFactory> */
    use HasFactory;

    protected $table = 'bunny_sidecar_record_managers';

    protected static function newFactory(): DnsRecordManagerFactory
    {
        return DnsRecordManagerFactory::new();
    }

    /**
     * @return BelongsTo<BunnyAccount, $this>
     */
    public function bunnyAccount(): BelongsTo
    {
        return $this->belongsTo(BunnyAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
