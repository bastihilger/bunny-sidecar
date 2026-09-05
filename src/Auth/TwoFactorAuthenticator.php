<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Auth;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (authenticator app) two-factor authentication with recovery codes.
 * Secrets and codes are stored encrypted on the panel user.
 */
class TwoFactorAuthenticator
{
    public function __construct(
        private Google2FA $engine,
        private BunnySidecar $sidecar,
    ) {}

    /**
     * Whether the feature is switched on in the config.
     */
    public function isEnabled(): bool
    {
        return config()->boolean('bunny-sidecar.two_factor.enabled', true);
    }

    /**
     * Whether the user has to pass the challenge after entering the password.
     */
    public function isRequiredFor(User $user): bool
    {
        return $this->isEnabled() && $user->hasEnabledTwoFactorAuthentication();
    }

    /**
     * Generate a fresh secret and recovery codes; the secret is unconfirmed
     * until the user enters a valid code from their authenticator app.
     */
    public function enable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => $this->engine->generateSecretKey(),
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Confirm the pending secret with a code from the authenticator app.
     */
    public function confirm(User $user, string $code): bool
    {
        if (! $user->hasPendingTwoFactorAuthentication() || ! $this->verifyCode($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function regenerateRecoveryCodes(User $user): void
    {
        $user->forceFill(['two_factor_recovery_codes' => $this->generateRecoveryCodes()])->save();
    }

    /**
     * Verify a code from the authenticator app against the user's secret.
     */
    public function verifyCode(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;
        $code = preg_replace('/\s+/', '', $code) ?? $code;

        if ($secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        return $this->engine->verifyKey($secret, $code, config()->integer('bunny-sidecar.two_factor.window', 1)) === true;
    }

    /**
     * Verify a recovery code and, when valid, replace it with a new one so
     * every code can only be used once.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $code = trim($code);

        foreach ($codes as $index => $candidate) {
            if (hash_equals($candidate, $code)) {
                $codes[$index] = $this->generateRecoveryCode();

                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * The otpauth:// URL encoded in the QR code.
     */
    public function qrCodeUrl(User $user): string
    {
        return $this->engine->getQRCodeUrl($this->sidecar->name(), $user->email, (string) $user->two_factor_secret);
    }

    /**
     * An inline SVG QR code for the user's pending or active secret.
     */
    public function qrCodeSvg(User $user): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(24, 24, 27))),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($this->qrCodeUrl($user));

        return trim(substr($svg, (int) strpos($svg, "\n") + 1));
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return array_map(fn (int $i): string => $this->generateRecoveryCode(), range(1, 8));
    }

    private function generateRecoveryCode(): string
    {
        return Str::random(10).'-'.Str::random(10);
    }
}
