#!/usr/bin/env bash
# Provision (or repair) the local DinkyMetrics test stack. Idempotent.
set -euo pipefail
cd "$(dirname "$0")"

COMPOSE="docker compose -f docker-compose.yml"
J() { $COMPOSE exec -T joomla sh -c "$1"; }

echo "==> docker compose up -d"
$COMPOSE up -d

echo "==> waiting for Joomla core files in the html volume"
for _ in $(seq 1 60); do
  if J '[ -f /var/www/html/libraries/src/Version.php ]' 2>/dev/null; then break; fi
  sleep 2
done

if J '[ -s /var/www/html/configuration.php ]' 2>/dev/null; then
  echo "==> Joomla already installed"
else
  echo "==> installing Joomla (headless)"
  J 'cd /var/www/html && php installation/joomla.php install \
       --site-name="Dinky Metrics Test Site" \
       --admin-user="Admin User" --admin-username=admin --admin-password="admin1234secure" --admin-email=admin@example.com \
       --db-type=mysqli --db-host=db --db-user=joomla --db-pass=joomlapw --db-name=joomla --db-prefix=jos_ \
       -n'
  # The headless installer runs as root, so configuration.php ends up root-owned and
  # Global Configuration cannot be saved from the browser. Hand it to Apache, and give
  # the throwaway stack a session long enough to work in.
  J 'chown -R www-data:www-data /var/www/html/cache /var/www/html/administrator/cache /var/www/html/tmp
     chown www-data:www-data /var/www/html/configuration.php
     sed -i "s/public \$lifetime = 15;/public \$lifetime = 120;/" /var/www/html/configuration.php'
fi

echo "==> symlinking module source from /repo"
J '
  set -e
  cd /var/www/html/modules
  rm -rf mod_dinkymetrics && mkdir mod_dinkymetrics
  ln -s /repo/mod_dinkymetrics.xml mod_dinkymetrics/mod_dinkymetrics.xml
  ln -s /repo/services             mod_dinkymetrics/services
  ln -s /repo/tmpl                 mod_dinkymetrics/tmpl
  ln -s /repo/language             mod_dinkymetrics/language
  chown -h www-data:www-data mod_dinkymetrics mod_dinkymetrics/*
  # src/ and layouts/ arrive in later slices; link them once they exist.
  if [ -d /repo/src ]; then
    ln -s /repo/src mod_dinkymetrics/src
    chown -h www-data:www-data mod_dinkymetrics/src
  fi
  if [ -d /repo/layouts ]; then
    ln -s /repo/layouts mod_dinkymetrics/layouts
    chown -h www-data:www-data mod_dinkymetrics/layouts
  fi
  cd /var/www/html/media
  rm -rf mod_dinkymetrics
  if [ -d /repo/media/mod_dinkymetrics ]; then
    ln -s /repo/media/mod_dinkymetrics mod_dinkymetrics
    chown -h www-data:www-data mod_dinkymetrics
  fi
'

if J 'cd /var/www/html && php cli/joomla.php extension:list 2>/dev/null | grep -q mod_dinkymetrics'; then
  echo "==> module already registered"
else
  echo "==> discovering + installing the module"
  J '
    set -e
    cd /var/www/html
    php cli/joomla.php extension:discover -n >/dev/null
    eid=$(php cli/joomla.php extension:discover:list -n 2>/dev/null | awk "/mod_dinkymetrics/ {print \$2}")
    [ -n "$eid" ] || { echo "could not determine discovered extension id" >&2; exit 1; }
    php cli/joomla.php extension:discover:install --eid="$eid" -n
  '
fi

# Joomla only rebuilds administrator/cache/autoload_psr4.php when the file is missing.
# Without this, the module's namespace stays unknown, ModuleDispatcherFactory silently
# falls back to the generic dispatcher, and src/ never runs — with no error anywhere.
echo "==> dropping the extension namespace map so it is rebuilt"
J 'rm -f /var/www/html/administrator/cache/autoload_psr4.php'

echo "==> loading fixtures (categories, articles, two module instances)"
$COMPOSE exec -T db sh -c 'mariadb -ujoomla -pjoomlapw joomla' < fixtures.sql

echo "==> clearing cache"
J 'cd /var/www/html && php cli/joomla.php cache:clean --all >/dev/null 2>&1 || true'

cat <<'EOF'

Ready.

  Site    http://dinkymetrics.localhost/
  Admin   http://dinkymetrics.localhost/administrator/   (admin / admin1234secure)

  Two module instances sit in Cassiopeia's right sidebar and are shown on every page:
    900  "DinkyMetrics - all sources"      in-view, grouped, 1 decimal
    901  "DinkyMetrics - second instance"  on-load, ungrouped, en-GB

  Edit them under Content -> Site Modules to exercise the parameter form.

  Fixture data (expected counts are documented at the bottom of fixtures.sql):
    categories 601 metrics-root > 602 child-a > 603 grand-a1, 604 child-b,
               605 metrics-empty, 606 metrics-unpub (unpublished)
    articles   651, 611-619, 621-622, 631-632, 641 — one per filter

  Module source is symlinked from the working tree: edit src/*.php, tmpl/*.php,
  media/*.css|js and just reload (opcache revalidates within ~2s).
  After changing mod_dinkymetrics.xml:
      docker compose -f .docker/docker-compose.yml exec joomla \
        php /var/www/html/cli/joomla.php extension:discover
  After changing language .ini files:
      docker compose -f .docker/docker-compose.yml exec joomla \
        php /var/www/html/cli/joomla.php cache:clean --all
EOF
