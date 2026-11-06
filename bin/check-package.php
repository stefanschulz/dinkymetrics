<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * Sanity-checks a built release ZIP before it is published.
 *
 *   php bin/check-package.php .releases/mod_dinkymetrics-X.Y.Z.zip
 *
 * A Joomla installer silently drops whatever the manifest does not declare and fails late
 * on whatever it declares but the archive lacks, so this checks the archive against the
 * manifest it carries:
 *
 * - the manifest sits at the root, and its version matches the file name
 * - every <files> and <media> entry exists in the archive, and nothing else is in it
 * - no development tooling (tests, vendor, composer files, dot-folders, docs) leaked in
 * - each language folder has both its .ini and its .sys.ini
 * - every PHP file in it parses (checked on the shipped bytes, not the working tree)
 * - the update.xml next to the ZIP, if there is one, carries the ZIP's version and checksum
 */

declare(strict_types=1);

$zipPath = $argv[1] ?? '';

if ($zipPath === '' || !is_file($zipPath)) {
    fwrite(STDERR, "Usage: php bin/check-package.php <zip>\n");
    exit(2);
}

$errors = [];
$zip    = new ZipArchive();

if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "ERROR: cannot open $zipPath\n");
    exit(1);
}

$entries = [];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);

    if (!str_ends_with($name, '/')) {
        $entries[] = $name;
    }
}

sort($entries);

// --- the manifest -------------------------------------------------------------------------

$manifest = null;
$raw      = $zip->getFromName('mod_dinkymetrics.xml');

if ($raw === false) {
    $errors[] = 'mod_dinkymetrics.xml is not at the root of the archive';
} else {
    $manifest = simplexml_load_string($raw);

    if ($manifest === false) {
        $errors[] = 'mod_dinkymetrics.xml is not well-formed XML';
        $manifest = null;
    }
}

$version = $manifest ? trim((string) $manifest->version) : '';

if ($manifest && basename($zipPath) !== "mod_dinkymetrics-$version.zip") {
    $errors[] = 'the file name ' . basename($zipPath) . " does not match the manifest version $version";
}

// --- nothing that belongs to the repository, not the extension ----------------------------

foreach ($entries as $name) {
    if (preg_match('~^(\.|tests/|vendor/|bin/|composer\.|phpcs\.|phpunit\.|build\.xml$|CHANGELOG|README|LICENSE$)~', $name)) {
        $errors[] = "development file in the archive: $name";
    }
}

// --- the manifest's promises, both directions ---------------------------------------------

if ($manifest) {
    $declared = ['mod_dinkymetrics.xml' => true, 'LICENSE.txt' => true];
    $media    = [];

    foreach ($manifest->files->children() ?? [] as $node) {
        $declared[trim((string) $node)] = $node->getName();
    }

    foreach ($manifest->media->children() ?? [] as $node) {
        $media[trim((string) $node)] = $node->getName();
    }

    $mediaRoot = trim((string) ($manifest->media['folder'] ?? ''));

    // Direction one: everything declared is in the archive.
    foreach ($declared as $path => $type) {
        if ($type === true) {
            $present = \in_array($path, $entries, true);
        } elseif ($type === 'folder') {
            $present = \count(array_filter($entries, static fn (string $e): bool => str_starts_with($e, $path . '/'))) > 0;
        } else {
            $present = \in_array($path, $entries, true);
        }

        if (!$present) {
            $errors[] = "declared in the manifest <files> but not in the archive: $path";
        }
    }

    foreach ($media as $path => $type) {
        $full = $mediaRoot . '/' . $path;
        $hit  = $type === 'folder'
            ? \count(array_filter($entries, static fn (string $e): bool => str_starts_with($e, $full . '/'))) > 0
            : \in_array($full, $entries, true);

        if (!$hit) {
            $errors[] = "declared in the manifest <media> but not in the archive: $full";
        }
    }

    // Direction two: everything in the archive is declared, or the installer drops it.
    foreach ($entries as $name) {
        $top = explode('/', $name, 2)[0];

        if ($mediaRoot !== '' && str_starts_with($name, $mediaRoot . '/')) {
            $inner = explode('/', substr($name, \strlen($mediaRoot) + 1), 2)[0];

            if (!isset($media[$inner])) {
                $errors[] = "in the archive but not declared in the manifest <media>: $name";
            }

            continue;
        }

        if (!isset($declared[$top]) && !isset($declared[$name])) {
            $errors[] = "in the archive but not declared in the manifest <files>: $name";
        }
    }
}

// --- language files come in pairs ---------------------------------------------------------

$languages = [];

foreach ($entries as $name) {
    if (preg_match('~^language/([^/]+)/~', $name, $match)) {
        $languages[$match[1]] = true;
    }
}

foreach (array_keys($languages) as $language) {
    foreach (['mod_dinkymetrics.ini', 'mod_dinkymetrics.sys.ini'] as $file) {
        if (!\in_array("language/$language/$file", $entries, true)) {
            $errors[] = "language/$language/$file is missing";
        }
    }
}

// --- the shipped PHP parses ---------------------------------------------------------------

$tmp = sys_get_temp_dir() . '/dinkymetrics-check-' . getmypid();
mkdir($tmp);
$zip->extractTo($tmp);
$zip->close();

foreach ($entries as $name) {
    if (!str_ends_with($name, '.php')) {
        continue;
    }

    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg("$tmp/$name") . ' 2>&1', $output, $code);

    if ($code !== 0) {
        $errors[] = "syntax error in $name: " . implode(' ', $output);
    }
}

$cleanup = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($cleanup as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}

rmdir($tmp);

// --- update.xml, the Joomla update server's description of this very ZIP ------------------

$updatePath = \dirname($zipPath) . '/update.xml';

if (is_file($updatePath)) {
    $update = simplexml_load_file($updatePath);
    $node   = $update ? $update->update : null;

    if (!$node) {
        $errors[] = 'update.xml is not a valid update description';
    } else {
        if ((string) $node->version !== $version) {
            $errors[] = 'update.xml says version ' . $node->version . ", the manifest says $version";
        }

        if ((string) $node->sha256 !== hash_file('sha256', $zipPath)) {
            $errors[] = 'update.xml carries a sha256 that does not match the ZIP';
        }

        if (!str_ends_with((string) $node->downloads->downloadurl, '/' . basename($zipPath))) {
            $errors[] = 'update.xml points at a different file than ' . basename($zipPath);
        }
    }
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: $error\n");
    }

    exit(1);
}

echo 'OK: ' . basename($zipPath) . ' (' . \count($entries) . " files, version $version"
    . (is_file($updatePath) ? ', update.xml matches' : '') . ")\n";
