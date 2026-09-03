# Local test / dev stack

Disposable Joomla instances for `mod_dinkymetrics`, routed by the machine's shared
Traefik proxy. **Not tracked in git** (`/.docker/` is in `.gitignore`).

- **Site:** http://dinkymetrics.localhost/
- **Admin:** http://dinkymetrics.localhost/administrator/ — `admin` / `admin1234secure`
- **DB:** db `joomla`, user `joomla` / `joomlapw`, root `rootpw`, prefix `jos_`
  (no host port — `docker compose exec db mariadb -ujoomla -pjoomlapw joomla`)

Throwaway credentials for a local container only.

## Requirements

- Docker Desktop with drive `P:` shared.
- The shared proxy running (creates/serves the external `dev-proxy` network;
  `docker network create dev-proxy` once).
- No host ports are published by this stack — Traefik handles routing.

## Usage

```bash
cd .docker

./setup.sh      # up + install Joomla + register the module + seed fixtures (idempotent)
./reset.sh      # down -v, then setup.sh from scratch

docker compose -f docker-compose.yml stop     # pause (data kept in named volumes)
docker compose -f docker-compose.yml start    # resume
docker compose -f docker-compose.yml down     # remove containers, keep volumes
docker compose -f docker-compose.yml down -v  # remove everything
```

`db-data` and `html-data` are named volumes, so a plain `stop` / `start` / `down`
keeps the install and fixtures; only `down -v` (or `reset.sh`) wipes them.

## How the module gets in

The working tree is mounted read-only at `/repo`; `setup.sh` symlinks
`mod_dinkymetrics.xml`, `services/`, `tmpl/`, `language/` (and `src/`, `media/` once
they exist) into `modules/mod_dinkymetrics/` and `media/mod_dinkymetrics/`. Joomla
registers it via `extension:discover` — no file copy.

- **PHP edits** (`src/**`, `services/**`, `tmpl/**`) are live on the next request
  (opcache revalidates within ~2s).
- **CSS / JS edits** (`media/**`) are served straight from the symlink — reload and
  bypass the browser cache.
- **`mod_dinkymetrics.xml` edits**: the params form re-reads the manifest on every
  load, so new fields show up immediately. Refresh the stored manifest cache
  (version/author) with:
  ```bash
  docker compose -f docker-compose.yml exec joomla \
    php /var/www/html/cli/joomla.php extension:discover
  ```
- **New class or new namespace** in `src/`: drop the extension namespace map, or the
  class stays invisible and `ModuleDispatcherFactory` quietly uses the generic
  dispatcher — no error, the module simply does nothing:
  ```bash
  docker compose -f docker-compose.yml exec joomla \
    rm -f /var/www/html/administrator/cache/autoload_psr4.php
  ```
  Joomla rebuilds the file on the next request, but only when it is missing.
- **Language `.ini` edits**: clear the cache —
  `docker compose -f docker-compose.yml exec joomla php /var/www/html/cli/joomla.php cache:clean --all`.

## Fixtures

`fixtures.sql` seeds the counting matrix — a six-category tree and fifteen articles,
each one there to make a single filter observable (status, publishing dates, access
level, language, featured, an unpublished category). The expected counts for every
combination are listed at the bottom of that file and are the target values of the
QA checklist.

It also creates two module instances (ids 900 and 901) in Cassiopeia's right sidebar
with different animation and formatting parameters, so two instances on one page can
be watched side by side.

Three main-menu items make the fixture content visible in the front end:

| Menu item | Shows |
|---|---|
| **Testdaten & Sollwerte** (`/dm-testdaten`) | The category tree, why each article is or is not counted, and the expected value of every figure |
| **Kategorien mit Beitragszahlen** (`/dm-kategorien`) | The category overview under `metrics-root` — three direct children, the unpublished one correctly absent |
| **Beiträge in Child A** (`/dm-child-a`) | Exactly the four countable articles; the unpublished, trashed, not-yet, expired and access-restricted ones stay hidden |

Careful with Joomla's own **Article Count** badge on the category page: it shows `7`
for Child A where the module will show `4`. Joomla counts `state = 1` only, without
the publishing dates and without the access level; the module honours both
(spec section 4.1). The difference is intended.

## Checking the counting

```bash
./counts.sh     # tests/Integration/counts.php against the fixture matrix
./test.sh       # the unit tests, on a PHP that has intl
```

Global caching is **off** in this stack so edits show up immediately. Turn it on in
Global Configuration when you want to check the module cache — with it off, Joomla's
module cache never engages, whatever the module's own Caching parameter says.

## Joomla 6 acceptance stack

`docker-compose.j6.yml` is a second, separate stack at
http://dinkymetrics6.localhost/ that installs the module **from the built ZIP**
instead of symlinking it — that is what verifies the manifest and the `<media>`
layout. Use it for the acceptance pass once `phing package` produces
`.releases/mod_dinkymetrics-<version>.zip`.
