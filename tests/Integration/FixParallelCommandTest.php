<?php

namespace Tuchsoft\MoodleChecklist\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class FixParallelCommandTest extends TestCase
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

    public function testParallelDryRunProducesWaveOutput(): void
    {
        $process = $this->runFix();
        $output = $process->getOutput() . $process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), "Parallel dry-run should succeed. Output:\n{$output}");
        $this->assertStringContainsString('Wave 1: bootstrap', $output, 'Bootstrap should run in wave 1.');
        $this->assertStringContainsString('Wave 2: metadata', $output, 'Metadata should run in wave 2.');
        $this->assertStringContainsString('Wave 3:', $output, 'Domain fixers should run in wave 3.');
        $this->assertStringContainsString('Dry-run complete', $output);
        $this->assertStringContainsString('10 formatter(s) would run', $output);
    }

    public function testNoParallelSkipsWaveOutput(): void
    {
        $process = $this->runFix(['--no-parallel']);
        $output = $process->getOutput() . $process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), "Sequential dry-run should succeed. Output:\n{$output}");
        $this->assertStringNotContainsString('Wave 1:', $output, 'Wave headers should not appear in sequential mode.');
        $this->assertStringContainsString('Dry-run complete', $output);
        $this->assertStringContainsString('10 formatter(s) would run', $output);
    }

    public function testParallelAndSequentialProduceSameSummary(): void
    {
        $parallel = $this->runFix();
        $sequential = $this->runFix(['--no-parallel']);

        $parallelOutput = $parallel->getOutput() . $parallel->getErrorOutput();
        $sequentialOutput = $sequential->getOutput() . $sequential->getErrorOutput();

        $this->assertSame(0, $parallel->getExitCode(), "Parallel dry-run should succeed. Output:\n{$parallelOutput}");
        $this->assertSame(0, $sequential->getExitCode(), "Sequential dry-run should succeed. Output:\n{$sequentialOutput}");

        $this->assertStringContainsString('10 formatter(s) would run, 2 skipped', $parallelOutput);
        $this->assertStringContainsString('10 formatter(s) would run, 2 skipped', $sequentialOutput);
    }
}
