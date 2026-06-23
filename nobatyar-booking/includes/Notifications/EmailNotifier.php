<?php

namespace Nobatyar\Notifications;

if (! defined('ABSPATH')) {
    exit;
}

class EmailNotifier
{
    public function send(string $to, string $subject, string $message): bool
    {
        if ($to === '' || ! is_email($to)) {
            return false;
        }

        return wp_mail($to, $subject, $message, ['Content-Type: text/plain; charset=UTF-8']);
    }
}
