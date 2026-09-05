<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('bunny-sidecar::partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('bunny-sidecar.home') }}" class="mb-4 flex flex-col items-center gap-2" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                        <x-bunny-sidecar::logo-icon class="size-6 text-white dark:text-black" />
                    </span>
                    <span class="text-lg font-semibold text-zinc-800 dark:text-zinc-100">{{ app(\BunnySidecar\BunnySidecar\BunnySidecar::class)->name() }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
