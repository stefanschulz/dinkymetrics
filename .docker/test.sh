#!/usr/bin/env bash
# Run the unit tests inside the Joomla container.
#
# Why not just `composer run test` on the host: the Formatter's primary path is the intl
# extension, and a Windows PHP CLI usually has no intl — the tests would then only ever
# exercise the fallback. The container ships PHP 8.3 with ICU, which is also far closer
# to what the module will run on.
#
# The working tree is mounted read-only, so PHPUnit's cache goes to /tmp.
set -euo pipefail
cd "$(dirname "$0")"

# Git Bash on Windows rewrites arguments that look like Unix paths, turning /repo into
# C:/Program Files/Git/repo before Docker ever sees it. Harmless everywhere else.
export MSYS_NO_PATHCONV=1

docker compose -f docker-compose.yml exec -T joomla \
  php /repo/vendor/bin/phpunit \
  --configuration /repo/phpunit.xml.dist \
  --cache-directory /tmp/phpunit \
  "$@"
