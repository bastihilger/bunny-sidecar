<div class="flex flex-col gap-6">
    @if ($invitation === null)
        <x-bunny-sidecar::auth-header
            :title="__('Invitation invalid')"
            :description="__('This invitation link is invalid, expired, or has already been used.')"
        />

        <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Please ask an admin to send you a new invitation.')" />

        <flux:button variant="primary" :href="route('bunny-sidecar.login')" wire:navigate class="w-full">
            {{ __('Go to login') }}
        </flux:button>
    @else
        <x-bunny-sidecar::auth-header
            :title="__('Set up your account')"
            :description="__('You were invited as :email. Choose a name and password to get started.', ['email' => $invitation->email])"
        />

        <form wire:submit="accept" class="flex flex-col gap-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" viewable />

            <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />

            <flux:button type="submit" variant="primary" class="w-full" data-test="accept-invitation-button">
                {{ __('Create account') }}
            </flux:button>
        </form>
    @endif
</div>
