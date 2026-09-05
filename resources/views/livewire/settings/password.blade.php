<x-bunny-sidecar::settings-layout :heading="__('Password')" :subheading="__('Use a long, random password to keep your account secure')">
    <form wire:submit="updatePassword" class="my-6 w-full space-y-6">
        <flux:input wire:model="current_password" :label="__('Current password')" type="password" required autocomplete="current-password" viewable />

        <flux:input wire:model="password" :label="__('New password')" type="password" required autocomplete="new-password" viewable />

        <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit" data-test="update-password-button">
                {{ __('Save') }}
            </flux:button>
        </div>
    </form>
</x-bunny-sidecar::settings-layout>
