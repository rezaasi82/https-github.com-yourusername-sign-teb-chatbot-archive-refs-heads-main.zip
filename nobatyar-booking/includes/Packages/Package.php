<?php

namespace Nobatyar\Packages;

if (! defined('ABSPATH')) {
    exit;
}

class Package
{
    public int $id;
    public int $service_id;
    public string $name;
    public int $session_count;
    public float $price;
    public ?int $validity_days;
    public bool $is_active;

    public static function from_row(array $row): self
    {
        $package = new self();
        $package->id            = (int) $row['id'];
        $package->service_id    = (int) $row['service_id'];
        $package->name          = $row['name'];
        $package->session_count = (int) $row['session_count'];
        $package->price         = (float) $row['price'];
        $package->validity_days = isset($row['validity_days']) ? (int) $row['validity_days'] : null;
        $package->is_active     = (bool) $row['is_active'];
        return $package;
    }
}
