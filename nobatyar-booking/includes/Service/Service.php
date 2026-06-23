<?php

namespace Nobatyar\Service;

if (! defined('ABSPATH')) {
    exit;
}

class Service
{
    public int $id;
    public string $name;
    public int $duration_minutes;
    public int $buffer_minutes;
    public ?float $price;
    public ?float $deposit_amount;
    public bool $is_active;

    public static function from_row(array $row): self
    {
        $service = new self();

        $service->id               = (int) $row['id'];
        $service->name             = $row['name'];
        $service->duration_minutes = (int) $row['duration_minutes'];
        $service->buffer_minutes   = (int) $row['buffer_minutes'];
        $service->price            = isset($row['price']) ? (float) $row['price'] : null;
        $service->deposit_amount   = isset($row['deposit_amount']) ? (float) $row['deposit_amount'] : null;
        $service->is_active        = (bool) $row['is_active'];

        return $service;
    }
}
