<?php

namespace Nobatyar\Provider;

if (! defined('ABSPATH')) {
    exit;
}

class Provider
{
    public int $id;
    public ?int $user_id;
    public string $name;
    public ?string $label_override;
    public ?string $license_field;
    public ?int $avatar_id;
    public bool $is_active;
    public int $sort_order;

    public static function from_row(array $row): self
    {
        $provider = new self();

        $provider->id             = (int) $row['id'];
        $provider->user_id        = isset($row['user_id']) ? (int) $row['user_id'] : null;
        $provider->name           = $row['name'];
        $provider->label_override = $row['label_override'] ?? null;
        $provider->license_field  = $row['license_field'] ?? null;
        $provider->avatar_id      = isset($row['avatar_id']) ? (int) $row['avatar_id'] : null;
        $provider->is_active      = (bool) $row['is_active'];
        $provider->sort_order     = (int) $row['sort_order'];

        return $provider;
    }
}
