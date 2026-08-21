<?php

namespace Tuchsoft\MoodleChecklist\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MarketplaceImagesCheckTest extends TestCase
{
    private string $bin;
    private string $cleanPlugin;
    private string $dirtyPlugin;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->bin = "{$root}/bin/console";
        $this->cleanPlugin = realpath("{$root}/tests/fixtures/clean-plugin/local/clean") ?: "{$root}/tests/fixtures/clean-plugin/local/clean";
        $this->dirtyPlugin = realpath("{$root}/tests/fixtures/dirty-plugin/local/dirty") ?: "{$root}/tests/fixtures/dirty-plugin/local/dirty";
    }

    private function runMarketplaceImagesCheck(string $plugin): Process
    {
        $process = new Process([
            'php',
            $this->bin,
            'check',
            '--no-interaction',
            '--quiet',
            '--format', 'raw',
            '--only', \Tuchsoft\MoodleChecklist\Check\MarketplaceImagesCheck::class,
            $plugin,
        ]);
        $process->setTimeout(120);
        $process->run();
        return $process;
    }

    public function testReportsMissingMarketplaceImages(): void
    {
        $process = $this->runMarketplaceImagesCheck($this->cleanPlugin);
        $output = $process->getOutput() . $process->getErrorOutput();

        $this->assertStringNotContainsString('Fatal error', $output);
        $this->assertStringContainsString('marketplaceimages.poster-image', $output);
        $this->assertStringContainsString('marketplaceimages.screenshot-dir', $output);
        $this->assertStringContainsString('marketplaceimages.screenshots', $output);
    }
}
