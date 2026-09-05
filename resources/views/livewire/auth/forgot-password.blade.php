<div class="flex flex-col gap-6">
    <x-bunny-sidecar::auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

    <x-bunny-sidecar::auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <flux:input
            wire:model="email"
            :label="__('Email address')"
            type="email"
            required
            autofocus
            placeholder="email@example.com"
        />

        <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
            {{ __('Email password reset link') }}
        </flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400 rtl:space-x-reverse">
        <span>{{ __('Or, return to') }}</span>
        <flux:link :href="route('bunny-sidecar.login')" wire:navigate>{{ __('log in') }}</flux:link>
    </div>
</div>
