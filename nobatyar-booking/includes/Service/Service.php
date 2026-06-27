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
    public int $capacity_min;
    public int $capacity_max;
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
        $service->capacity_min     = isset($row['capacity_min']) ? max(1, (int) $row['capacity_min']) : 1;
        $service->capacity_max     = isset($row['capacity_max']) ? max(1, (int) $row['capacity_max']) : 1;
        $service->is_active        = (bool) $row['is_active'];

        return $service;
    }

    public function is_group_bookable(): bool
    {
        return $this->capacity_max > 1;
    }
}
