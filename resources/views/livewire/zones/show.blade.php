<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('bunny-sidecar.zones.index')" wire:navigate>{{ __('Zones') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $domain }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <div class="mt-2 flex items-center gap-3">
                <flux:heading size="xl">{{ $domain }}</flux:heading>
                <flux:badge size="sm">{{ $account->name }}</flux:badge>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:tooltip :content="__('Reload records from bunny.net')">
                <flux:button icon="arrow-path" wire:click="refreshRecords" :aria-label="__('Refresh records')" />
            </flux:tooltip>
            @if ($canAdd)
                <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-record-form')">
                    {{ __('Add record') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if ($apiError)
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$apiError" />
    @elseif ($rows->isEmpty())
        <flux:callout icon="document-plus" :heading="__('This zone has no DNS records yet.')" />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Value') }}</flux:table.column>
                <flux:table.column>{{ __('TTL') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                @if ($canAssignManagers)
                    <flux:table.column>{{ __('Managers') }}</flux:table.column>
                @endif
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($rows as $row)
                    @php($record = $row['record'])
                    <flux:table.row wire:key="record-{{ $record->id }}">
                        <flux:table.cell>
                            <flux:badge size="sm" color="blue">{{ $record->type->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">
                            {{ $record->name !== '' ? $record->name : '@' }}
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs">
                            <flux:tooltip class="flex">
                                <span class="min-w-0 truncate">{{ $record->value }}</span>
                                <flux:tooltip.content class="max-w-[300px] wrap-anywhere">{{ $record->value }}</flux:tooltip.content>
                            </flux:tooltip>
                        </flux:table.cell>
                        <flux:table.cell>{{ $record->ttl }}s</flux:table.cell>
                        <flux:table.cell>
                            @if ($record->disabled)
                                <flux:badge size="sm" color="amber">{{ __('Disabled') }}</flux:badge>
                            @endif
                            @if ($record->accelerated)
                                <flux:badge size="sm" color="green">{{ __('Accelerated') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        @if ($canAssignManagers)
                            <flux:table.cell>
                                <flux:dropdown position="bottom" align="start">
                                    <flux:button size="sm" variant="subtle" icon="users" icon:trailing="chevron-down">
                                        {{ count($managerIds[$record->id] ?? []) }}
                                    </flux:button>
                                    <flux:menu>
                                        <flux:menu.checkbox.group wire:model.live="managerIds.{{ $record->id }}">
                                            @foreach ($members as $member)
                                                <flux:menu.checkbox value="{{ $member->id }}">{{ $member->name }}</flux:menu.checkbox>
                                            @endforeach
                                        </flux:menu.checkbox.group>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        @endif
                        <flux:table.cell align="end">
                            @if ($row['canManage'])
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="subtle" icon="ellipsis-horizontal" :aria-label="__('Record actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="$dispatch('open-record-form', { recordId: {{ $record->id }} })">
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $record->id }})">
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="delete-record" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete DNS record?') }}</flux:heading>
                <flux:subheading>{{ __('This removes the record from bunny.net immediately. This cannot be undone.') }}</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteRecord" data-test="confirm-delete-record">
                    {{ __('Delete record') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    @if ($canAdd || $rows->contains(fn (array $row): bool => $row['canManage']))
        <livewire:bunny-sidecar.dns.record-form :account="$account" :domain="$domain" :zone-id="$zoneId" />
    @endif
</div>
