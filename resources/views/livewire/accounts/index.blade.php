<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Bunny Accounts') }}</flux:heading>
            <flux:subheading>{{ __('bunny.net accounts whose DNS zones are managed here.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create" data-test="add-account-button">
            {{ __('Add account') }}
        </flux:button>
    </div>

    @if ($accounts->isEmpty())
        <flux:callout icon="key" :heading="__('No bunny.net accounts yet.')">
            <flux:callout.text>{{ __('Add an account with its API key to manage its DNS zones.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('API key') }}</flux:table.column>
                <flux:table.column>{{ __('Member grants') }}</flux:table.column>
                <flux:table.column>{{ __('Added') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($accounts as $account)
                    <flux:table.row wire:key="account-{{ $account->id }}">
                        <flux:table.cell variant="strong">{{ $account->name }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $account->apiKeyHint() }}</flux:table.cell>
                        <flux:table.cell>{{ $account->grants_count }}</flux:table.cell>
                        <flux:table.cell>{{ $account->created_at?->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="subtle" icon="ellipsis-horizontal" :aria-label="__('Account actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="signal" wire:click="testConnection({{ $account->id }})">
                                        {{ __('Test connection') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $account->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $account->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="account-form" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $form->account ? __('Edit bunny account') : __('Add bunny account') }}
                </flux:heading>
                <flux:subheading>{{ __('The API key is stored encrypted and never shown again.') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.name" :label="__('Name')" :placeholder="__('e.g. Company account')" />

            <flux:input
                wire:model="form.api_key"
                type="password"
                viewable
                :label="__('API key')"
                :description="$form->account ? __('Leave empty to keep the current key.') : __('The account API key from the bunny.net dashboard.')"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-account-button">
                    {{ __('Save account') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-account" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete bunny account?') }}</flux:heading>
                <flux:subheading>
                    {{ __('All member zone grants for this account will be removed as well. DNS zones at bunny.net are not touched.') }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete" data-test="confirm-delete-account">
                    {{ __('Delete account') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
