@props([
    'sidebar' => false,
])

@php($name = app(\BunnySidecar\BunnySidecar\BunnySidecar::class)->name())

@if ($sidebar)
    <flux:sidebar.brand :name="$name" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-bunny-sidecar::logo-icon class="size-5 text-white dark:text-black" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$name" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-bunny-sidecar::logo-icon class="size-5 text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif
