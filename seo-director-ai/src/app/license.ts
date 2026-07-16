import { useQuery } from '@tanstack/react-query';
import { api, type LicenseStatusResponse } from '../api/client';

/**
 * Shared license/feature snapshot. The whole SPA reads feature flags from this
 * single query so PRO-gating stays consistent and only costs one request.
 */
export function useLicense() {
  const query = useQuery<LicenseStatusResponse>({
    queryKey: ['license'],
    queryFn: api.licenseStatus,
    staleTime: 300_000,
  });

  const features = query.data?.features ?? {};

  return {
    ...query,
    edition: query.data?.edition ?? 'starter',
    license: query.data?.license ?? null,
    isLite: query.data?.is_lite ?? false,
    upgradeUrl: query.data?.upgrade_url ?? 'https://seodirector.app/pricing',
    features,
    allows: (feature: string): boolean => Boolean(features[feature]),
  };
}
