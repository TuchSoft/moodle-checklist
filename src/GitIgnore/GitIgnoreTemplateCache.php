<?php

namespace Tuchsoft\MoodleChecklist\GitIgnore;

use RuntimeException;

/**
 * Manages the cached gitignore.io template used by the GitIgnore fixer.
 */
class GitIgnoreTemplateCache
{
    private string $cacheDir;

    private string $templateFile;

    private string $metaFile;

    private string $sourceUrl;

    private int $ttlSeconds;

    public function __construct(
        ?string $cacheDir = null,
        ?string $sourceUrl = null,
        int $ttlDays = 30
    ) {
        $this->cacheDir = $cacheDir ?? $this->defaultCacheDir();
        $this->templateFile = "{$this->cacheDir}/default.template";
        $this->metaFile = "{$this->cacheDir}/default.meta.json";
        $this->sourceUrl = $sourceUrl ?? 'https://www.toptal.com/developers/gitignore/api/windows,macos,linux,visualstudiocode,intellij,phpunit';
        $this->ttlSeconds = $ttlDays * 24 * 60 * 60;
    }

    private function defaultCacheDir(): string
    {
        return dirname(__DIR__, 2) . '/data/gitignore';
    }

    /**
     * Returns the cached template content, or null if not cached.
     */
    public function getTemplate(): ?string
    {
        if (!is_file($this->templateFile)) {
            return null;
        }

        $content = file_get_contents($this->templateFile);
        return $content === false ? null : $content;
    }

    /**
     * Whether the cache file exists.
     */
    public function hasCache(): bool
    {
        return is_file($this->templateFile);
    }

    /**
     * Whether the cache is older than the configured TTL.
     */
    public function isStale(): bool
    {
        if (!$this->hasCache()) {
            return true;
        }

        $meta = $this->getMeta();
        if ($meta === null || !isset($meta['fetched_at'])) {
            return true;
        }

        $fetched = strtotime($meta['fetched_at']);
        if ($fetched === false) {
            return true;
        }

        return (time() - $fetched) > $this->ttlSeconds;
    }

    /**
     * Returns cache metadata, or null if unavailable.
     */
    public function getMeta(): ?array
    {
        if (!is_file($this->metaFile)) {
            return null;
        }

        $content = file_get_contents($this->metaFile);
        if ($content === false) {
            return null;
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException $e) {
            return null;
        }
    }

    /**
     * Fetches the template from the network and updates the cache.
     *
     * @return string The fetched template content.
     * @throws RuntimeException If the fetch fails.
     */
    public function refresh(): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'moodle-checklist gitignore template fetcher',
            ],
        ]);

        $content = @file_get_contents($this->sourceUrl, false, $context);

        if ($content === false) {
            throw new RuntimeException("Failed to fetch gitignore.io template from {$this->sourceUrl}");
        }

        $this->write($content);

        return $content;
    }

    /**
     * Writes content to the cache.
     */
    public function write(string $content): void
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0777, true)) {
            throw new RuntimeException("Failed to create cache directory: {$this->cacheDir}");
        }

        if (file_put_contents($this->templateFile, $content) === false) {
            throw new RuntimeException("Failed to write template file: {$this->templateFile}");
        }

        $meta = [
            'source' => $this->sourceUrl,
            'fetched_at' => date('c'),
            'templates' => 'windows,macos,linux,visualstudiocode,intellij,phpunit',
        ];

        if (file_put_contents($this->metaFile, json_encode($meta, JSON_PRETTY_PRINT) . "\n") === false) {
            throw new RuntimeException("Failed to write meta file: {$this->metaFile}");
        }
    }

    /**
     * Returns the template, refreshing the cache first if it is stale or missing.
     */
    public function getOrRefresh(): ?string
    {
        if (!$this->hasCache() || $this->isStale()) {
            try {
                return $this->refresh();
            } catch (RuntimeException $e) {
                return $this->getTemplate();
            }
        }

        return $this->getTemplate();
    }
}
