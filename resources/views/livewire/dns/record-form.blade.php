@php
    use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
    use BunnySidecar\BunnySidecar\Enums\DnsRecordField;
@endphp

<flux:modal name="dns-record-form" class="w-full max-w-lg">
    <form wire:submit="save" class="space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $isUpdate ? __('Edit DNS record') : __('Add DNS record') }}
            </flux:heading>
            <flux:subheading>{{ $domain }}</flux:subheading>
        </div>

        <flux:select wire:model.live="form.type" :label="__('Type')" :disabled="$isUpdate">
            @foreach ($typeOptions as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input
            wire:model="form.name"
            :label="__('Name')"
            :description="__('Leave empty for the zone root (@).')"
            placeholder="www"
        />

        @if ($entryType->hasField(DnsRecordField::Value))
            @if ($entryType === DnsEntryType::TXT)
                <flux:textarea wire:model="form.value" :label="__('Value')" rows="3" />
            @else
                <flux:input wire:model="form.value" :label="__('Value')" />
            @endif
        @endif

        @if ($entryType->hasField(DnsRecordField::PullZoneId))
            <flux:input wire:model="form.pullZoneId" type="number" :label="__('Pull Zone ID')" />
        @endif

        @if ($entryType->hasField(DnsRecordField::ScriptId))
            <flux:input wire:model="form.scriptId" type="number" :label="__('Script ID')" />
        @endif

        <div class="grid grid-cols-2 gap-4">
            <flux:select wire:model="form.ttl" :label="__('TTL')">
                @foreach ([15 => '15s', 30 => '30s', 60 => '1m', 120 => '2m', 300 => '5m', 900 => '15m', 1800 => '30m', 3600 => '1h', 18000 => '5h', 43200 => '12h', 86400 => '1d'] as $seconds => $label)
                    <flux:select.option value="{{ $seconds }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($entryType->hasField(DnsRecordField::Priority))
                <flux:input wire:model="form.priority" type="number" :label="__('Priority')" />
            @endif
            @if ($entryType->hasField(DnsRecordField::Weight))
                <flux:input wire:model="form.weight" type="number" :label="__('Weight')" />
            @endif
            @if ($entryType->hasField(DnsRecordField::Port))
                <flux:input wire:model="form.port" type="number" :label="__('Port')" />
            @endif
            @if ($entryType->hasField(DnsRecordField::Flags))
                <flux:select wire:model="form.flags" :label="__('Flags')">
                    <flux:select.option value="0">0</flux:select.option>
                    <flux:select.option value="128">128</flux:select.option>
                </flux:select>
            @endif
            @if ($entryType->hasField(DnsRecordField::Tag))
                <flux:select wire:model="form.tag" :label="__('Tag')">
                    <flux:select.option value="issue">issue</flux:select.option>
                    <flux:select.option value="issuewild">issuewild</flux:select.option>
                    <flux:select.option value="iodef">iodef</flux:select.option>
                </flux:select>
            @endif
        </div>

        @if ($entryType->hasField(DnsRecordField::Accelerated))
            <flux:switch
                wire:model="form.accelerated"
                :label="__('Accelerated')"
                :description="__('Route this record through the bunny.net CDN.')"
            />
        @endif

        <flux:switch wire:model="form.disabled" :label="__('Disabled')" />

        <flux:input wire:model="form.comment" :label="__('Comment')" :description="__('Optional note, only visible in bunny.net.')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary" data-test="save-record-button">
                <span wire:loading.remove wire:target="save">{{ $isUpdate ? __('Save changes') : __('Add record') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </flux:button>
        </div>
    </form>
</flux:modal>
