@php
    use BunnySidecar\BunnySidecar\Enums\UserRole;
@endphp

<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('bunny-sidecar.users.index')" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Invite') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <flux:heading size="xl" class="mt-2">{{ __('Invite user') }}</flux:heading>
        <flux:subheading>{{ __('The user receives an email with a link to set up their account.') }}</flux:subheading>
    </div>

    <form wire:submit="invite" class="space-y-8">
        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="email" type="email" :label="__('Email address')" required />
            <flux:input wire:model="name" :label="__('Name')" :description="__('Optional, prefilled on the sign-up form.')" />
        </div>

        <flux:radio.group wire:model.live="role" :label="__('Role')">
            <flux:radio
                :value="UserRole::Member->value"
                :label="UserRole::Member->label()"
                :description="__('Can only edit DNS records in the zones connected below.')"
            />
            <flux:radio
                :value="UserRole::Admin->value"
                :label="UserRole::Admin->label()"
                :description="__('Full access: all zones, accounts and users.')"
            />
        </flux:radio.group>

        @if ($role === UserRole::Member->value)
            @include('bunny-sidecar::partials.zone-grants-editor', ['grantsForm' => $grantsForm, 'zoneOptions' => $zoneOptions])
        @endif

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('bunny-sidecar.users.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" data-test="send-invitation-button">
                {{ __('Send invitation') }}
            </flux:button>
        </div>
    </form>
</div>
