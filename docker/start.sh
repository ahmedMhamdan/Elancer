#!/bin/sh
# Container start on Render: prepare the writable folders, cache the configuration from the service's
# environment, report whether the mail relay, the database and the file buckets answer, then run the
# site with the queue worker and the scheduler beside it.
set -eu
cd /var/www/html

# Render supplies its public address; an explicit APP_URL wins.
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"

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
