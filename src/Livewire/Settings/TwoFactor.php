<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Settings;

use BunnySidecar\BunnySidecar\Auth\TwoFactorAuthenticator;
use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.app')]
#[Title('Two-factor authentication')]
class TwoFactor extends Component
{
    use ResolvesCurrentUser;

    public string $code = '';

    public string $password = '';

    public bool $showingRecoveryCodes = false;

    public function mount(TwoFactorAuthenticator $twoFactor): void
    {
        abort_unless($twoFactor->isEnabled(), 404);
    }

    public function enable(TwoFactorAuthenticator $twoFactor): void
    {
        $user = $this->currentUser();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return;
        }

        $twoFactor->enable($user);
        $this->reset('code');
    }

    public function confirm(TwoFactorAuthenticator $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string']]);

        if (! $twoFactor->confirm($this->currentUser(), $this->code)) {
            $this->addError('code', __('The provided two-factor authentication code was invalid.'));

            return;
        }

        $this->reset('code');
        $this->showingRecoveryCodes = true;

        Flux::toast(variant: 'success', text: __('Two-factor authentication enabled.'));
    }

    public function cancel(TwoFactorAuthenticator $twoFactor): void
    {
        $user = $this->currentUser();

        if ($user->hasPendingTwoFactorAuthentication()) {
            $twoFactor->disable($user);
        }

        $this->reset('code');
    }

    public function showRecoveryCodes(BunnySidecar $sidecar): void
    {
        $this->validatePassword($sidecar);

        $this->showingRecoveryCodes = true;
    }

    public function regenerateRecoveryCodes(BunnySidecar $sidecar, TwoFactorAuthenticator $twoFactor): void
    {
        $this->validatePassword($sidecar);

        $twoFactor->regenerateRecoveryCodes($this->currentUser());
        $this->showingRecoveryCodes = true;

        Flux::toast(variant: 'success', text: __('New recovery codes generated.'));
    }

    public function disable(BunnySidecar $sidecar, TwoFactorAuthenticator $twoFactor): void
    {
        $this->validatePassword($sidecar);

        $twoFactor->disable($this->currentUser());
        $this->showingRecoveryCodes = false;

        Flux::toast(variant: 'success', text: __('Two-factor authentication disabled.'));
    }

    public function render(TwoFactorAuthenticator $twoFactor): View
    {
        $user = $this->currentUser();

        return view('bunny-sidecar::livewire.settings.two-factor', [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'pending' => $user->hasPendingTwoFactorAuthentication(),
            'qrCode' => $user->hasPendingTwoFactorAuthentication() ? $twoFactor->qrCodeSvg($user) : null,
            'secret' => $user->hasPendingTwoFactorAuthentication() ? $user->two_factor_secret : null,
            'recoveryCodes' => $this->showingRecoveryCodes && $user->hasEnabledTwoFactorAuthentication()
                ? ($user->two_factor_recovery_codes ?? [])
                : [],
        ]);
    }

    /**
     * Sensitive actions re-check the password; the field is cleared afterwards.
     */
    protected function validatePassword(BunnySidecar $sidecar): void
    {
        $this->validate(['password' => ['required', 'string', 'current_password:'.$sidecar->guard()]]);

        $this->reset('password');
    }
}
