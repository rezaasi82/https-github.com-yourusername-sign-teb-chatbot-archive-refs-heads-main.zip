<?php

namespace STMC_Chat\Integration;

use STMC_Chat\Core\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Optional, read-only bridge to SignTeb Medical Core. When that plugin is
 * active we pull live doctors / services / NAP from it; otherwise we fall
 * back to the chatbot's own manual settings ("decoupled but integratable").
 *
 * Nothing here hard-fails if Medical Core is absent or its API changes — all
 * lookups are guarded with function/post-type existence checks.
 */
class MedicalCoreBridge
{
    public function __construct(private Settings $settings)
    {
    }

    public function is_active(): bool
    {
        return post_type_exists('doctor') || defined('STMC_VERSION') || class_exists('\\STMC\\Core\\Plugin');
    }

    /**
     * @return array<int,array{name:string,specialty:string}>
     */
    public function doctors(int $limit = 12): array
    {
        if (! post_type_exists('doctor')) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => 'doctor',
            'post_status'    => 'publish',
            'numberposts'    => $limit,
            'suppress_filters' => false,
        ]);

        $out = [];
        foreach ($posts as $p) {
            $out[] = [
                'name'      => get_the_title($p),
                'specialty' => (string) (get_post_meta($p->ID, 'specialty', true) ?: ''),
            ];
        }
        return $out;
    }

    /**
     * Live services with optional price. Falls back to manual settings text.
     *
     * @return array<int,array{name:string,price:string}>
     */
    public function services(int $limit = 20): array
    {
        $out = [];

        foreach (['service', 'medical_service'] as $cpt) {
            if (! post_type_exists($cpt)) {
                continue;
            }
            $posts = get_posts([
                'post_type'   => $cpt,
                'post_status' => 'publish',
                'numberposts' => $limit,
            ]);
            foreach ($posts as $p) {
                $price = (string) (get_post_meta($p->ID, 'price', true) ?: get_post_meta($p->ID, 'service_price', true) ?: '');
                $out[] = ['name' => get_the_title($p), 'price' => $price];
            }
            if ($out) {
                return $out;
            }
        }

        return $this->manual_services();
    }

    /**
     * @return array{clinic_name:string,specialty:string,phone:string,whatsapp:string,address:string,booking_url:string}
     */
    public function nap(): array
    {
        // Prefer Medical Core settings when available via its documented filter.
        $core = apply_filters('stmc_get_clinic_nap', []);
        if (is_array($core) && ! empty($core)) {
            return wp_parse_args($core, $this->fallback_nap());
        }
        return $this->fallback_nap();
    }

    private function fallback_nap(): array
    {
        return [
            'clinic_name' => (string) $this->settings->get('clinic_name', get_bloginfo('name')),
            'specialty'   => (string) $this->settings->get('specialty', ''),
            'phone'       => (string) $this->settings->get('phone', ''),
            'whatsapp'    => (string) $this->settings->get('whatsapp', ''),
            'address'     => (string) $this->settings->get('address', ''),
            'booking_url' => (string) $this->settings->get('booking_url', ''),
        ];
    }

    /**
     * @return array<int,array{name:string,price:string}>
     */
    private function manual_services(): array
    {
        $raw = (string) $this->settings->get('manual_services', '');
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $out[] = ['name' => $parts[0], 'price' => $parts[1] ?? ''];
        }
        return $out;
    }
}
