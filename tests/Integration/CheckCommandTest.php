<?php

namespace Tuchsoft\MoodleChecklist\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CheckCommandTest extends TestCase
{
    private string $bin;
    private string $cleanPlugin;
    private string $dirtyPlugin;
    private string $missingFilesPlugin;

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
        $this->missingFilesPlugin = realpath("{$root}/tests/fixtures/missing-files-plugin/local/missingfiles") ?: "{$root}/tests/fixtures/missing-files-plugin/local/missingfiles";
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

    /**
     * Runs a single check in parallel mode (the CLI default), which is the mode
     * that treats any PHP warning on stderr as a fatal error.
     *
     * @param string $plugin
     * @param string $check FQCN of the check to run, e.g. Tuchsoft\MoodleChecklist\Check\ReadmeCheck
     */
    private function runSingleCheckParallel(string $plugin, string $check): Process
    {
        $command = [
            'php',
            $this->bin,
            'check',
            '--no-interaction',
            '--quiet',
            '--format', 'raw',
            '--only', $check,
            $plugin,
        ];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        return $process;
    }

    private function assertNoPhpWarnings(Process $process, string $label): void
    {
        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertStringNotContainsString('Undefined array key', $output, "{$label} should not produce undefined array key warnings. Output:\n{$output}");
        $this->assertStringNotContainsString('An unexpected error occurred', $output, "{$label} should not produce an unexpected error. Output:\n{$output}");
        $this->assertStringNotContainsString('Warning:', $output, "{$label} should not produce PHP warnings. Output:\n{$output}");
        $this->assertStringNotContainsString('Fatal error', $output, "{$label} should not produce fatal errors. Output:\n{$output}");
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

    public function testReadmeCheckMissingFileDoesNotFatal(): void
    {
        $process = $this->runSingleCheckParallel($this->missingFilesPlugin, 'Tuchsoft\\MoodleChecklist\\Check\\ReadmeCheck');

        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertNoPhpWarnings($process, 'ReadmeCheck on missing README');
        $this->assertStringContainsString('readme.file-not-found', $output, "ReadmeCheck should report missing README. Output:\n{$output}");
    }

    public function testGitIgnoreCheckMissingFileDoesNotFatal(): void
    {
        $process = $this->runSingleCheckParallel($this->missingFilesPlugin, 'Tuchsoft\\MoodleChecklist\\Check\\GitIgnoreCheck');

        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertNoPhpWarnings($process, 'GitIgnoreCheck on missing .gitignore');
        $this->assertStringContainsString('gitignore.file-not-found', $output, "GitIgnoreCheck should report missing .gitignore. Output:\n{$output}");
    }
}
