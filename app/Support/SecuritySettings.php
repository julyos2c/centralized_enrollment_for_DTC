<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Validation\Rules\Password;

class SecuritySettings
{
    private static function integer(string $key, int $default, int $minimum, int $maximum): int
    {
        $value = Setting::get($key);
        return is_numeric($value) ? max($minimum, min($maximum, (int) $value)) : $default;
    }

    public static function passwordRules(bool $confirmed = true): array
    {
        $rule = Password::min(self::passwordMinimum());
        $extra = [];
        if (self::requiresSymbols()) {
            // Laravel 9's symbols() accepts whitespace. This setting requires punctuation or a symbol.
            $extra[] = function ($attribute, $value, $fail) {
                if (is_string($value) && ! preg_match('/[\p{S}\p{P}]/u', $value)) {
                    $fail('The '.$attribute.' must include at least one symbol (for example !, @, or #).');
                }
            };
        }
        return array_merge(['required', 'string', $rule], $extra, $confirmed ? ['confirmed'] : []);
    }

    public static function passwordMinimum(): int
    {
        return self::integer('min_password_length', 8, 8, 32);
    }

    public static function requiresSymbols(): bool
    {
        return Setting::get('require_special_chars', '0') === '1';
    }

    public static function sessionMinutes(): int
    {
        // Respect a shorter cookie/session lifetime as well as the admin policy.
        return min(max(1, (int) config('session.lifetime', 120)), self::integer('session_timeout', 60, 5, 1440));
    }

    public static function loginAttempts(): int
    {
        return self::integer('max_login_attempts', max(3, min(20, (int) config('services.login_rate_limit.per_identity_attempts', 5))), 3, 20);
    }
}
