@php
    use BunnySidecar\BunnySidecar\Enums\UserRole;
@endphp

<div class="space-y-10">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('Manage who can access this panel and which DNS zones they may edit.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="user-plus" :href="route('bunny-sidecar.users.invite')" wire:navigate data-test="invite-user-button">
            {{ __('Invite user') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Zones') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}">
                    <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$user->isAdmin() ? 'purple' : 'zinc'">
                            {{ $user->role->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $user->isAdmin() ? __('All') : $user->zone_grants_count }}
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="subtle" icon="ellipsis-horizontal" :aria-label="__('User actions')" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" :href="route('bunny-sidecar.users.edit', $user)" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->id }})">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="space-y-4">
        <flux:heading size="lg">{{ __('Pending invitations') }}</flux:heading>

        @if ($invitations->isEmpty())
            <flux:text>{{ __('No pending invitations.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Invited by') }}</flux:table.column>
                    <flux:table.column>{{ __('Expires') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($invitations as $invitation)
                        <flux:table.row wire:key="invitation-{{ $invitation->id }}">
                            <flux:table.cell variant="strong">{{ $invitation->email }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$invitation->role === UserRole::Admin ? 'purple' : 'zinc'">
                                    {{ $invitation->role->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $invitation->invitedBy?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($invitation->isExpired())
                                    <flux:badge size="sm" color="red">{{ __('Expired') }}</flux:badge>
                                @else
                                    {{ $invitation->expires_at->diffForHumans() }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="subtle" icon="ellipsis-horizontal" :aria-label="__('Invitation actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="paper-airplane" wire:click="resendInvitation({{ $invitation->id }})">
                                            {{ __('Resend') }}
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="revokeInvitation({{ $invitation->id }})">
                                            {{ __('Revoke') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:modal name="delete-user" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete user?') }}</flux:heading>
                <flux:subheading>{{ __('The user and all of their zone grants will be removed.') }}</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteUser" data-test="confirm-delete-user">
                    {{ __('Delete user') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
