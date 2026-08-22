<?php

namespace Tuchsoft\MoodleChecklist\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tuchsoft\MoodleChecklist\GitIgnore\GitIgnoreAssembler;
use Tuchsoft\MoodleChecklist\GitIgnore\GitIgnoreTemplateCache;

class GitIgnoreFixCommandTest extends TestCase
{
    private string $bin;
    private string $formattingPlugin;
    private string $originalGitignore;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->bin = "{$root}/bin/console";
        $this->formattingPlugin = realpath("{$root}/tests/fixtures/formatting-plugin/local/formatting")
            ?: "{$root}/tests/fixtures/formatting-plugin/local/formatting";
        $this->originalGitignore = is_file($this->formattingPlugin . '/.gitignore')
            ? file_get_contents($this->formattingPlugin . '/.gitignore')
            : '';
        $this->resetGitignore();
    }

    protected function tearDown(): void
    {
        $path = $this->formattingPlugin . '/.gitignore';
        if ($this->originalGitignore !== '') {
            file_put_contents($path, $this->originalGitignore);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    private function resetGitignore(): void
    {
        $path = $this->formattingPlugin . '/.gitignore';
        if (is_file($path)) {
            unlink($path);
        }
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
            '--phase=pre-build',
            '--include-check=gitignore',
            $this->formattingPlugin,
            ...$extraArgs,
        ];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        return $process;
    }

    public function testDryRunDoesNotCreateGitignore(): void
    {
        $path = $this->formattingPlugin . '/.gitignore';
        $this->assertFalse(is_file($path));

        $process = $this->runFix();
        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Dry-run fix should succeed. Output:\n{$output}");
        $this->assertStringContainsString('Would regenerate .gitignore', $output);

        $this->assertFalse(is_file($path), 'Dry-run should not create .gitignore.');
    }

    public function testApplyCreatesGitignore(): void
    {
        $path = $this->formattingPlugin . '/.gitignore';

        $process = $this->runFix(['--apply']);
        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Apply fix should succeed. Output:\n{$output}");
        $this->assertTrue(is_file($path), 'Apply should create .gitignore.');

        $content = file_get_contents($path);
        $this->assertStringContainsString('# This .gitignore file is auto-managed by moodle-checklist.', $content);
        $this->assertStringContainsString('# Created by https://www.toptal.com/developers/gitignore', $content);
        $this->assertStringContainsString('# Created by moodle-checklist', $content);
        $this->assertStringContainsString('### Moodle ###', $content);
        $this->assertStringContainsString('vendor/', $content);
        $this->assertStringContainsString('node_modules/', $content);
    }

    public function testReRunIsIdempotent(): void
    {
        $path = $this->formattingPlugin . '/.gitignore';

        $first = $this->runFix(['--apply']);
        $this->assertSame(0, $first->getExitCode());
        $firstHash = sha1_file($path);

        $second = $this->runFix(['--apply']);
        $this->assertSame(0, $second->getExitCode());
        $secondHash = sha1_file($path);

        $this->assertSame($firstHash, $secondHash, 'Re-running the fixer should produce the same .gitignore.');
    }
}
