<?php

namespace App\Domain\Licensing;

use App\Models\License;

/**
 * Generates keys in the form {PREFIX}-XXXX-XXXX-XXXX, e.g. MED-7KQ2-9XWM-4RTZ.
 *
 * The alphabet excludes visually ambiguous characters (0/O, 1/I/L) because doctors
 * type these keys by hand over the phone with support. The final character of the
 * last segment is a checksum so obviously mistyped keys are rejected before any
 * database lookup (cheap brute-force / typo filter).
 */
class LicenseKeyGenerator
{
    public function generate(string $productPrefix): string
    {
        $alphabet = config('slm.license.alphabet');
        $segments = (int) config('slm.license.segments');
        $length = (int) config('slm.license.segment_length');

        do {
            $body = [];
            for ($s = 0; $s < $segments; $s++) {
                $chars = '';
                for ($i = 0; $i < $length; $i++) {
                    $chars .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $body[] = $chars;
            }

            $key = strtoupper($productPrefix).'-'.implode('-', $body);
            $key = substr($key, 0, -1).$this->checksumChar(substr($key, 0, -1));
        } while (License::where('license_key', $key)->exists());

        return $key;
    }

    public function isWellFormed(string $key): bool
    {
        if (! preg_match('/^[A-Z0-9]{3,5}(-[A-Z0-9]{4}){3}$/', $key)) {
            return false;
        }

        return substr($key, -1) === $this->checksumChar(substr($key, 0, -1));
    }

    private function checksumChar(string $input): string
    {
        $alphabet = config('slm.license.alphabet');
        $sum = 0;
        foreach (str_split($input) as $i => $char) {
            $sum += ord($char) * ($i + 1);
        }

        return $alphabet[$sum % strlen($alphabet)];
    }
}
