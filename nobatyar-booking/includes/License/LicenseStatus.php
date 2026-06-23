<?php

namespace Nobatyar\License;

if (! defined('ABSPATH')) {
    exit;
}

class LicenseStatus
{
    public const ACTIVE   = 'active';
    public const GRACE    = 'grace';
    public const LOCKED   = 'locked';
    public const INACTIVE = 'inactive';
}
