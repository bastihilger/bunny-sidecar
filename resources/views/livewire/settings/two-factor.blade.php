<x-bunny-sidecar::settings-layout :heading="__('Two-factor authentication')" :subheading="__('Add a second login step with an authenticator app')">
    <div class="my-6 space-y-6">
        @if ($enabled)
            <flux:callout variant="success" icon="shield-check" :heading="__('Two-factor authentication is enabled.')">
                <flux:callout.text>{{ __('You will be asked for a code from your authenticator app whenever you log in.') }}</flux:callout.text>
            </flux:callout>

            @if ($recoveryCodes !== [])
                <div class="space-y-3">
                    <flux:heading size="sm">{{ __('Recovery codes') }}</flux:heading>
                    <flux:text size="sm">{{ __('Store these in a safe place. Each code logs you in once if you lose access to your authenticator app.') }}</flux:text>
                    <ul class="grid gap-1 rounded-md bg-zinc-100 p-4 font-mono text-sm dark:bg-zinc-800" data-test="recovery-codes">
                        @foreach ($recoveryCodes as $recoveryCode)
                            <li>{{ $recoveryCode }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form wire:submit="showRecoveryCodes" class="space-y-4">
                <flux:input wire:model="password" :label="__('Confirm your password for the actions below')" type="password" autocomplete="current-password" viewable />

                <div class="flex flex-wrap gap-2">
                    @if ($recoveryCodes === [])
                        <flux:button type="submit" variant="filled" data-test="show-recovery-codes">{{ __('Show recovery codes') }}</flux:button>
                    @endif
                    <flux:button type="button" variant="filled" wire:click="regenerateRecoveryCodes" data-test="regenerate-recovery-codes">{{ __('Regenerate recovery codes') }}</flux:button>
                    <flux:button type="button" variant="danger" wire:click="disable" data-test="disable-two-factor">{{ __('Disable') }}</flux:button>
                </div>
            </form>
        @elseif ($pending)
            <flux:text>{{ __('Scan the QR code with your authenticator app, then enter the code it shows to finish the setup.') }}</flux:text>

            <div class="flex flex-wrap items-start gap-6">
                <div class="rounded-md bg-white p-2" data-test="two-factor-qr">{!! $qrCode !!}</div>
                <div class="space-y-1 text-sm">
                    <flux:text size="sm">{{ __('Or enter this setup key manually:') }}</flux:text>
                    <code class="block break-all font-mono" data-test="two-factor-secret">{{ $secret }}</code>
                </div>
            </div>

            <form wire:submit="confirm" class="space-y-4">
                <flux:input wire:model="code" :label="__('Authentication code')" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" />

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" data-test="confirm-two-factor">{{ __('Confirm and enable') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        @else
            <flux:text>{{ __('Two-factor authentication is not enabled. When enabled, you will need a code from an authenticator app such as Google Authenticator or 1Password in addition to your password.') }}</flux:text>

            <flux:button variant="primary" wire:click="enable" data-test="enable-two-factor">{{ __('Enable two-factor authentication') }}</flux:button>
        @endif
    </div>
</x-bunny-sidecar::settings-layout>
