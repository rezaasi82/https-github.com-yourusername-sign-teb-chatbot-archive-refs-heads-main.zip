#!/usr/bin/env bash
#
# Install the WordPress test library and a scratch database.
#
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
# Example:
#   bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
#
# Safe to re-run: existing downloads are reused and the database is only
# created when absent.

set -euo pipefail

if [ $# -lt 3 ]; then
    echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]" >&2
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}
SKIP_DB_CREATE=${6:-false}

WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}

download() {
    if command -v curl >/dev/null 2>&1; then
        curl -sSL "$1" -o "$2"
    elif command -v wget >/dev/null 2>&1; then
        wget -nv -O "$2" "$1"
    else
        echo "Neither curl nor wget is available." >&2
        exit 1
    fi
}

resolve_version() {
    if [[ $WP_VERSION == "latest" ]]; then
        download https://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
        WP_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | head -1 | sed 's/.*"version":"//')
    fi

    echo "Using WordPress ${WP_VERSION}"
}

install_wp() {
    if [ -d "$WP_CORE_DIR" ]; then
        echo "WordPress already present at ${WP_CORE_DIR}"
        return
    fi

    mkdir -p "$WP_CORE_DIR"

    local archive="/tmp/wordpress-${WP_VERSION}.tar.gz"

    if [[ $WP_VERSION == "nightly" || $WP_VERSION == "trunk" ]]; then
        download https://wordpress.org/nightly-builds/wordpress-latest.zip /tmp/wordpress-nightly.zip
        unzip -q /tmp/wordpress-nightly.zip -d /tmp/
        mv /tmp/wordpress/* "$WP_CORE_DIR"
    else
        download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" "$archive"
        tar --strip-components=1 -zxmf "$archive" -C "$WP_CORE_DIR"
    fi

    download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php "${WP_CORE_DIR}/wp-content/db.php"
}

install_test_suite() {
    # The test library lives in the develop repository, not in the release tarball.
    if [ ! -d "$WP_TESTS_DIR" ]; then
        mkdir -p "$WP_TESTS_DIR"

        local branch="tags/${WP_VERSION}"
        if [[ $WP_VERSION == "nightly" || $WP_VERSION == "trunk" ]]; then
            branch="trunk"
        fi

        svn co --quiet "https://develop.svn.wordpress.org/${branch}/tests/phpunit/includes/" "${WP_TESTS_DIR}/includes"
        svn co --quiet "https://develop.svn.wordpress.org/${branch}/tests/phpunit/data/" "${WP_TESTS_DIR}/data"
    fi

    if [ -f "${WP_TESTS_DIR}/wp-tests-config.php" ]; then
        return
    fi

    download https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php "${WP_TESTS_DIR}/wp-tests-config.php"

    # BSD and GNU sed disagree about -i, so write through a temp file instead.
    local config="${WP_TESTS_DIR}/wp-tests-config.php"
    local tmp="${config}.tmp"

    sed \
        -e "s:dirname( __FILE__ ) . '/src/':'${WP_CORE_DIR}/':" \
        -e "s/youremptytestdbnamehere/${DB_NAME}/" \
        -e "s/yourusernamehere/${DB_USER}/" \
        -e "s/yourpasswordhere/${DB_PASS}/" \
        -e "s|localhost|${DB_HOST}|" \
        "$config" > "$tmp"

    mv "$tmp" "$config"
}

create_db() {
    if [ "$SKIP_DB_CREATE" = "true" ]; then
        return
    fi

    local args=(--user="$DB_USER" --host="$DB_HOST")

    if [ -n "$DB_PASS" ]; then
        args+=(--password="$DB_PASS")
    fi

    # `--force` so a pre-existing database is not an error on re-runs.
    mysqladmin create "$DB_NAME" "${args[@]}" 2>/dev/null || \
        echo "Database ${DB_NAME} already exists or could not be created; continuing."
}

resolve_version
install_wp
install_test_suite
create_db

echo "Ready. Run: composer test:integration"
