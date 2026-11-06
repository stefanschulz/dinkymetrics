<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * Checks the language files for the mistakes that only show up on a live site as a raw
 * MOD_DINKYMETRICS_… key where a label should be:
 *
 *   php bin/check-language.php
 *
 * - every language folder holds the same keys, in both the .ini and the .sys.ini
 * - every key the manifest refers to exists in the en-GB files
 * - every literal key the PHP and layout files ask for exists in the en-GB .ini
 *
 * Keys assembled at runtime ('PREFIX_' . $variable) cannot be seen by a pattern and are
 * not checked.
 */

declare(strict_types=1);

$root   = \dirname(__DIR__);
$errors = [];

/** Keys of one .ini file, as a list. @return string[] */
$keysOf = static function (string $file): array {
    $keys = [];

    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('~^\s*([A-Z][A-Z0-9_]*)\s*=~', $line, $match)) {
            $keys[] = $match[1];
        }
    }

    return $keys;
};

$files     = ['mod_dinkymetrics.ini', 'mod_dinkymetrics.sys.ini'];
$languages = array_map('basename', glob($root . '/language/*', GLOB_ONLYDIR) ?: []);
$keys      = [];

foreach ($languages as $language) {
    foreach ($files as $file) {
        $path = "$root/language/$language/$file";

        if (!is_file($path)) {
            $errors[] = "language/$language/$file is missing";
            continue;
        }

        $list = $keysOf($path);

        foreach (array_keys(array_filter(array_count_values($list), static fn (int $n): bool => $n > 1)) as $key) {
            $errors[] = "language/$language/$file defines $key more than once";
        }

        $keys[$language][$file] = $list;
    }
}

if (!isset($keys['en-GB'])) {
    $errors[] = 'language/en-GB is missing';
}

foreach ($files as $file) {
    foreach ($languages as $language) {
        if ($language === 'en-GB' || !isset($keys[$language][$file], $keys['en-GB'][$file])) {
            continue;
        }

        foreach (array_diff($keys['en-GB'][$file], $keys[$language][$file]) as $key) {
            $errors[] = "$key is in en-GB/$file but not in $language/$file";
        }

        foreach (array_diff($keys[$language][$file], $keys['en-GB'][$file]) as $key) {
            $errors[] = "$key is in $language/$file but not in en-GB/$file";
        }
    }
}

$english = array_merge(...array_values($keys['en-GB'] ?? [[]]));

preg_match_all('~MOD_DINKYMETRICS_[A-Z0-9_]+~', (string) file_get_contents($root . '/mod_dinkymetrics.xml'), $matches);

foreach (array_unique($matches[0]) as $key) {
    if (!\in_array($key, $english, true)) {
        $errors[] = "$key is used in mod_dinkymetrics.xml but defined nowhere in en-GB";
    }
}

$used = [];

foreach (['src', 'tmpl', 'layouts', 'services'] as $folder) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$folder", FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php' && $file->getExtension() !== 'xml') {
            continue;
        }

        // A key that is a prefix ('MOD_X_SOURCE_') followed by a quote is built at runtime.
        preg_match_all('~[\'"](MOD_DINKYMETRICS_[A-Z0-9]+(?:_[A-Z0-9]+)*)[\'"]~', (string) file_get_contents($file->getPathname()), $found);

        foreach ($found[1] as $key) {
            $used[$key] = substr($file->getPathname(), \strlen($root) + 1);
        }
    }
}

foreach ($used as $key => $where) {
    if (!\in_array($key, $english, true)) {
        $errors[] = "$key is used in $where but defined nowhere in en-GB";
    }
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: $error\n");
    }

    exit(1);
}

printf(
    "OK: %s — %d keys in en-GB, %d referenced from the manifest, %d from code and layouts\n",
    implode(' + ', $languages),
    \count($english),
    \count(array_unique($matches[0])),
    \count($used)
);
