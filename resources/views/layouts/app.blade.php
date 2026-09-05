<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('bunny-sidecar::partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-bunny-sidecar::logo :sidebar="true" href="{{ route('bunny-sidecar.zones.index') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="globe-alt" :href="route('bunny-sidecar.zones.index')" :current="request()->routeIs('bunny-sidecar.zones.*')" wire:navigate>
                        {{ __('Zones') }}
                    </flux:sidebar.item>

                    @if ($user->isAdmin())
                        <flux:sidebar.item icon="key" :href="route('bunny-sidecar.accounts.index')" :current="request()->routeIs('bunny-sidecar.accounts.*')" wire:navigate>
                            {{ __('Bunny Accounts') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="shield-check" :href="route('bunny-sidecar.shield.events')" :current="request()->routeIs('bunny-sidecar.shield.*')" wire:navigate>
                            {{ __('WAF Events') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="users" :href="route('bunny-sidecar.users.index')" :current="request()->routeIs('bunny-sidecar.users.*')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-bunny-sidecar::user-menu :user="$user" class="hidden lg:block" />
        </flux:sidebar>

        {{-- Mobile header --}}
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />

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

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('bunny-sidecar.logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
