#!/usr/bin/env bash
# Provisions the disposable Joomla from .docker/ci-compose.yml for the integration check:
#   - starts it and waits for Joomla's core files
#   - installs Joomla headless
#   - installs the module from the built ZIP (the same path the acceptance stack takes)
#   - loads .docker/fixtures.sql (the counting matrix and two module instances)
#   - waits until the front page answers
#
# Needs the ZIP in .releases/ first (phing package, or the CI artifact).
#
# Env: JOOMLA_TAG       joomla image tag, 5 or 6 (default 5)
#      CI_JOOMLA_PORT   host port (default 8280)
#      CI_JOOMLA_TIMEOUT seconds to wait for Joomla's files and the front page (default 300)
set -euo pipefail

cd "$(dirname "$0")/.."

# Git Bash on Windows would rewrite /repo into a Windows path before Docker sees it.
export MSYS_NO_PATHCONV=1

PORT="${CI_JOOMLA_PORT:-8280}"
TIMEOUT="${CI_JOOMLA_TIMEOUT:-300}"
# COMPOSE_FILE (docker compose's own variable) can add override files locally.
export COMPOSE_FILE="${COMPOSE_FILE:-.docker/ci-compose.yml}"
COMPOSE=(docker compose)
J() { "${COMPOSE[@]}" exec -T joomla sh -c "$1"; }

ZIP="$(ls -1 .releases/mod_dinkymetrics-*.zip 2>/dev/null | tail -1 || true)"
if [[ -z "$ZIP" ]]; then
    echo "No package in .releases/ - run 'phing package' first." >&2
    exit 1
fi
ZIP_NAME="$(basename "$ZIP")"
echo "Package: $ZIP_NAME"

"${COMPOSE[@]}" up -d

# The image's entrypoint copies Joomla into the (empty) volume and only then starts Apache.
# Version.php shows up long before the copy is done - installing at that point fails with
# "trying to run Joomla from our git repository" - so wait for Apache to answer instead.
echo "Waiting for Joomla's files and Apache (up to ${TIMEOUT}s)..."
deadline=$((SECONDS + TIMEOUT))
until J '[ -f /var/www/html/installation/joomla.php ]' 2>/dev/null && curl -s -o /dev/null "http://localhost:$PORT/"; do
    if (( SECONDS > deadline )); then
        echo "Joomla did not come up in time" >&2
        "${COMPOSE[@]}" logs --tail=60 joomla >&2 || true
        exit 1
    fi
    sleep 2
done

echo "Installing Joomla (headless)..."
J 'cd /var/www/html && php installation/joomla.php install \
     --site-name="DinkyMetrics CI" \
     --admin-user="Admin User" --admin-username=admin --admin-password="admin1234secure" --admin-email=admin@example.com \
     --db-type=mysqli --db-host=db --db-user=joomla --db-pass=joomlapw --db-name=joomla --db-prefix=jos_ \
     -n'
# The installer runs as root; Apache has to be able to write its caches and read the config.
J 'chown -R www-data:www-data /var/www/html/cache /var/www/html/administrator/cache /var/www/html/tmp
   chown www-data:www-data /var/www/html/configuration.php'

echo "Installing the module from the package..."
J "cp /repo/.releases/$ZIP_NAME /tmp/$ZIP_NAME
   cd /var/www/html && php cli/joomla.php extension:install --path=/tmp/$ZIP_NAME -n"
J 'cd /var/www/html && php cli/joomla.php extension:list 2>/dev/null | grep -q mod_dinkymetrics' \
    || { echo "The module is not registered after the install" >&2; exit 1; }

# Joomla only rebuilds this namespace map when the file is missing; without a fresh one the
# dispatcher quietly falls back to the generic one and src/ never runs.
J 'rm -f /var/www/html/administrator/cache/autoload_psr4.php'

echo "Loading the fixtures..."
"${COMPOSE[@]}" exec -T db sh -c 'mariadb -ujoomla -pjoomlapw joomla' < .docker/fixtures.sql
J 'cd /var/www/html && php cli/joomla.php cache:clean --all >/dev/null 2>&1 || true'

echo "Waiting for the front page (up to ${TIMEOUT}s)..."
deadline=$((SECONDS + TIMEOUT))
until curl -s -o /dev/null -f "http://localhost:$PORT/"; do
    if (( SECONDS > deadline )); then
        echo "The front page did not answer in time" >&2
        "${COMPOSE[@]}" logs --tail=60 joomla >&2 || true
        exit 1
    fi
    sleep 3
done
echo "Ready: http://localhost:$PORT/ with the module installed from $ZIP_NAME."
