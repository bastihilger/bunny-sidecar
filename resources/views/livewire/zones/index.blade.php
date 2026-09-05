<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('DNS Zones') }}</flux:heading>
            <flux:subheading>{{ __('All zones you have access to.') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:input
                icon="magnifying-glass"
                :placeholder="__('Search domains…')"
                wire:model.live.debounce.300ms="search"
                clearable
            />
            <flux:tooltip :content="__('Reload zones from bunny.net')">
                <flux:button icon="arrow-path" wire:click="refreshZones" :aria-label="__('Refresh zones')" />
            </flux:tooltip>
        </div>
    </div>

    @if ($apiError)
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$apiError" />
    @endif

    @if ($rows->isEmpty() && ! $apiError)
        <flux:callout icon="globe-alt" :heading="__('No zones found.')">
            <flux:callout.text>
                @if ($isAdmin)
                    {{ __('Add a bunny.net account to see its DNS zones here.') }}
                @else
                    {{ __('No DNS zones have been shared with you yet.') }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Domain') }}</flux:table.column>
                <flux:table.column>{{ __('Account') }}</flux:table.column>
                <flux:table.column>{{ __('Nameservers') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($rows as $row)
                    <flux:table.row wire:key="zone-{{ $row['account']->id }}-{{ $row['zone']->id }}">
                        <flux:table.cell variant="strong">
                            <flux:link
                                :href="route('bunny-sidecar.zones.show', ['account' => $row['account'], 'domain' => $row['zone']->domain])"
                                wire:navigate
                            >
                                {{ $row['zone']->domain }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $row['account']->name }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ collect([$row['zone']->nameserver1, $row['zone']->nameserver2])->filter()->join(', ') }}
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button
                                size="sm"
                                variant="subtle"
                                icon="chevron-right"
                                :href="route('bunny-sidecar.zones.show', ['account' => $row['account'], 'domain' => $row['zone']->domain])"
                                wire:navigate
                                :aria-label="__('Open zone :domain', ['domain' => $row['zone']->domain])"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
