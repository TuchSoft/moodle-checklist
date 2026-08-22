<?php

/**
 * Fetches gitignore.io templates and caches them locally.
 *
 * Run automatically by Composer post-install-cmd / post-update-cmd.
 */

$templates = 'windows,macos,linux,visualstudiocode,intellij,phpunit';
$url = "https://www.toptal.com/developers/gitignore/api/{$templates}";

$root = dirname(__DIR__);
$cacheDir = "{$root}/data/gitignore";
$templateFile = "{$cacheDir}/default.template";
$metaFile = "{$cacheDir}/default.meta.json";

if (!is_dir($cacheDir) && !mkdir($cacheDir, 0777, true)) {
    fwrite(STDERR, "Failed to create cache directory: {$cacheDir}\n");
    exit(1);
}

$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'user_agent' => 'moodle-checklist gitignore template fetcher',
    ],
]);

$content = @file_get_contents($url, false, $context);

if ($content === false) {
    fwrite(STDERR, "Failed to fetch gitignore.io template from {$url}\n");
    exit(1);
}

if (file_put_contents($templateFile, $content) === false) {
    fwrite(STDERR, "Failed to write template file: {$templateFile}\n");
    exit(1);
}

$meta = [
    'source' => $url,
    'fetched_at' => date('c'),
    'templates' => $templates,
];

if (file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT) . "\n") === false) {
    fwrite(STDERR, "Failed to write meta file: {$metaFile}\n");
    exit(1);
}

echo "Cached gitignore.io template to {$templateFile}\n";
exit(0);
