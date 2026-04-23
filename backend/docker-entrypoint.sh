#!/bin/sh
set -e
cd /var/www/html

i=0
while ! php artisan db:show > /dev/null 2>&1; do
  i=$((i + 1))
  if [ "$i" -gt 90 ]; then
    echo "MySQL did not become reachable in time."
    exit 1
  fi
  echo "Waiting for MySQL..."
  sleep 1
done

php artisan migrate --force

php artisan storage:link 2>/dev/null || true

echo "Ensuring root user exists..."
php artisan db:seed --force --class='Database\Seeders\RootUserSeeder'
echo "Root user seed step finished."

exec php artisan serve --host=0.0.0.0 --port=8000
