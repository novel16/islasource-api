<?php

namespace App\Services;

use App\Mail\QuoteEmailVerificationMail;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class QuoteOtpService
{
    public const TTL_SECONDS = 600;

    public const COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public const MIN_ELAPSED_MS = 1200;

    public function issueCode(string $email, ?string $fixedCode = null): string
    {
        $normalized = $this->normalizeEmail($email);
        $code = $fixedCode ?? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(
            $this->codeKey($normalized),
            [
                'hash' => $this->hashCode($normalized, $code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
            ],
            self::TTL_SECONDS,
        );

        return $code;
    }

    /** @return array{cooldown_seconds: int, expires_in_seconds: int} */
    public function sendCode(string $customerName, string $email, ?string $honeypot, int $elapsedMs): array
    {
        $honeypotFilled = trim((string) $honeypot) !== '';

        if ($honeypotFilled || $elapsedMs < self::MIN_ELAPSED_MS) {
            Log::info('Quote OTP suppressed by bot filter.', [
                'honeypot_filled' => $honeypotFilled,
                'elapsed_ms' => $elapsedMs,
            ]);

            return [
                'cooldown_seconds' => self::COOLDOWN_SECONDS,
                'expires_in_seconds' => self::TTL_SECONDS,
            ];
        }

        $normalized = $this->normalizeEmail($email);
        $cooldownUntil = Cache::get($this->cooldownKey($normalized));
        $nowTimestamp = now()->getTimestamp();

        if (is_int($cooldownUntil) && $cooldownUntil > $nowTimestamp) {
            $retryAfter = max(1, $cooldownUntil - $nowTimestamp);

            throw new ThrottleRequestsException(
                "Please wait {$retryAfter} seconds before requesting a new verification code.",
                null,
                ['Retry-After' => $retryAfter],
            );
        }

        $code = $this->issueCode($normalized);
        Cache::put(
            $this->cooldownKey($normalized),
            $nowTimestamp + self::COOLDOWN_SECONDS,
            self::COOLDOWN_SECONDS,
        );

        try {
            Mail::to($normalized)->send(new QuoteEmailVerificationMail(
                customerName: trim($customerName),
                code: $code,
                validMinutes: (int) (self::TTL_SECONDS / 60),
            ));
        } catch (Throwable $exception) {
            $this->consume($normalized);
            Log::error('Failed to send quote verification code email.', [
                'email_hash' => hash('sha256', $normalized),
                'error' => $exception->getMessage(),
            ]);

            throw new HttpException(
                503,
                'We could not send a verification code right now. Please try again shortly.',
            );
        }

        return [
            'cooldown_seconds' => self::COOLDOWN_SECONDS,
            'expires_in_seconds' => self::TTL_SECONDS,
        ];
    }

    public function verify(string $email, string $code): void
    {
        $normalized = $this->normalizeEmail($email);
        $entry = Cache::get($this->codeKey($normalized));

        if (! is_array($entry) || ! is_string($entry['hash'] ?? null)) {
            throw ValidationException::withMessages([
                'verification_code' => 'Your verification code has expired or was not requested. Please request a new 6-digit code.',
            ]);
        }

        $expected = $this->hashCode($normalized, trim($code));

        if (! hash_equals($entry['hash'], $expected)) {
            $attempts = ((int) ($entry['attempts'] ?? 0)) + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->consume($normalized);

                throw ValidationException::withMessages([
                    'verification_code' => 'Too many incorrect attempts. Please request a new 6-digit code.',
                ]);
            }

            $remainingTtl = max(
                1,
                ((int) ($entry['expires_at'] ?? (now()->getTimestamp() + self::TTL_SECONDS))) - now()->getTimestamp(),
            );

            Cache::put(
                $this->codeKey($normalized),
                [...$entry, 'attempts' => $attempts],
                $remainingTtl,
            );

            throw ValidationException::withMessages([
                'verification_code' => 'Invalid 6-digit verification code. Please check your email and try again.',
            ]);
        }
    }

    public function consume(string $email): void
    {
        $normalized = $this->normalizeEmail($email);
        Cache::forget($this->codeKey($normalized));
        Cache::forget($this->cooldownKey($normalized));
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function hashCode(string $normalizedEmail, string $code): string
    {
        return hash_hmac('sha256', "{$normalizedEmail}|{$code}", (string) config('app.key'));
    }

    private function codeKey(string $normalizedEmail): string
    {
        return 'quote_otp:code:'.hash('sha256', $normalizedEmail);
    }

    private function cooldownKey(string $normalizedEmail): string
    {
        return 'quote_otp:cooldown:'.hash('sha256', $normalizedEmail);
    }
}
