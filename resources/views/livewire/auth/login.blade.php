<div class="flex flex-col gap-6">
    <x-bunny-sidecar::auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

    <x-bunny-sidecar::auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-6">
        <flux:input
            wire:model="email"
            :label="__('Email address')"
            type="email"
            required
            autofocus
            autocomplete="email"
            placeholder="email@example.com"
        />

        <div class="relative">
            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />

            <flux:link class="absolute end-0 top-0 text-sm" :href="route('bunny-sidecar.password.request')" wire:navigate>
                {{ __('Forgot your password?') }}
            </flux:link>
        </div>

        <flux:checkbox wire:model="remember" :label="__('Remember me')" />

        <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
            {{ __('Log in') }}
        </flux:button>
    </form>
</div>
