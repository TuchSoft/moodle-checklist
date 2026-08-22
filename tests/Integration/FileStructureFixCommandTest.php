<?php

namespace Tuchsoft\MoodleChecklist\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class FileStructureFixCommandTest extends TestCase
{
    private string $bin;
    private string $plugin;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->bin = "{$root}/bin/console";
        $this->plugin = realpath("{$root}/tests/fixtures/filestructure-fix-plugin/local/filestructurefix")
            ?: "{$root}/tests/fixtures/filestructure-fix-plugin/local/filestructurefix";
        $this->resetFixture();
    }

    private function resetFixture(): void
    {
        $pluginRoot = $this->plugin;

        // Remove scaffolded files and directories so each test starts clean.
        $scaffolded = [
            '.moodleplugin',
            'README.md',
            'CHANGELOG.md',
            'LICENSE.md',
            'CONTRIBUTING.md',
            '.gitignore',
        ];
        foreach ($scaffolded as $relative) {
            $path = "{$pluginRoot}/{$relative}";
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                rmdir($path);
            }
        }

        // Provide a fake .git directory so required-dir-git does not fail.
        $gitDir = "{$pluginRoot}/.git";
        if (!is_dir($gitDir)) {
            mkdir($gitDir, 0755, true);
        }

        // Create a non-UTF-8 file to exercise re-encoding.
        file_put_contents("{$pluginRoot}/README_ISO.md", mb_convert_encoding('café', 'ISO-8859-1'));
    }

    /**
     * @param string[] $extraArgs
     */
    private function runFix(array $extraArgs = []): Process
    {
        $command = [
            'php',
            $this->bin,
            'fix',
            '--include-check=filestructure',
            $this->plugin,
            ...$extraArgs,
        ];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        return $process;
    }

    /**
     * @param string[] $extraArgs
     */
    private function runCheck(array $extraArgs = []): Process
    {
        $command = [
            'php',
            $this->bin,
            'check',
            '--no-parallel',
            '--exclude-check=filestructure.moodle-plugin-ci.validate',
            $this->plugin,
            ...$extraArgs,
        ];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        return $process;
    }

    public function testDryRunDoesNotCreateFiles(): void
    {
        $process = $this->runFix();
        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Dry-run fix should succeed. Output:\n{$output}");
        $this->assertStringContainsString('Would create', $output);
        $this->assertStringContainsString('Dry-run complete', $output);

        $this->assertDirectoryDoesNotExist($this->plugin . '/.moodleplugin');
        $this->assertFileDoesNotExist($this->plugin . '/README.md');
    }

    public function testApplyCreatesRequiredFilesAndDirectory(): void
    {
        $process = $this->runFix(['--apply']);
        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Apply fix should succeed. Output:\n{$output}");
        $this->assertStringContainsString('Formatting applied', $output);

        $this->assertDirectoryExists($this->plugin . '/.moodleplugin');

        foreach (['README.md', 'CHANGELOG.md', 'LICENSE.md', 'CONTRIBUTING.md', '.gitignore'] as $file) {
            $path = $this->plugin . '/' . $file;
            $this->assertFileExists($path, "Fixer should create {$file}.");
            $content = file_get_contents($path);
            $this->assertStringContainsString('TODO', $content, "{$file} should contain a TODO placeholder.");
        }

        $readme = file_get_contents($this->plugin . '/README.md');
        $this->assertStringContainsString('local_filestructurefix', $readme, 'README.md should mention the plugin component.');

        $gitignore = file_get_contents($this->plugin . '/.gitignore');
        $this->assertStringContainsString('gitignore fixer', $gitignore, '.gitignore should hint at the gitignore fixer.');
    }

    public function testApplyReEncodesFilesToUtf8(): void
    {
        $isoFile = $this->plugin . '/README_ISO.md';
        $this->assertFileExists($isoFile, 'Non-UTF-8 fixture file should exist.');

        // Make the ISO file look like one of the monitored files by renaming it.
        $readmePath = $this->plugin . '/README.md';
        rename($isoFile, $readmePath);

        $before = mb_detect_encoding(file_get_contents($readmePath), 'UTF-8, ISO-8859-1, ASCII', true);
        $this->assertSame('ISO-8859-1', $before, 'Fixture should be ISO-8859-1 before fix.');

        $process = $this->runFix(['--apply']);
        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Apply fix should succeed. Output:\n{$output}");
        $this->assertStringContainsString('Re-encoded', $output);

        $after = mb_detect_encoding(file_get_contents($readmePath), 'UTF-8, ISO-8859-1, ASCII', true);
        $this->assertSame('UTF-8', $after, 'README.md should be UTF-8 after fix.');
        $this->assertSame('café', file_get_contents($readmePath), 'README.md content should be preserved.');
    }

    public function testReCheckAfterApplyShowsFewerIssues(): void
    {
        $before = $this->runCheck();
        $beforeOutput = $before->getOutput() . $before->getErrorOutput();
        $this->assertStringContainsString('filestructure.required-file-readme', $beforeOutput, 'Missing README should be reported before fix.');
        $this->assertStringContainsString('filestructure.required-dir-moodleplugin', $beforeOutput, 'Missing .moodleplugin should be reported before fix.');

        $apply = $this->runFix(['--apply']);
        $this->assertSame(0, $apply->getExitCode(), 'Apply should succeed.');

        $after = $this->runCheck();
        $afterOutput = $after->getOutput() . $after->getErrorOutput();
        $this->assertStringNotContainsString('filestructure.required-file-readme', $afterOutput, 'README issue should be gone after fix.');
        $this->assertStringNotContainsString('filestructure.required-file-changelog', $afterOutput, 'CHANGELOG issue should be gone after fix.');
        $this->assertStringNotContainsString('filestructure.required-file-license', $afterOutput, 'LICENSE issue should be gone after fix.');
        $this->assertStringNotContainsString('filestructure.required-file-contributing', $afterOutput, 'CONTRIBUTING issue should be gone after fix.');
        $this->assertStringNotContainsString('filestructure.required-file-gitignore', $afterOutput, '.gitignore issue should be gone after fix.');
        $this->assertStringNotContainsString('filestructure.required-dir-moodleplugin', $afterOutput, '.moodleplugin issue should be gone after fix.');
    }
}
