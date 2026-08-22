<?php

namespace Tuchsoft\MoodleChecklist\Tests\Unit\GitIgnore;

use PHPUnit\Framework\TestCase;
use Tuchsoft\MoodleChecklist\GitIgnore\GitIgnoreTemplateCache;

class GitIgnoreTemplateCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/moodle-checklist-test-' . uniqid();
        mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->cacheDir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testHasCacheReturnsFalseWhenMissing(): void
    {
        $cache = new GitIgnoreTemplateCache($this->cacheDir);
        $this->assertFalse($cache->hasCache());
        $this->assertNull($cache->getTemplate());
    }

    public function testWriteAndReadTemplate(): void
    {
        $cache = new GitIgnoreTemplateCache($this->cacheDir);
        $cache->write("foo\nbar\n");

        $this->assertTrue($cache->hasCache());
        $this->assertSame("foo\nbar\n", $cache->getTemplate());

        $meta = $cache->getMeta();
        $this->assertNotNull($meta);
        $this->assertArrayHasKey('fetched_at', $meta);
        $this->assertArrayHasKey('source', $meta);
    }

    public function testIsStaleReturnsTrueWhenMissing(): void
    {
        $cache = new GitIgnoreTemplateCache($this->cacheDir);
        $this->assertTrue($cache->isStale());
    }

    public function testIsStaleReturnsFalseForFreshCache(): void
    {
        $cache = new GitIgnoreTemplateCache($this->cacheDir, null, 30);
        $cache->write("foo\n");
        $this->assertFalse($cache->isStale());
    }

    public function testIsStaleReturnsTrueForExpiredCache(): void
    {
        $cache = new GitIgnoreTemplateCache($this->cacheDir, null, 1);
        $cache->write("foo\n");

        // Simulate an old cache by rewriting the meta file with a past timestamp.
        $meta = [
            'source' => 'https://www.toptal.com/developers/gitignore/api/test',
            'fetched_at' => date('c', time() - 90000),
            'templates' => 'windows,macos,linux',
        ];
        file_put_contents($this->cacheDir . '/default.meta.json', json_encode($meta, JSON_PRETTY_PRINT) . "\n");

        $this->assertTrue($cache->isStale());
    }

    public function testRefreshFailsWithInvalidUrl(): void
    {
        $cache = new GitIgnoreTemplateCache($this->cacheDir, 'https://invalid.invalid.invalid.invalid/gitignore', 30);

        $this->expectException(\RuntimeException::class);
        $cache->refresh();
    }
}
