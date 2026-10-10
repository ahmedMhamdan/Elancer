#!/bin/sh
# Container start on Render: stop if a setting the site cannot run without is missing, prepare the
# writable folders, cache the configuration from the service's environment, report whether the mail
# relay, the database and the file buckets answer, then run the site with the queue worker and the
# scheduler beside it.
set -eu

# Without these every page fails while the health check still answers, so the start stops here and
# the host keeps the previous deployment. Only names are printed.
missing=""
for name in APP_KEY DB_CONNECTION DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
    eval "value=\${$name:-}"
    [ -n "$value" ] || missing="$missing $name"
done
if [ -n "$missing" ]; then
    echo "[elancer] cannot start: these settings are missing:$missing. Add them under the service's Environment Variables (not Secret Files) and deploy again." >&2
    exit 1
fi

cd /var/www/html

# Render supplies its public address; an explicit APP_URL wins.
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"
# Render's edge names each visitor in this header, which per-address limits then count by. An explicit
# setting wins, and an empty one keeps the proxy's address.
export CLIENT_ADDRESS_HEADER="${CLIENT_ADDRESS_HEADER-CF-Connecting-IP}"

mkdir -p storage/app/private storage/app/identity-private storage/app/public \
    storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

as_app() {
    runuser -u www-data -- "$@"
}

as_app php artisan optimize --no-interaction || echo "[elancer] optimize failed; continuing without cached configuration" >&2
as_app php docker/check.php || true

# Restarted if they stop. They pause with the container when the free service is put to sleep.
(while :; do as_app php artisan queue:work --sleep=3 --tries=3 --backoff=30 --max-time=3600 || true; sleep 5; done) &
(while :; do as_app php artisan schedule:work || true; sleep 5; done) &

exec apache2-foreground
