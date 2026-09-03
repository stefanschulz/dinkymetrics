#!/usr/bin/env bash
# Provision the Joomla 6 acceptance stack and install the module FROM THE BUILT ZIP.
#
# This is the run that verifies the package itself: the manifest's <files> and <media>
# blocks, the language file locations, and that everything the module needs is actually
# in the archive. Nothing is symlinked here — if a file is missing from the ZIP, it is
# missing on this site.
#
# Build the package first:  phing package
set -euo pipefail
cd "$(dirname "$0")"

export MSYS_NO_PATHCONV=1

COMPOSE="docker compose -f docker-compose.j6.yml"
J() { $COMPOSE exec -T joomla sh -c "$1"; }

ZIP=$(ls -1 ../.releases/mod_dinkymetrics-*.zip 2>/dev/null | tail -1 || true)
[ -n "$ZIP" ] || { echo "No package in .releases/ — run 'phing package' first." >&2; exit 1; }
ZIP_NAME=$(basename "$ZIP")
echo "==> package: $ZIP_NAME"

echo "==> docker compose up -d"
$COMPOSE up -d

echo "==> waiting for Joomla core files in the html volume"
for _ in $(seq 1 90); do
  if J '[ -f /var/www/html/libraries/src/Version.php ]' 2>/dev/null; then break; fi
  sleep 2
done

if J '[ -s /var/www/html/configuration.php ]' 2>/dev/null; then
  echo "==> Joomla already installed"
else
  echo "==> installing Joomla 6 (headless)"
  J 'cd /var/www/html && php installation/joomla.php install \
       --site-name="DinkyMetrics J6 Acceptance" \
       --admin-user="Admin User" --admin-username=admin --admin-password="admin1234secure" --admin-email=admin@example.com \
       --db-type=mysqli --db-host=db --db-user=joomla --db-pass=joomlapw --db-name=joomla --db-prefix=jos_ \
       -n'
  J 'chown -R www-data:www-data /var/www/html/cache /var/www/html/administrator/cache /var/www/html/tmp
     chown www-data:www-data /var/www/html/configuration.php
     sed -i "s/public \$lifetime = 15;/public \$lifetime = 120;/" /var/www/html/configuration.php'
fi

if J 'cd /var/www/html && php cli/joomla.php extension:list 2>/dev/null | grep -q mod_dinkymetrics'; then
  echo "==> module already installed — remove it first to re-test a fresh install"
else
  echo "==> installing the module from the package"
  J "cp /repo/.releases/$ZIP_NAME /tmp/$ZIP_NAME
     cd /var/www/html && php cli/joomla.php extension:install --path=/tmp/$ZIP_NAME -n"
fi

echo "==> loading fixtures"
$COMPOSE exec -T db sh -c 'mariadb -ujoomla -pjoomlapw joomla' < fixtures.sql

echo "==> clearing cache"
J 'cd /var/www/html && php cli/joomla.php cache:clean --all >/dev/null 2>&1 || true'

cat <<'EOF'

Ready — Joomla 6, module installed from the ZIP.

  Site    http://dinkymetrics6.localhost/
  Admin   http://dinkymetrics6.localhost/administrator/   (admin / admin1234secure)

  Compare against the Joomla 5 stack at http://dinkymetrics.localhost/ — same
  fixtures, same expected numbers, but there the module runs from symlinked source.

  Tear down completely:
      docker compose -f docker-compose.j6.yml down -v
EOF
