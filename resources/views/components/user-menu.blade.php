@props([
    'user',
])

<flux:dropdown position="bottom" align="start" {{ $attributes }}>
    <flux:sidebar.profile
        :name="$user->name"
        :initials="$user->initials()"
        icon:trailing="chevron-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar :name="$user->name" :initials="$user->initials()" />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                <flux:text class="truncate">{{ $user->email }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.item :href="route('bunny-sidecar.profile.edit')" icon="cog" wire:navigate>
            {{ __('Settings') }}
        </flux:menu.item>
        <form method="POST" action="{{ route('bunny-sidecar.logout') }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
