# White label and SaaS deployment

## White label (Agency tier)

Enabling the White Label module lets an agency present the platform under their
own brand. Everything flows through `WhiteLabel\BrandingManager`:

| Key | Effect |
|---|---|
| `enabled` | master switch |
| `product_name` | plugin name on the Plugins screen and the dashboard title |
| `short_name` | admin menu label |
| `vendor_name`, `vendor_url` | authorship on the Plugins screen |
| `support_url` | where the plugin row links |
| `accent_color` | published as `--medora-accent`, picked up by the dashboard without a rebuild |
| `hide_vendor` | replaces Medora's authorship metadata entirely |

Set them in the settings UI, or force them from code so the customer cannot
change them:

```php
add_filter( 'medora_branding', function ( array $branding ): array {
    return array_merge( $branding, [
        'enabled'      => '1',
        'product_name' => 'Acme Authority',
        'short_name'   => 'Acme',
        'vendor_name'  => 'Acme Digital',
        'vendor_url'   => 'https://acme.example',
        'accent_color' => '#7c3aed',
        'hide_vendor'  => '1',
    ] );
} );
```

The accent colour is validated as a hex value before being written into a
`<style>` block — an unvalidated value there would be a stored-XSS vector.

## SaaS / multi-tenant deployment

The platform is designed so a host can own entitlement, configuration and
updates without forking.

### Entitlement from your own service

```php
// Skip the licence server entirely and grant a tier from your own billing.
add_filter( 'medora_license_tier', function (): string {
    return my_platform_plan_for_current_site(); // 'pro' | 'agency' | 'enterprise'
} );
```

Or keep the licence flow and point it at your own endpoint:

```php
add_filter( 'medora_license_endpoint', fn (): string => 'https://api.acme.example/v1/' );
add_filter( 'medora_update_endpoint', fn (): string => 'https://api.acme.example/v1/update' );
```

The licence server never learns the customer's domain — only a SHA-256 hash of
it. Implement `activate`, `deactivate`, `status`, `transfer` and `update`; the
client expects `{ tier, status, expires_at }`.

### Locking configuration

`medora_option` intercepts settings at read time, so a host can force a value
without writing to the database and without the customer being able to override
it:

```php
add_filter( 'medora_option', function ( $value, string $key ) {
    return match ( $key ) {
        'retention_days'     => 90,          // enforce the platform's data policy
        'embedding_provider' => 'openai',    // centrally provisioned
        default              => $value,
    };
}, 10, 2 );
```

### Centrally provisioned secrets

Set `MEDORA_EMBEDDING_API_KEY` as an environment variable across the fleet. It
takes precedence over both the constant and the database, so no tenant ever
holds the key and it never appears in a backup.

### Controlling the module surface

```php
add_filter( 'medora_module_classes', function ( array $classes ): array {
    // Withhold modules your plan tier does not include, or swap a core module
    // for your own implementation.
    return array_values( array_filter(
        $classes,
        fn ( string $class ) => $class !== \Medora\Authority\Vector\VectorModule::class
    ) );
} );
```

### Scaling the vector index

The built-in linear scan is honest about its ceiling: `GET /overview` reports
`vectors.needs_external_index` once a site passes ~20,000 chunks. At that point
delegate to a real vector database without touching calling code:

```php
add_filter( 'medora_vector_search', function ( $result, array $query, int $limit, array $args ) {
    return acme_vector_db_query( $query, $limit, $args );
}, 10, 4 );
```

### Draining queues at scale

WordPress cron is a poor scheduler on a large fleet. Run the worker from your
own supervisor instead:

```bash
wp medora queue --max=500 --url=https://tenant.example
```

The worker self-limits on wall time (20 s) and memory (80% of the PHP limit),
and recovers jobs whose worker died mid-run, so it is safe to invoke frequently
and in parallel across tenants.

### Closing the public API

Public knowledge endpoints are open by default because that is the product.
Where a tenant needs them closed:

```php
add_filter( 'medora_public_api_enabled', '__return_false' );
add_filter( 'medora_public_rate_limit', fn (): int => 30 );
```

## Multisite

Each site gets its own tables (`$wpdb->prefix` is resolved per site), its own
settings and its own install salt — so visitor hashes are not comparable across
tenants even within one network. Capabilities are per-site roles. Licence
binding uses the site's own `home_url()` host.
