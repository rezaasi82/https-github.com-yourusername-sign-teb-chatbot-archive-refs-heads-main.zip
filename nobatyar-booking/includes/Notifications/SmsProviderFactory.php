<?php

namespace Nobatyar\Notifications;

use Nobatyar\Notifications\Providers\KavehnegarProvider;
use Nobatyar\Notifications\Providers\MelipayamakProvider;
use Nobatyar\Notifications\WhatsApp\WhatsAppProvider;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds the active notification channels from the `nobatyar_sms_settings`
 * option: exactly one primary SMS gateway (Kavehnegar or Melipayamak, the
 * two providers behind SmsProviderInterface per the roadmap), plus the
 * experimental WhatsApp channel when explicitly enabled and configured.
 */
class SmsProviderFactory
{
    /**
     * @return array<int,SmsProviderInterface>
     */
    public static function active_providers(): array
    {
        $settings  = self::settings();
        $providers = [];

        if ($settings['provider'] === 'kavehnegar' && ! empty($settings['kavehnegar']['api_key'])) {
            $providers[] = new KavehnegarProvider($settings['kavehnegar']['api_key'], $settings['kavehnegar']['sender']);
        } elseif ($settings['provider'] === 'melipayamak' && ! empty($settings['melipayamak']['username'])) {
            $providers[] = new MelipayamakProvider(
                $settings['melipayamak']['username'],
                $settings['melipayamak']['password'],
                $settings['melipayamak']['sender']
            );
        }

        if (! empty($settings['whatsapp']['enabled']) && ! empty($settings['whatsapp']['access_token'])) {
            $providers[] = new WhatsAppProvider($settings['whatsapp']['phone_number_id'], $settings['whatsapp']['access_token']);
        }

        return $providers;
    }

    /**
     * @return array{provider:string,kavehnegar:array{api_key:string,sender:string},melipayamak:array{username:string,password:string,sender:string},whatsapp:array{enabled:bool,phone_number_id:string,access_token:string}}
     */
    public static function settings(): array
    {
        $defaults = [
            'provider'    => '',
            'kavehnegar'  => ['api_key' => '', 'sender' => ''],
            'melipayamak' => ['username' => '', 'password' => '', 'sender' => ''],
            'whatsapp'    => ['enabled' => false, 'phone_number_id' => '', 'access_token' => ''],
        ];

        $stored = get_option('nobatyar_sms_settings', []);

        return apply_filters('nobatyar_sms_settings', array_replace_recursive($defaults, $stored));
    }
}
