<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Single source of truth for the platform's physical schema.
 *
 * Table names are resolved through `name()` rather than interpolated at call
 * sites so multisite prefixes and `$wpdb->prefix` changes stay in one place.
 * Every DDL statement here is `dbDelta()`-compatible: two spaces after the
 * column name, `KEY` (never `INDEX`), and no backticks around table names.
 */
final class Tables
{
    public const PREFIX = 'mdra_';

    public const ENTITIES     = 'entities';
    public const RELATIONS    = 'entity_relations';
    public const ENTITY_INDEX = 'entity_index';
    public const VECTORS      = 'vectors';
    public const ANALYSIS     = 'analysis';
    public const PROMPT_PACKS = 'prompt_packs';
    public const CRAWLER_HITS = 'crawler_hits';
    public const CITATIONS    = 'citations';
    public const REFERRALS    = 'ai_referrals';
    public const AUDIT_LOG    = 'audit_log';
    public const JOBS         = 'jobs';

    /** @return list<string> */
    public static function allNames(): array
    {
        return [
            self::ENTITIES,
            self::RELATIONS,
            self::ENTITY_INDEX,
            self::VECTORS,
            self::ANALYSIS,
            self::PROMPT_PACKS,
            self::CRAWLER_HITS,
            self::CITATIONS,
            self::REFERRALS,
            self::AUDIT_LOG,
            self::JOBS,
        ];
    }

    /** Fully qualified table name, e.g. `wp_mdra_entities`. */
    public static function name(string $table): string
    {
        global $wpdb;

        return $wpdb->prefix . self::PREFIX . $table;
    }

    /**
     * @return list<string> dbDelta-ready CREATE TABLE statements.
     */
    public static function definitions(): array
    {
        global $wpdb;

        $collate = $wpdb->get_charset_collate();

        $sql = [];

        // Every indexable object in the site graph — a post, a term, a user or
        // an externally imported concept — is normalised into one entity row.
        $sql[] = 'CREATE TABLE ' . self::name(self::ENTITIES) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_uid VARCHAR(64) NOT NULL,
            entity_type VARCHAR(64) NOT NULL DEFAULT 'Thing',
            name VARCHAR(191) NOT NULL,
            canonical_name VARCHAR(191) NOT NULL DEFAULT '',
            description TEXT NULL,
            object_type VARCHAR(32) NOT NULL DEFAULT 'virtual',
            object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            permalink VARCHAR(255) NOT NULL DEFAULT '',
            same_as LONGTEXT NULL,
            meta LONGTEXT NULL,
            authority_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            confidence DECIMAL(4,3) NOT NULL DEFAULT 0.000,
            occurrences INT UNSIGNED NOT NULL DEFAULT 0,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY entity_uid (entity_uid),
            KEY entity_type (entity_type),
            KEY object_lookup (object_type,object_id),
            KEY authority_score (authority_score),
            KEY canonical_name (canonical_name)
        ) {$collate};";

