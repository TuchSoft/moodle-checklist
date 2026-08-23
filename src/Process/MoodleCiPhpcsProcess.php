<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\IssueReporter\Issue;

class MoodleCiPhpcsProcess extends AbstractProcess
{
    /** @var Issue[] */
    private array $issues = [];

    private string $reportFile;

    public function __construct(private string $pluginPath, private string $moodleRoot)
    {
        parent::__construct($pluginPath);
    }

    /**
     * Accept exit code 1 as a successful parseable run (it means issues were found).
     */
    public function execute(?float $timeout = 300.0): bool
    {
        $this->reportFile = tempnam(sys_get_temp_dir(), 'mcp-phpcs-') . '.json';
        $ok = parent::execute($timeout);
        // 0 = clean, 1 = issues found, 2 = fixable issues found. All are parseable.
        if (!$ok && !in_array($this->getExitCode(), [1, 2], true)) {
            return false;
        }
        return $this->parseOutput();
    }

    /**
     * @return Issue[]
     */
    public function getIssues(?string $code = null): array
    {
        return $this->issues;
    }

    protected function getCommand(): array
    {
        $phpcs = realpath(__DIR__ . '/../../vendor/squizlabs/php_codesniffer/bin/phpcs');
        if (!$phpcs) {
            throw new \Exception('phpcs binary not found.');
        }

        return [
            'php',
            '-d', 'error_reporting=E_ALL^E_DEPRECATED^E_USER_DEPRECATED',
            $phpcs,
            '--standard=moodle',
            '--report-json=' . $this->reportFile,
            '--extensions=php',
            '-p',
            '--no-cache',
            '--encoding=utf-8',
            $this->pluginPath,
        ];
    }

    private function parseOutput(): bool
    {
        if (!is_file($this->reportFile)) {
            $this->error = 'phpcs did not produce a JSON report file.';
            return false;
        }

        $output = @file_get_contents($this->reportFile);
        @unlink($this->reportFile);
        if (!$output) {
            $this->issues = [];
            return true;
        }

        $data = json_decode($output, true);
        if (!is_array($data) || !isset($data['files'])) {
            $this->error = 'Unable to parse phpcs JSON output.';
            return false;
        }

        foreach ($data['files'] as $path => $file) {
            if (empty($file['messages'])) {
                continue;
            }
            foreach ($file['messages'] as $message) {
                $severity = match ($message['type'] ?? 'ERROR') {
                    'ERROR' => Issue::SEVERITY_ERROR,
                    'WARNING' => Issue::SEVERITY_WARNING,
                    default => Issue::SEVERITY_TIP,
                };
                $this->issues[] = new Issue(
                    $message['source'] ?? 'phpcs',
                    $severity,
                    $message['message'] ?? 'Coding style issue',
                    $path,
                    $message['line'] ?? null,
                );
            }
        }

        return true;
    }
}
