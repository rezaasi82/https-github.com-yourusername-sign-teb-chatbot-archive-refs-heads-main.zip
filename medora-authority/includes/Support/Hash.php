<?php

declare(strict_types=1);

namespace Medora\Authority\Support;

use Medora\Authority\Core\Options;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Salted, one-way hashing for anything that could identify a visitor.
 *
 * Medora never stores raw IP addresses or user agents. The salt is generated
 * per install, so hashes are not comparable across sites and a leaked table is
 * not a rainbow-table target.
 */
final class Hash
{
    public function __construct(private readonly Options $options)
    {
    }

    public function pseudonymize(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return hash_hmac('sha256', $value, $this->salt());
    }

    /**
     * Hash of the visitor's IP, resolved through the proxy headers WordPress
     * itself trusts. Returns an empty string when no address is available.
     */
    public function visitorIp(): string
    {
        $address = $this->rawIp();

        return $address === '' ? '' : $this->pseudonymize($address);
    }

    public function rawIp(): string
    {
        $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($candidates as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $raw = sanitize_text_field(wp_unslash((string) $_SERVER[$key]));

            // X-Forwarded-For may carry a chain; the client is the first entry.
            foreach (explode(',', $raw) as $part) {
                $candidate = trim($part);

                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private function salt(): string
    {
        $salt = $this->options->getString('install_hash');

        if ($salt === '') {
            $salt = wp_generate_password(32, false, false);
            $this->options->set('install_hash', $salt);
        }

        return $salt . wp_salt('nonce');
    }
}
