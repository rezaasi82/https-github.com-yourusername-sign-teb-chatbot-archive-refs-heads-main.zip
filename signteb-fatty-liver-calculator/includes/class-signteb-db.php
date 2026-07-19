<?php
/**
 * Database layer — creates and manages the wp_signteb_liver_leads table.
 *
 * @package SignTeb_Fatty_Liver_Calculator
 */
defined( 'ABSPATH' ) || exit;

class SignTeb_Liver_DB {

    /* ── Table Creation ───────────────────────────────────────────────────── */

    /**
     * Called on plugin activation via register_activation_hook().
     * Uses dbDelta() so it is safe to run on upgrades too.
     */
    public static function create_table() {
        global $wpdb;

        $table           = $wpdb->prefix . SIGNTEB_LIVER_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        /*
         * Columns:
         *  id               – Auto-increment PK
         *  full_name        – Patient's full name (sanitised)
         *  phone            – Iranian mobile (09XXXXXXXXX)
         *  test_type        – 'clinical' | 'lifestyle'
         *  input_metrics    – JSON blob of all input values
         *  calculated_score – FLI (0–100) or lifestyle score (0–16)
         *  result_grade     – 'grade_0' | 'grade_1' | 'grade_2' | 'grade_3'
         *  ip_address       – Client IP (anonymised if GDPR mode enabled)
         *  created_at       – UTC timestamp
         */
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id               BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
            full_name        VARCHAR(200)         NOT NULL,
            phone            VARCHAR(20)          NOT NULL,
            test_type        VARCHAR(20)          NOT NULL DEFAULT 'clinical',
            input_metrics    LONGTEXT             NOT NULL,
            calculated_score DECIMAL(6,2)         NOT NULL DEFAULT 0.00,
            result_grade     VARCHAR(30)          NOT NULL DEFAULT 'grade_0',
            ip_address       VARCHAR(45)              NULL DEFAULT NULL,
            created_at       DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_phone      (phone),
            KEY idx_grade      (result_grade),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'signteb_liver_db_version', SIGNTEB_LIVER_VERSION );
    }

    /* ── Insert Lead ──────────────────────────────────────────────────────── */

    /**
     * Persist a new lead record.
     *
     * @param array $data {
     *     @type string $full_name
     *     @type string $phone
     *     @type string $test_type  'clinical'|'lifestyle'
     *     @type array  $metrics    Associative array of input values
     *     @type float  $score      Computed FLI or lifestyle score
     *     @type string $grade      'grade_0'..'grade_3'
     * }
     * @return int|false  Inserted row ID, or false on failure.
     */
    public static function insert_lead( array $data ) {
        global $wpdb;

        $table = $wpdb->prefix . SIGNTEB_LIVER_TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->insert(
            $table,
            [
                'full_name'        => sanitize_text_field( $data['full_name'] ),
                'phone'            => sanitize_text_field( $data['phone'] ),
                'test_type'        => sanitize_key( $data['test_type'] ),
                'input_metrics'    => wp_json_encode( $data['metrics'] ),
                'calculated_score' => round( (float) $data['score'], 2 ),
                'result_grade'     => sanitize_key( $data['grade'] ),
                'ip_address'       => self::get_client_ip(),
            ],
            [ '%s', '%s', '%s', '%s', '%f', '%s', '%s' ]
        );

        return ( false === $result ) ? false : (int) $wpdb->insert_id;
    }

    /* ── Helpers ──────────────────────────────────────────────────────────── */

    /**
     * Return the best-available client IP address.
     * Falls back to REMOTE_ADDR which is always set.
     *
     * @return string
     */
    private static function get_client_ip() {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $candidates as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                // X-Forwarded-For may contain a comma-separated list; take the first.
                $raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                $ip  = trim( explode( ',', $raw )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
