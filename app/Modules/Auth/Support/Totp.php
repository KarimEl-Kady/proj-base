<?php

namespace App\Modules\Auth\Support;

/**
 * Minimal RFC 6238 TOTP implementation (SHA1, 6 digits, 30s period) —
 * compatible with Google Authenticator, 1Password, Authy, etc.
 * Dependency-free on purpose: the base stays light.
 */
class Totp
{
    protected const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    protected const PERIOD = 30;

    protected const DIGITS = 6;

    public static function generateSecret(int $bytes = 20): string
    {
        return static::base32Encode(random_bytes($bytes));
    }

    public static function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);
        $binary = pack('N2', 0, $counter); // 8-byte big-endian counter

        $hash = hash_hmac('sha1', $binary, static::base32Decode($secret), true);

        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3])
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a code, allowing ±$window periods of clock drift.
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $now = time();

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(static::code($secret, $now + ($i * self::PERIOD)), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * otpauth:// URI for QR codes in authenticator apps.
     */
    public static function uri(string $secret, string $email, ?string $issuer = null): string
    {
        $issuer = rawurlencode($issuer ?? config('project.name', config('app.name', 'Laravel')));
        $label = $issuer.':'.rawurlencode($email);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=".self::DIGITS.'&period='.self::PERIOD;
    }

    protected static function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    protected static function base32Decode(string $encoded): string
    {
        $bits = '';
        foreach (str_split(strtoupper($encoded)) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
