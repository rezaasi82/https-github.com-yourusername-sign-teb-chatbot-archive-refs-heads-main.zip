<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Licensing\LicenseService;
use App\Domain\Licensing\ResponseSigner;
use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * GET /api/v1/plugin/check-update — update manifest, gated by license status.
 * GET /api/v1/plugin/download    — signed, short-lived artifact URL.
 */
class PluginUpdateController extends Controller
{
    public function __construct(
        private readonly LicenseService $licenses,
        private readonly ResponseSigner $signer,
    ) {}

    public function checkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:25'],
            'domain' => ['required', 'string', 'max:255'],
            'product' => ['required', 'string', 'max:64'],
            'version' => ['required', 'string', 'max:20'],
            'channel' => ['nullable', 'in:stable,beta'],
        ]);

        $license = License::where('license_key', $data['license_key'])
            ->whereHas('product', fn ($q) => $q->where('slug', $data['product']))
            ->with('product')
            ->first();

        if (! $license) {
            return response()->json(['error' => 'invalid_license'], 404);
        }

        $validation = $this->licenses->validate($license, $data['domain']);

        if (! $validation['valid']) {
            // Expired customers still see that an update exists — they just can't
            // download it. This is the renewal nudge, WP Rocket-style.
            return response()->json($this->signer->sign([
                'update_available' => false,
                'reason' => $validation['reason'],
            ], $license->product));
        }

        $latest = $license->product->latestVersion($data['channel'] ?? 'stable');

        if (! $latest || version_compare($latest->version, $data['version'], '<=')) {
            return response()->json($this->signer->sign(['update_available' => false], $license->product));
        }

        return response()->json($this->signer->sign([
            'update_available' => true,
            'version' => $latest->version,
            'release_notes' => $latest->release_notes,
            'requires_php' => $latest->min_php,
            'requires_wp' => $latest->min_wp,
            'sha256' => $latest->artifact_sha256,
            'download_url' => URL::temporarySignedRoute(
                'plugin.download',
                now()->addSeconds((int) config('slm.download_url_ttl_seconds')),
                ['version_id' => $latest->id, 'license_key' => $license->license_key],
            ),
        ], $license->product));
    }

    public function download(Request $request): mixed
    {
        abort_unless($request->hasValidSignature(), 403);

        $version = \App\Models\ProductVersion::findOrFail($request->query('version_id'));

        $license = License::where('license_key', $request->query('license_key'))
            ->where('product_id', $version->product_id)
            ->firstOrFail();

        abort_unless($license->status->isUsable(), 403, 'License is not active.');

        $version->increment('download_count');

        return Storage::disk(config('filesystems.default'))
            ->download($version->artifact_path, basename((string) $version->artifact_path));
    }
}
