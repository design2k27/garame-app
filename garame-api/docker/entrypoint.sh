#!/bin/sh
set -eu

cd /app

log() {
    printf '[entrypoint] %s\n' "$1"
}

require_env() {
    var_name="$1"
    eval "var_value=\${$var_name:-}"

    if [ -z "$var_value" ]; then
        printf '[entrypoint] Missing required environment variable: %s\n' "$var_name" >&2
        exit 1
    fi
}

file_from_env() {
    var_name="$1"
    eval "file_path=\${$var_name:-}"

    if [ -n "$file_path" ] && [ ! -f "$file_path" ]; then
        printf '[entrypoint] File configured by %s not found: %s\n' "$var_name" "$file_path" >&2
        exit 1
    fi
}

ensure_parent_dir() {
    file_path="$1"
    parent_dir="$(dirname "$file_path")"
    mkdir -p "$parent_dir"
}

ensure_jwt_keys() {
    require_env JWT_SECRET_KEY
    require_env JWT_PUBLIC_KEY

    private_key_path="$JWT_SECRET_KEY"
    public_key_path="$JWT_PUBLIC_KEY"
    passphrase="${JWT_PASSPHRASE:-}"

    if [ -f "$private_key_path" ] && [ -f "$public_key_path" ]; then
        return 0
    fi

    log "JWT keys not found, generating a new RSA key pair."

    ensure_parent_dir "$private_key_path"
    ensure_parent_dir "$public_key_path"

    if [ -n "$passphrase" ]; then
        openssl genpkey \
            -algorithm RSA \
            -out "$private_key_path" \
            -aes-256-cbc \
            -pass "pass:$passphrase" \
            -pkeyopt rsa_keygen_bits:4096

        openssl pkey \
            -in "$private_key_path" \
            -passin "pass:$passphrase" \
            -pubout \
            -out "$public_key_path"
    else
        openssl genpkey \
            -algorithm RSA \
            -out "$private_key_path" \
            -pkeyopt rsa_keygen_bits:4096

        openssl pkey \
            -in "$private_key_path" \
            -pubout \
            -out "$public_key_path"
    fi

    chmod 600 "$private_key_path"
    chmod 644 "$public_key_path"
}

wait_for_postgres() {
    if [ -z "${DATABASE_URL:-}" ]; then
        return 0
    fi

    db_host="$(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_HOST) ?: "";')"
    db_port="$(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_PORT) ?: "5432";')"
    db_name="$(php -r 'parse_str((string) parse_url(getenv("DATABASE_URL"), PHP_URL_QUERY), $query); $path = parse_url(getenv("DATABASE_URL"), PHP_URL_PATH) ?: ""; echo ltrim($path, "/");')"
    db_user="$(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_USER) ?: "";')"

    if [ -z "$db_host" ] || [ -z "$db_name" ] || [ -z "$db_user" ]; then
        log "Skipping database wait because DATABASE_URL could not be parsed."
        return 0
    fi

    retries="${DB_WAIT_RETRIES:-30}"
    delay="${DB_WAIT_DELAY:-2}"
    attempt=1

    log "Waiting for PostgreSQL at ${db_host}:${db_port}/${db_name}"

    while [ "$attempt" -le "$retries" ]; do
        if pg_isready -h "$db_host" -p "$db_port" -U "$db_user" -d "$db_name" >/dev/null 2>&1; then
            log "PostgreSQL is reachable."
            return 0
        fi

        sleep "$delay"
        attempt=$((attempt + 1))
    done

    printf '[entrypoint] PostgreSQL did not become reachable in time.\n' >&2
    exit 1
}

run_console() {
    php bin/console "$@"
}

console_env_args() {
    args="--env=${APP_ENV:-prod}"

    if [ "${APP_DEBUG:-0}" = "0" ]; then
        args="$args --no-debug"
    fi

    printf '%s' "$args"
}

create_database_if_needed() {
    if [ -z "${DATABASE_URL:-}" ]; then
        return 0
    fi

    if [ "${CREATE_DATABASE_IF_MISSING:-1}" != "1" ]; then
        return 0
    fi

    log "Ensuring database exists."
    # shellcheck disable=SC2086
    run_console doctrine:database:create --if-not-exists --no-interaction $(console_env_args)
}

require_env APP_SECRET
require_env DATABASE_URL
ensure_jwt_keys
file_from_env JWT_SECRET_KEY
file_from_env JWT_PUBLIC_KEY

mkdir -p var/cache var/log

wait_for_postgres

create_database_if_needed

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    log "Running database migrations."
    # shellcheck disable=SC2086
    run_console doctrine:migrations:migrate --no-interaction --all-or-nothing $(console_env_args)
fi

if [ "${WARMUP_CACHE:-1}" = "1" ]; then
    log "Warming up Symfony cache."
    # shellcheck disable=SC2086
    run_console cache:clear --no-interaction $(console_env_args)
    # shellcheck disable=SC2086
    run_console cache:warmup --no-interaction $(console_env_args)
fi

if [ "$#" -eq 0 ]; then
    set -- frankenphp run --config /etc/caddy/Caddyfile
fi

log "Starting application."
exec "$@"
