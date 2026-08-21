<?php

namespace Tuchsoft\MoodleChecklist\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class FixCommandTest extends TestCase
{
    private string $bin;
    private string $formattingPlugin;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->bin = "{$root}/bin/console";
        $this->formattingPlugin = realpath("{$root}/tests/fixtures/formatting-plugin/local/formatting")
            ?: "{$root}/tests/fixtures/formatting-plugin/local/formatting";
        $this->resetFixture();
    }

    private function resetFixture(): void
    {
        file_put_contents($this->formattingPlugin . '/lib.php', <<<'PHP'
<?php
// Badly formatted PHP for the formatter fixture.
namespace local_formatting;

function local_formatting_hello(){
  echo 'hello';
}
PHP
        );

        file_put_contents($this->formattingPlugin . '/config.json', <<<'JSON'
{"name":"local_formatting","enabled":true,"count":42,"nested":{"items":[{"id":1,"label":"one"},{"id":2,"label":"two"}]}}
JSON
        );

        file_put_contents($this->formattingPlugin . '/db/install.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?><TABLES><TABLE NAME="local_formatting"><FIELDS><FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/></FIELDS><KEYS><KEY NAME="primary" TYPE="primary" FIELDS="id"/></KEYS></TABLE></TABLES>
XML
        );

        file_put_contents($this->formattingPlugin . '/db/tasks.yml', <<<'YAML'
my_task:
  cron: "*/5 * * * *"
  class: local_formatting\\task\\sample
  args: [1,   2]
YAML
        );

        file_put_contents($this->formattingPlugin . '/README.md', <<<'MD'
# Formatting fixture _(moodle-local_formatting)_

This README has   trailing spaces and weird blank lines.


- item one
-    item two

Some `inline code`   here.
MD
        );
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
            $this->formattingPlugin,
            ...$extraArgs,
        ];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        return $process;
    }

    public function testDryRunDoesNotModifyFiles(): void
    {
        $filesToWatch = [
            'lib.php',
            'config.json',
            'db/install.xml',
            'db/tasks.yml',
            'README.md',
        ];

        $hashes = [];
        foreach ($filesToWatch as $relative) {
            $path = $this->formattingPlugin . '/' . $relative;
            $hashes[$relative] = is_file($path) ? sha1_file($path) : null;
        }

        $process = $this->runFix();
        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Dry-run fix should succeed. Output:\n{$output}");
        $this->assertStringContainsString('Dry-run complete', $output);
        $this->assertStringNotContainsString('Fatal error', $output);

        foreach ($filesToWatch as $relative) {
            $path = $this->formattingPlugin . '/' . $relative;
            if (is_file($path)) {
                $this->assertSame($hashes[$relative], sha1_file($path), "Dry-run should not modify {$relative}.");
            }
        }
    }

    public function testApplyFormatsFiles(): void
    {
        $process = $this->runFix(['--apply']);
        $output = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), "Apply fix should succeed. Output:\n{$output}");
        $this->assertStringContainsString('Formatting applied', $output);
        $this->assertStringNotContainsString('Fatal error', $output);

        $configJson = file_get_contents($this->formattingPlugin . '/config.json');
        $this->assertStringContainsString("{\n", $configJson, 'config.json should be pretty-printed.');

        $readme = file_get_contents($this->formattingPlugin . '/README.md');
        $this->assertStringNotContainsString('   ', $readme, 'README.md trailing spaces should be removed.');

        $lib = file_get_contents($this->formattingPlugin . '/lib.php');
        $this->assertStringNotContainsString("function local_formatting_hello(){", $lib, 'lib.php opening brace should be spaced.');
    }

    public function testReCheckAfterApplyShowsFewerIssues(): void
    {
        $checkCommand = [
            'php',
            $this->bin,
            'check',
            '--no-parallel',
            '--phase=pre-build',
            '--include-check=moodle-plugin-ci.phpcs',
            $this->formattingPlugin,
        ];

        $before = new Process($checkCommand);
        $before->setTimeout(120);
        $before->run();
        $beforeOutput = $before->getOutput() . $before->getErrorOutput();
        $this->assertStringContainsString('ScopeIndent.Incorrect', $beforeOutput, 'Fixable PHPCS issue should be present before fix.');
        $this->assertStringContainsString('OpeningFunctionBraceKernighanRitchie', $beforeOutput, 'Fixable PHPCS issue should be present before fix.');

        $apply = $this->runFix(['--apply', '--include-check=moodle-plugin-ci.phpcs']);
        $this->assertSame(0, $apply->getExitCode(), 'PHPCS-only apply should succeed.');

        $after = new Process($checkCommand);
        $after->setTimeout(120);
        $after->run();
        $afterOutput = $after->getOutput() . $after->getErrorOutput();

        $this->assertStringNotContainsString('ScopeIndent.Incorrect', $afterOutput, 'ScopeIndent issue should be fixed.');
        $this->assertStringNotContainsString('OpeningFunctionBraceKernighanRitchie', $afterOutput, 'Opening brace issue should be fixed.');
    }
}
