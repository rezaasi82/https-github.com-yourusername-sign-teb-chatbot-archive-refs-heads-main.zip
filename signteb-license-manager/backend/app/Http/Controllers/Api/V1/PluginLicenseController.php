<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Licensing\Exceptions\LicensingException;
use App\Domain\Licensing\LicenseKeyGenerator;
use App\Domain\Licensing\LicenseService;
use App\Domain\Licensing\ResponseSigner;
use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoints consumed by the SignTeb WordPress SDK:
 * POST /api/v1/plugin/activate | validate | deactivate
 *
 * All responses are HMAC-signed with the product secret (see ResponseSigner).
 * Rate limited per IP and per license key (routes/api.php).
 */
class PluginLicenseController extends Controller
{
    public function __construct(
        private readonly LicenseService $licenses,
        private readonly LicenseKeyGenerator $keys,
        private readonly ResponseSigner $signer,
    ) {}

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:25'],
            'domain' => ['required', 'string', 'max:255'],
            'product' => ['required', 'string', 'max:64'],
            'site_url' => ['nullable', 'url', 'max:255'],
            'sdk_version' => ['nullable', 'string', 'max:20'],
            'wp_version' => ['nullable', 'string', 'max:20'],
            'php_version' => ['nullable', 'string', 'max:20'],
            'fingerprint' => ['nullable', 'string', 'size:64'],
        ]);

        $license = $this->resolveLicense($data['license_key'], $data['product']);

        if (! $license) {
            return $this->unsignedError('invalid_license', 'License key not found for this product.', 404);
        }

        try {
            $this->licenses->activate($license, $data['domain'], [
                ...$data,
                'ip' => $request->ip(),
            ]);
        } catch (LicensingException $e) {
            return $this->unsignedError($e->errorCode(), $e->getMessage(), 422);
        }

        return response()->json($this->signer->sign(
            $this->licenses->validate($license, $data['domain']),
            $license->product,
        ));
    }

    public function validateLicense(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:25'],
            'domain' => ['required', 'string', 'max:255'],
            'product' => ['required', 'string', 'max:64'],
        ]);

        $license = $this->resolveLicense($data['license_key'], $data['product']);

        if (! $license) {
            return $this->unsignedError('invalid_license', 'License key not found for this product.', 404);
        }

        return response()->json($this->signer->sign(
            $this->licenses->validate($license, $data['domain']),
            $license->product,
        ));
    }

    public function deactivate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:25'],
            'domain' => ['required', 'string', 'max:255'],
            'product' => ['required', 'string', 'max:64'],
        ]);

        $license = $this->resolveLicense($data['license_key'], $data['product']);

        if (! $license) {
            return $this->unsignedError('invalid_license', 'License key not found for this product.', 404);
        }

        $this->licenses->deactivate($license, $data['domain']);

        return response()->json($this->signer->sign(['deactivated' => true], $license->product));
    }

    private function resolveLicense(string $key, string $productSlug): ?License
    {
        // Checksum check rejects typos/brute force before touching the database.
        if (! $this->keys->isWellFormed($key)) {
            return null;
        }

        return License::where('license_key', $key)
            ->whereHas('product', fn ($q) => $q->where('slug', $productSlug))
            ->with(['product', 'plan'])
            ->first();
    }

    private function unsignedError(string $code, string $message, int $status): JsonResponse
    {
        // Errors carry no entitlement, so they don't need product-secret signing.
        return response()->json(['error' => $code, 'message' => $message], $status);
    }
}
