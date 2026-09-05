@props([
    'heading',
    'subheading',
])

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your profile and password') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex items-start max-md:flex-col">
        <div class="me-10 w-full pb-4 md:w-[220px]">
            <flux:navlist aria-label="{{ __('Settings') }}">
                <flux:navlist.item :href="route('bunny-sidecar.profile.edit')" :current="request()->routeIs('bunny-sidecar.profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
                <flux:navlist.item :href="route('bunny-sidecar.password.edit')" :current="request()->routeIs('bunny-sidecar.password.edit')" wire:navigate>{{ __('Password') }}</flux:navlist.item>
            </flux:navlist>
        </div>

        <flux:separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6">
            <flux:heading>{{ $heading }}</flux:heading>
            <flux:subheading>{{ $subheading }}</flux:subheading>

            <div class="mt-5 w-full max-w-lg">
                {{ $slot }}
            </div>
        </div>
    </div>
</section>
