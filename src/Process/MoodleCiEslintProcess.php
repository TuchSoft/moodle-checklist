<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\IssueReporter\Issue;

class MoodleCiEslintProcess extends AbstractProcess
{
    /** @var Issue[] */
    private array $issues = [];

    /**
     * @param string[] $files
     */
    public function __construct(
        private array $files,
        private string $moodleRoot,
        private ?string $configPath = null,
    ) {
        parent::__construct();
    }

    public function execute(?float $timeout = 300.0): bool
    {
        if (empty($this->files)) {
            $this->issues = [];
            return true;
        }
        $ok = parent::execute($timeout);
        if (!$ok && $this->getExitCode() !== 1) {
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
        $eslint = $this->findEslint();
        if (!$eslint) {
            throw new \Exception('eslint not found. Install it in the Docker image or run `npm install eslint`.');
        }

        $cmd = [$eslint];
        if ($this->configPath) {
            $cmd[] = '--config=' . $this->configPath;
        }
        $cmd[] = '--format=json';
        $cmd[] = '--no-error-on-unmatched-pattern';
        foreach ($this->files as $file) {
            $cmd[] = $file;
        }

        return $cmd;
    }

    private function findEslint(): ?string
    {
        $candidates = [
            // Moodle root node_modules (preferred, avoids duplication in Docker)
            $this->moodleRoot . '/node_modules/.bin/eslint',
            $this->moodleRoot . '/node_modules/eslint/bin/eslint.js',
            // Moodle 5.1+ public docroot layout
            $this->moodleRoot . '/public/node_modules/.bin/eslint',
            $this->moodleRoot . '/public/node_modules/eslint/bin/eslint.js',
            // Moodle 5.2+ nested docroot layout
            $this->moodleRoot . '/../node_modules/.bin/eslint',
            $this->moodleRoot . '/../node_modules/eslint/bin/eslint.js',
            // Local fallback for standalone usage
            __DIR__ . '/../../node_modules/.bin/eslint',
            __DIR__ . '/../../node_modules/eslint/bin/eslint.js',
            'node_modules/.bin/eslint',
            'node_modules/eslint/bin/eslint.js',
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && is_file($real)) {
                return $real;
            }
        }
        return null;
    }

    private function parseOutput(): bool
    {
        $output = $this->getStdout();
        if (!$output) {
            $this->issues = [];
            return true;
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            $this->error = 'Unable to parse eslint JSON output.';
            return false;
        }

        foreach ($data as $result) {
            $path = $result['filePath'] ?? '';
            foreach ($result['messages'] ?? [] as $message) {
                $severity = match ($message['severity'] ?? 2) {
                    2 => Issue::SEVERITY_ERROR,
                    1 => Issue::SEVERITY_WARNING,
                    default => Issue::SEVERITY_TIP,
                };
                $this->issues[] = new Issue(
                    $message['ruleId'] ?? 'eslint',
                    $severity,
                    $message['message'] ?? 'JavaScript lint issue',
                    $path,
                    $message['line'] ?? null,
                );
            }
        }

        return true;
    }
}
