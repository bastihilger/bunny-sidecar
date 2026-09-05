<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Models;

use BunnySidecar\BunnySidecar\Database\Factories\BunnyAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A bunny.net account (name + API key). The key is stored encrypted.
 *
 * @property int $id
 * @property string $name
 * @property string $api_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'api_key'])]
#[Hidden(['api_key'])]
class BunnyAccount extends Model
{
    /** @use HasFactory<BunnyAccountFactory> */
    use HasFactory;

    protected $table = 'bunny_sidecar_accounts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }

    protected static function newFactory(): BunnyAccountFactory
    {
        return BunnyAccountFactory::new();
    }

    /**
     * @return HasMany<DnsZoneGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(DnsZoneGrant::class);
    }

    /**
     * The last characters of the API key for display purposes.
     */
    public function apiKeyHint(): string
    {
        return '••••'.Str::substr($this->api_key, -4);
    }
}
