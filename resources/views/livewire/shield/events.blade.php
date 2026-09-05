<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('WAF Events') }}</flux:heading>
        <flux:subheading>
            {{ __('Find every Shield event a WAF rule produced in the last :hours hours, including the matched data the bunny.net dashboard hides.', ['hours' => $windowHours]) }}
        </flux:subheading>
    </div>

    @if ($zonesError)
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$zonesError" />
    @endif

    <form wire:submit="search" class="flex flex-wrap items-start gap-4">
        <flux:select wire:model.live="shieldZone" :label="__('Shield zone')" :placeholder="__('Choose a shield zone…')" class="min-w-72">
            @foreach ($shieldZones as $row)
                <flux:select.option value="{{ $row['account']->id }}:{{ $row['zone']->id }}">
                    {{ $row['zone']->label() }}@if ($showAccountNames) ({{ $row['account']->name }})@endif
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select
            wire:model="ruleId"
            :label="__('Rule')"
            :placeholder="$this->shieldZone === '' ? __('Choose a shield zone first') : ($this->ruleOptions->isEmpty() ? __('No rules fired in the last :hours hours', ['hours' => $windowHours]) : __('Choose a rule…'))"
            :disabled="$this->ruleOptions->isEmpty()"
            class="min-w-72"
        >
            @foreach ($this->ruleOptions as $group)
                <flux:select.option value="{{ $group->ruleId }}">
                    {{ $group->ruleId }} · {{ trans_choice(':count event|:count events', $group->count) }}{{ $group->blocked > 0 ? ', '.__(':count blocked', ['count' => $group->blocked]) : '' }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <div class="pt-6">
            <flux:button type="submit" variant="primary" icon="magnifying-glass" wire:loading.attr="disabled" data-test="search-events-button">
                {{ __('Search') }}
            </flux:button>
        </div>
    </form>

    <div wire:loading.flex wire:target="search" class="items-center gap-2 text-sm text-zinc-500">
        <flux:icon.loading variant="mini" />
        {{ __('Searching bunny.net event logs…') }}
    </div>

    @if ($this->results !== null)
        <div wire:loading.remove wire:target="search" class="space-y-4">
            @if ($this->results['error'])
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$this->results['error']" />
            @elseif ($this->events->isEmpty())
                <flux:callout icon="shield-check" :heading="__('No events for rule :rule.', ['rule' => $searchedRuleId])">
                    <flux:callout.text>
                        {{ __('Nothing on :zone between :from and :to.', [
                            'zone' => $this->searchedTarget['zone']->label(),
                            'from' => \Illuminate\Support\Carbon::parse($this->results['from'])->format('Y-m-d H:i').' UTC',
                            'to' => \Illuminate\Support\Carbon::parse($this->results['to'])->format('Y-m-d H:i').' UTC',
                        ]) }}
                    </flux:callout.text>
                </flux:callout>
            @else
                <flux:text>
                    {{ trans_choice(':count event for rule :rule on :zone between :from and :to.|:count events for rule :rule on :zone between :from and :to.', $this->events->count(), [
                        'rule' => $searchedRuleId,
                        'zone' => $this->searchedTarget['zone']->label(),
                        'from' => \Illuminate\Support\Carbon::parse($this->results['from'])->format('Y-m-d H:i').' UTC',
                        'to' => \Illuminate\Support\Carbon::parse($this->results['to'])->format('Y-m-d H:i').' UTC',
                    ]) }}
                </flux:text>

                <div class="space-y-3">
                    @foreach ($this->events as $event)
                        <div
                            wire:key="event-{{ $event->logId }}"
                            x-data="{ expanded: false }"
                            class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
                            data-test="shield-event"
                        >
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <time
                                    datetime="{{ $event->occurredAt()->toIso8601String() }}"
                                    class="font-mono text-zinc-600 dark:text-zinc-300"
                                    x-text="new Date($el.getAttribute('datetime')).toLocaleString([], { dateStyle: 'medium', timeStyle: 'medium' })"
                                >{{ $event->occurredAt()->format('Y-m-d H:i:s') }} UTC</time>
                                @if ($event->action)
                                    <flux:badge size="sm" :color="$event->action === 'Blocked' ? 'red' : ($event->action === 'Logged' ? 'zinc' : 'amber')">
                                        {{ $event->action }}
                                    </flux:badge>
                                @endif
                                @if ($event->method)
                                    <flux:badge size="sm" color="blue">{{ $event->method }}</flux:badge>
                                @endif
                                @if ($event->remoteIp)
                                    <span class="font-mono">{{ $event->remoteIp }}</span>
                                @endif
                                @if ($event->country)
                                    <span class="text-zinc-500">{{ $event->country }}</span>
                                @endif
                            </div>

                            @if ($event->url)
                                <div class="break-all font-mono text-sm">{{ $event->url }}</div>
                            @endif

                            @if ($event->message)
                                <flux:text size="sm">{{ $event->message }}</flux:text>
                            @endif

                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <flux:text size="sm" class="font-medium">{{ __('Matched data') }}</flux:text>
                                    @if ($event->triggerMatchKey)
                                        <flux:badge size="sm" variant="pill">{{ $event->triggerMatchKey }}</flux:badge>
                                    @endif
                                </div>
                                @if ($event->triggerMatchValue !== null)
                                    @php($formattedMatch = $event->formattedTriggerMatch())
                                    <pre
                                        class="whitespace-pre-wrap break-all rounded-md bg-zinc-100 p-3 font-mono text-xs text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100"
                                        :class="expanded ? '' : 'line-clamp-[12]'"
                                        data-test="trigger-match"
                                    >{{ $formattedMatch }}</pre>
                                    <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500">
                                        @if (substr_count($formattedMatch, "\n") > 8 || mb_strlen($formattedMatch) > 400)
                                            <button type="button" class="underline" x-on:click="expanded = ! expanded">
                                                <span x-show="! expanded">{{ __('Show full match') }}</span>
                                                <span x-show="expanded" x-cloak>{{ __('Collapse') }}</span>
                                            </button>
                                        @endif
                                        @if ($event->triggerMatchIsTruncated())
                                            <span>{{ __('bunny.net cuts matched data off after :limit characters.', ['limit' => \BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldEvent::TRIGGER_MATCH_LIMIT]) }}</span>
                                        @endif
                                    </div>
                                @else
                                    <flux:text size="sm" class="text-zinc-500">{{ __('No matched data recorded for this event.') }}</flux:text>
                                @endif
                            </div>

                            <details class="text-sm">
                                <summary class="cursor-pointer text-zinc-500">{{ __('Request body & headers') }}</summary>
                                <div class="mt-2 space-y-3">
                                    <div>
                                        <flux:text size="sm" class="font-medium">{{ __('Form-encoded body') }}</flux:text>
                                        @if ($event->urlEncodedBody !== null)
                                            <pre class="whitespace-pre-wrap break-all rounded-md bg-zinc-100 p-3 font-mono text-xs dark:bg-zinc-800">{{ $event->urlEncodedBody }}</pre>
                                        @else
                                            <flux:text size="sm" class="text-zinc-500">{{ __('Not recorded. bunny.net only logs form-encoded bodies; this request sent JSON, so the matched data above is all that was captured.') }}</flux:text>
                                        @endif
                                    </div>

                                    @if ($event->headers !== [])
                                        <div>
                                            <flux:text size="sm" class="font-medium">{{ __('Headers') }}</flux:text>
                                            <dl class="mt-1 grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 font-mono text-xs">
                                                @foreach ($event->headers as $header)
                                                    <dt class="text-zinc-500">{{ $header['name'] }}</dt>
                                                    <dd class="break-all">{{ $header['value'] }}</dd>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
