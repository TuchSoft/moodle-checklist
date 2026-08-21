<?php

namespace Tuchsoft\MoodleChecklist\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ImageCheckTest extends TestCase
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

    private function runImageCheck(string $plugin): Process
    {
        $process = new Process([
            'php',
            $this->bin,
            'check',
            '--no-interaction',
            '--quiet',
            '--format', 'raw',
            '--only', \Tuchsoft\MoodleChecklist\Check\ImageCheck::class,
            $plugin,
        ]);
        $process->setTimeout(120);
        $process->run();
        return $process;
    }

    public function testCleanPluginPasses(): void
    {
        $process = $this->runImageCheck($this->cleanPlugin);
        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Clean plugin image check should pass. Output:\n{$output}");
        $this->assertStringNotContainsString('Fatal error', $output);
    }

    public function testDirtyPluginReportsImageIssues(): void
    {
        $process = $this->runImageCheck($this->dirtyPlugin);
        $output = $process->getOutput() . $process->getErrorOutput();

        $this->assertStringNotContainsString('Fatal error', $output);
        $this->assertStringContainsString('image.location-invalid', $output);
        $this->assertStringContainsString('image.naming-invalid', $output);
        $this->assertStringContainsString('image.dimensions-exceeded', $output);
        $this->assertStringContainsString('image.size-exceeded', $output);
        $this->assertStringContainsString('image.format-unsupported', $output);
        $this->assertStringContainsString('image.corrupt', $output);
        $this->assertStringContainsString('image.mime-mismatch', $output);
    }
}