        // Directed, weighted triples: subject --predicate--> object.
        $sql[] = 'CREATE TABLE ' . self::name(self::RELATIONS) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subject_id BIGINT UNSIGNED NOT NULL,
            predicate VARCHAR(64) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            weight DECIMAL(5,4) NOT NULL DEFAULT 1.0000,
            source VARCHAR(32) NOT NULL DEFAULT 'inferred',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY triple (subject_id,predicate,object_id),
            KEY subject_id (subject_id),
            KEY object_id (object_id),
            KEY predicate (predicate)
        ) {$collate};";

        // Which entity appears on which piece of content, and how salient it is.
        $sql[] = 'CREATE TABLE ' . self::name(self::ENTITY_INDEX) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(32) NOT NULL DEFAULT 'post',
            object_id BIGINT UNSIGNED NOT NULL,
            occurrences INT UNSIGNED NOT NULL DEFAULT 1,
            salience DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            first_seen_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY entity_object (entity_id,object_type,object_id),
            KEY object_lookup (object_type,object_id),
            KEY salience (salience)
        ) {$collate};";

        // Packed float32 embeddings. One row per content chunk.
        $sql[] = 'CREATE TABLE ' . self::name(self::VECTORS) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type VARCHAR(32) NOT NULL DEFAULT 'post',
            object_id BIGINT UNSIGNED NOT NULL,
            chunk_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            provider VARCHAR(64) NOT NULL DEFAULT 'hashing',
            dimensions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            embedding LONGBLOB NOT NULL,
            magnitude DOUBLE NOT NULL DEFAULT 0,
            excerpt TEXT NULL,
            content_hash CHAR(40) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY chunk (object_type,object_id,chunk_index,provider),
            KEY object_lookup (object_type,object_id),
            KEY provider (provider)
        ) {$collate};";

        // Cached authority analysis, keyed by content hash so a re-analysis is
        // skipped entirely when the content has not changed.
        $sql[] = 'CREATE TABLE ' . self::name(self::ANALYSIS) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type VARCHAR(32) NOT NULL DEFAULT 'post',
            object_id BIGINT UNSIGNED NOT NULL,
            overall_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            components LONGTEXT NULL,
            deductions LONGTEXT NULL,
            content_hash CHAR(40) NOT NULL DEFAULT '',
            analyzed_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY object_lookup (object_type,object_id),
            KEY overall_score (overall_score),
            KEY analyzed_at (analyzed_at)
        ) {$collate};";

        // Generated, LLM-facing representation of a page.
        $sql[] = 'CREATE TABLE ' . self::name(self::PROMPT_PACKS) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type VARCHAR(32) NOT NULL DEFAULT 'post',
            object_id BIGINT UNSIGNED NOT NULL,
            summary TEXT NULL,
            canonical_answer TEXT NULL,
            questions LONGTEXT NULL,
            facts LONGTEXT NULL,
            context_window LONGTEXT NULL,
            content_hash CHAR(40) NOT NULL DEFAULT '',
            generated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY object_lookup (object_type,object_id)
        ) {$collate};";

        // Raw AI crawler access log. IPs are hashed, never stored in the clear.
        $sql[] = 'CREATE TABLE ' . self::name(self::CRAWLER_HITS) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            crawler_slug VARCHAR(64) NOT NULL,
            vendor VARCHAR(64) NOT NULL DEFAULT '',
            request_uri VARCHAR(255) NOT NULL DEFAULT '',
            object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
            decision VARCHAR(16) NOT NULL DEFAULT 'allow',
            ip_hash CHAR(64) NOT NULL DEFAULT '',
            hit_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY crawler_slug (crawler_slug),
            KEY hit_at (hit_at),
            KEY crawler_day (crawler_slug,hit_at)
        ) {$collate};";

        $sql[] = 'CREATE TABLE ' . self::name(self::CITATIONS) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type VARCHAR(32) NOT NULL DEFAULT 'post',
            object_id BIGINT UNSIGNED NOT NULL,
            doi VARCHAR(191) NOT NULL DEFAULT '',
            pmid VARCHAR(32) NOT NULL DEFAULT '',
            title TEXT NULL,
            authors LONGTEXT NULL,
            container VARCHAR(191) NOT NULL DEFAULT '',
            published_year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            url VARCHAR(255) NOT NULL DEFAULT '',
            evidence_level VARCHAR(16) NOT NULL DEFAULT '',
            quality_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            raw LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY object_lookup (object_type,object_id),
            KEY doi (doi),
            KEY pmid (pmid)
        ) {$collate};";

        // Sessionless, cookie-free AI referral log.
        $sql[] = 'CREATE TABLE ' . self::name(self::REFERRALS) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_slug VARCHAR(64) NOT NULL,
            referrer_host VARCHAR(191) NOT NULL DEFAULT '',
            landing_uri VARCHAR(255) NOT NULL DEFAULT '',
            object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            visitor_hash CHAR(64) NOT NULL DEFAULT '',
            occurred_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY source_slug (source_slug),
            KEY occurred_at (occurred_at),
            KEY object_id (object_id)
        ) {$collate};";

        $sql[] = 'CREATE TABLE ' . self::name(self::AUDIT_LOG) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            action VARCHAR(64) NOT NULL,
            object_type VARCHAR(32) NOT NULL DEFAULT '',
            object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ip_hash CHAR(64) NOT NULL DEFAULT '',
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$collate};";

        // Durable background queue. Cron drains it; a long-running worker can
        // drain it faster without schema changes.
        $sql[] = 'CREATE TABLE ' . self::name(self::JOBS) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            queue VARCHAR(64) NOT NULL DEFAULT 'default',
            handler VARCHAR(191) NOT NULL,
            payload LONGTEXT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL,
            reserved_at DATETIME NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY claim (status,available_at),
            KEY queue (queue),
            KEY handler (handler)
        ) {$collate};";

        return $sql;
    }
}
