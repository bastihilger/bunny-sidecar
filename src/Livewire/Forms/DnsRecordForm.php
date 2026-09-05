<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Forms;

use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsRecord;
use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordField;
use Livewire\Form;

class DnsRecordForm extends Form
{
    public ?int $recordId = null;

    public int $type = 0;

    public string $name = '';

    public string $value = '';

    public int $ttl = 300;

    public ?int $priority = null;

    public ?int $weight = null;

    public ?int $port = null;

    public int $flags = 0;

    public ?string $tag = null;

    public ?int $pullZoneId = null;

    public ?int $scriptId = null;

    public bool $accelerated = false;

    public bool $disabled = false;

    public ?string $comment = null;

    /**
     * Fields the form does not edit but must send back unchanged on update,
     * so bunny.net does not reset them (monitoring, smart routing, geo).
     *
     * @var array<string, mixed>
     */
    public array $passthrough = [];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'type' => ['required', 'integer', 'in:'.implode(',', array_column(DnsEntryType::cases(), 'value'))],
            'name' => ['nullable', 'string', 'max:253'],
            'ttl' => ['required', 'integer', 'between:15,86400'],
            'disabled' => ['boolean'],
            'accelerated' => ['boolean'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], $this->entryType()->rules());
    }

    public function entryType(): DnsEntryType
    {
        return DnsEntryType::from($this->type);
    }

    public function fillFromRecord(DnsRecord $record): void
    {
        $this->recordId = $record->id;
        $this->type = $record->type->value;
        $this->name = $record->name;
        $this->value = $record->value;
        $this->ttl = $record->ttl;
        $this->priority = $record->priority;
        $this->weight = $record->weight;
        $this->port = $record->port;
        $this->flags = $record->flags;
        $this->tag = $record->tag;
        $this->pullZoneId = $record->pullZoneId;
        $this->scriptId = $record->scriptId;
        $this->accelerated = $record->accelerated;
        $this->disabled = $record->disabled;
        $this->comment = $record->comment;
        $this->passthrough = [
            'MonitorType' => $record->monitorType,
            'SmartRoutingType' => $record->smartRoutingType,
            'GeolocationLatitude' => $record->geolocationLatitude,
            'GeolocationLongitude' => $record->geolocationLongitude,
            'LatencyZone' => $record->latencyZone,
            'AutoSslIssuance' => $record->autoSslIssuance,
        ];
    }

    public function resetForType(DnsEntryType $type): void
    {
        $this->reset();
        $this->type = $type->value;
    }

    /**
     * The complete PascalCase request body for the bunny.net API. Always
     * contains every field the form knows so updates never reset values.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $type = $this->entryType();

        return array_filter([
            'Type' => $this->type,
            'Name' => $this->name,
            'Value' => $type->hasField(DnsRecordField::Value) ? $this->value : '',
            'Ttl' => $this->ttl,
            'Priority' => $this->priority,
            'Weight' => $this->weight,
            'Port' => $this->port,
            'Flags' => $type->hasField(DnsRecordField::Flags) ? $this->flags : null,
            'Tag' => $this->tag,
            'PullZoneId' => $this->pullZoneId,
            'ScriptId' => $this->scriptId,
            'Accelerated' => $this->accelerated,
            'Disabled' => $this->disabled,
            'Comment' => $this->comment,
            ...$this->passthrough,
        ], fn (mixed $value): bool => $value !== null);
    }
}
