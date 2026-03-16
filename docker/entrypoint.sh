#!/usr/bin/env bash
set -euo pipefail

cd /var/www

# Defaults (can be overridden via docker-compose env)
: "${CI_ENVIRONMENT:=development}"
: "${DB_HOST:=db}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=ims}"
: "${DB_USERNAME:=ims}"
: "${DB_PASSWORD:=ims_password}"

export CI_ENVIRONMENT DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
for i in {1..60}; do
  php -r "\$m=@new mysqli(getenv('DB_HOST'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), getenv('DB_DATABASE'), (int)getenv('DB_PORT')); exit(\$m && \$m->connect_errno===0 ? 0 : 1);" \
    && break
  sleep 2
done

echo "Running migrations..."
php spark migrate --all --no-interaction

if [[ "${SEED_DEV:-0}" == "1" ]]; then
  echo "Seeding dev data..."
  php spark db:seed ImsDevSeeder --no-interaction
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
