<?php

namespace App\Domain\Licensing;

use Illuminate\Support\Str;

class DomainNormalizer
{
    /**
     * Lowercase, strip scheme/www/port/path so "https://WWW.Clinic.ae:443/fa/"
     * and "clinic.ae" count as the same activation.
     */
    public function normalize(string $input): string
    {
        $host = parse_url(trim($input), PHP_URL_HOST) ?? trim($input);
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return rtrim(explode(':', $host)[0], '/');
    }

    public function hash(string $domain): string
    {
        return hash('sha256', $this->normalize($domain));
    }

    /**
     * Development/staging domains don't consume activation slots.
     */
    public function isDevDomain(string $domain): bool
    {
        $domain = $this->normalize($domain);

        foreach (config('slm.dev_domain_patterns') as $pattern) {
            if (Str::is($pattern, $domain)) {
                return true;
            }
        }

        return filter_var($domain, FILTER_VALIDATE_IP) !== false
            && ! filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
