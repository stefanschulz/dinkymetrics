<?php

/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * Verifies that every place the module version is written down agrees.
 *
 *   php bin/check-version.php [--expect=X.Y.Z]
 *
 * --expect additionally pins them all to a given version (the release workflow passes
 * the pushed tag's version). The newest numbered CHANGELOG entry is the one compared;
 * an [Unreleased] section above it is ignored on purpose.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);

$sources = [
    'mod_dinkymetrics.xml (<version>)'     => ['mod_dinkymetrics.xml', '~<version>\s*(\d+\.\d+\.\d+)\s*</version>~'],
    '.doc/ARCHITECTURE.md (Version line)'  => ['.doc/ARCHITECTURE.md', '~^\*\*Version\*\*:\s*(\d+\.\d+\.\d+)\s*$~m'],
    'CHANGELOG.md (newest numbered entry)' => ['CHANGELOG.md', '~^##\s+\[(\d+\.\d+\.\d+)\]~m'],
];

$expected = null;

foreach (\array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--expect=')) {
        $expected = substr($arg, \strlen('--expect='));
    }
}

$found = [];

foreach ($sources as $label => [$path, $pattern]) {
    $content = @file_get_contents($root . '/' . $path);

    if ($content === false || !preg_match($pattern, $content, $match)) {
        fwrite(STDERR, "ERROR: no version found in $label\n");
        exit(1);
    }

    $found[$label] = $match[1];
}

$target = $expected ?? reset($found);
$failed = false;

foreach ($found as $label => $version) {
    $ok = $version === $target;

    echo ($ok ? 'ok       ' : 'MISMATCH ') . str_pad($version, 8) . ' ' . $label . "\n";
    $failed = $failed || !$ok;
}

if ($failed) {
    fwrite(STDERR, "ERROR: version mismatch (expected $target)\n");
    exit(1);
}

echo "All versions are $target.\n";
