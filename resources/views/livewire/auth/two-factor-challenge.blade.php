<div class="flex flex-col gap-6" x-data="{ recovery: false }">
    <x-bunny-sidecar::auth-header
        :title="__('Two-factor authentication')"
        :description="__('Enter the code from your authenticator app to finish logging in.')"
    />

    <form wire:submit="authenticate" class="flex flex-col gap-6">
        <div x-show="! recovery">
            <flux:input
                wire:model="code"
                :label="__('Authentication code')"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus
                placeholder="123456"
            />
        </div>

        <div x-show="recovery" x-cloak>
            <flux:input
                wire:model="recovery_code"
                :label="__('Recovery code')"
                type="text"
                autocomplete="off"
                placeholder="xxxxxxxxxx-xxxxxxxxxx"
            />
        </div>

        <flux:button type="submit" variant="primary" class="w-full" data-test="two-factor-submit">
            {{ __('Log in') }}
        </flux:button>
    </form>

    <div class="text-center text-sm text-zinc-400">
        <button type="button" class="underline" x-show="! recovery" x-on:click="recovery = true; $wire.set('code', '')">
            {{ __('Use a recovery code instead') }}
        </button>
        <button type="button" class="underline" x-show="recovery" x-cloak x-on:click="recovery = false; $wire.set('recovery_code', '')">
            {{ __('Use an authentication code instead') }}
        </button>
    </div>
</div>
