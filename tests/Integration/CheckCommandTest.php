<?php

namespace Tuchsoft\MoodleChecklist\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CheckCommandTest extends TestCase
{
    private string $bin;
    private string $cleanPlugin;
    private string $dirtyPlugin;

    /**
     * Checks that require a full Moodle installation or external services.
     * They are excluded so the fixture-based integration test stays self-contained.
     */
    private const EXCLUDED_EXTERNAL_CHECKS = [
        '--exclude-check=filestructure',
        '--exclude-check=images',
        '--exclude-check=repository',
        '--exclude-check=moodle-plugin-ci.docblock',
        '--exclude-check=moodle-plugin-ci.gherkinlint',
        '--exclude-check=moodle-plugin-ci.mustache',
        '--exclude-check=moodle-plugin-ci.savepoint',
        '--exclude-check=moodle-plugin-ci.stylelint',
        '--exclude-check=moodle-plugin-ci.phplint',
    ];

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->bin = "{$root}/bin/console";
        $this->cleanPlugin = realpath("{$root}/tests/fixtures/clean-plugin/local/clean") ?: "{$root}/tests/fixtures/clean-plugin/local/clean";
        $this->dirtyPlugin = realpath("{$root}/tests/fixtures/dirty-plugin/local/dirty") ?: "{$root}/tests/fixtures/dirty-plugin/local/dirty";
    }

    /**
     * @param string $plugin
     * @param string[] $extraArgs
     */
    private function runCheck(string $plugin, array $extraArgs = []): Process
    {
        $command = [
            'php',
            $this->bin,
            'check',
            '--no-parallel',
            ...self::EXCLUDED_EXTERNAL_CHECKS,
            $plugin,
        ];

        $process = new Process(array_merge($command, $extraArgs));
        $process->setTimeout(120);
        $process->run();

        return $process;
    }

    public function testCleanPluginPasses(): void
    {
        $process = $this->runCheck($this->cleanPlugin);

        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Clean plugin should pass. Output:\n{$output}");
        $this->assertStringNotContainsString('Fatal error', $output);
        $this->assertStringContainsString('All checks passed!', $output);
    }

    public function testDirtyPluginFailsWithExpectedIssue(): void
    {
        $process = $this->runCheck($this->dirtyPlugin);

        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertNotSame(0, $process->getExitCode(), "Dirty plugin should fail. Output:\n{$output}");
        $this->assertStringContainsString('readme.title', $output);
    }

    public function testCheckstyleFormatDoesNotFatal(): void
    {
        $process = $this->runCheck($this->cleanPlugin, ['--format=checkstyle']);

        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Checkstyle format should not fatal. Output:\n{$output}");
        $this->assertStringContainsString('<?xml version="1.0"', $output);
        $this->assertStringNotContainsString('Fatal error', $output);
    }

    public function testJsonFormatDoesNotFatal(): void
    {
        $reportFile = tempnam(sys_get_temp_dir(), 'mcp-report-') . '.json';
        try {
            $process = $this->runCheck($this->cleanPlugin, ["--format=json:{$reportFile}"]);

            $output = $process->getOutput() . $process->getErrorOutput();
            $this->assertSame(0, $process->getExitCode(), "JSON format should not fatal. Output:\n{$output}");
            $this->assertStringNotContainsString('Fatal error', $output);
            $this->assertFileExists($reportFile);
            $this->assertJson(file_get_contents($reportFile));
        } finally {
            if (file_exists($reportFile)) {
                unlink($reportFile);
            }
        }
    }
}
