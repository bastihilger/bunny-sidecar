<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Models;

use BunnySidecar\BunnySidecar\Database\Factories\DnsRecordOwnershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Remembers which member created a DNS record through the panel. Records
 * themselves live only at bunny.net and are addressed by their bunny.net id
 * within the zone (bunny account + domain). Records created elsewhere have
 * no ownership row and therefore no owner.
 *
 * @property int $id
 * @property int $bunny_account_id
 * @property string $zone_domain
 * @property int $record_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['bunny_account_id', 'zone_domain', 'record_id', 'created_by'])]
class DnsRecordOwnership extends Model
{
    /** @use HasFactory<DnsRecordOwnershipFactory> */
    use HasFactory;

    protected $table = 'bunny_sidecar_record_ownerships';

    protected static function newFactory(): DnsRecordOwnershipFactory
    {
        return DnsRecordOwnershipFactory::new();
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
