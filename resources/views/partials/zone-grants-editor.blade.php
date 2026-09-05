{{-- Shared zone grants editor. Expects: $grantsForm (ZoneGrantsForm bound as `grantsForm`), $zoneOptions (["{accountId}:{domain}" => label]). --}}
@php
    use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
    use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
    use BunnySidecar\BunnySidecar\Livewire\Forms\ZoneGrantsForm;
@endphp

<div class="space-y-4">
    <div>
        <flux:heading size="lg">{{ __('DNS zone access') }}</flux:heading>
        <flux:subheading>{{ __('Connect zones and choose which record types the member may add, separately for the zone root (@) and for subdomains. Members may edit and delete the records they added themselves. Access to other records is assigned per record on the zone page. Connected zones are always visible read-only.') }}</flux:subheading>
    </div>

    @if ($zoneOptions === [])
        <flux:callout icon="globe-alt" :heading="__('No zones available. Add a bunny.net account first.')" />
    @endif

    @foreach ($grantsForm->rows as $index => $row)
        <flux:card wire:key="grant-row-{{ $index }}" class="space-y-4">
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:select
                        wire:model="grantsForm.rows.{{ $index }}.zone"
                        :label="__('Zone')"
                        :placeholder="__('Select a zone…')"
                    >
                        @foreach ($zoneOptions as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:button
                    icon="trash"
                    variant="subtle"
                    wire:click="removeGrantRow({{ $index }})"
                    :aria-label="__('Remove zone')"
                />
            </div>
            <flux:error name="grantsForm.rows.{{ $index }}.zone" />

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (DnsRecordScope::cases() as $scope)
                    <div class="space-y-3">
                        <flux:radio.group
                            variant="segmented"
                            size="sm"
                            wire:model.live="grantsForm.rows.{{ $index }}.{{ $scope->value }}_mode"
                            :label="__('Add records: :scope', ['scope' => $scope->label()])"
                        >
                            <flux:radio value="{{ ZoneGrantsForm::MODE_NONE }}" :label="__('None')" />
                            <flux:radio value="{{ ZoneGrantsForm::MODE_ALL }}" :label="__('All')" />
                            <flux:radio value="{{ ZoneGrantsForm::MODE_SELECTED }}" :label="__('Some')" />
                        </flux:radio.group>

                        @if ($row[$scope->value.'_mode'] === ZoneGrantsForm::MODE_SELECTED)
                            <flux:checkbox.group
                                wire:model="grantsForm.rows.{{ $index }}.{{ $scope->value }}_types"
                                :label="__('Record types')"
                                class="grid grid-cols-2 gap-x-4 sm:grid-cols-3"
                            >
                                @foreach (DnsEntryType::options() as $value => $label)
                                    <flux:checkbox value="{{ $value }}" :label="$label" />
                                @endforeach
                            </flux:checkbox.group>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endforeach

    <flux:button icon="plus" variant="subtle" wire:click="addGrantRow" data-test="add-grant-row">
        {{ __('Connect zone') }}
    </flux:button>
</div>
