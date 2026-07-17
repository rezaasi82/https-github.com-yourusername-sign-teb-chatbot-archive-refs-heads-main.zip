<?php

namespace App\Console\Commands;

use App\Domain\Licensing\LicenseService;
use App\Domain\Licensing\LicenseStatus;
use App\Models\License;
use Illuminate\Console\Command;

/**
 * Daily scheduler job (registered in routes/console.php):
 *   active + past expires_at      → grace  (plan-defined window, default 14 days)
 *   grace  + past grace_ends_at   → expired
 *
 * Uses the (status, expires_at) index — scans only rows that are due.
 */
class SweepLicenseExpiry extends Command
{
    protected $signature = 'slm:sweep-expiry';

    protected $description = 'Move overdue licenses into grace, and grace licenses past their window into expired.';

    public function handle(LicenseService $licenses): int
    {
        License::where('status', LicenseStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->eachById(function (License $license) use ($licenses) {
                $licenses->transition($license, LicenseStatus::Grace, 'grace_entered');
            });

        License::where('status', LicenseStatus::Grace)
            ->where('grace_ends_at', '<=', now())
            ->eachById(function (License $license) use ($licenses) {
                $licenses->transition($license, LicenseStatus::Expired, 'expired');
            });

        $this->info('Expiry sweep complete.');

        return self::SUCCESS;
    }
}
