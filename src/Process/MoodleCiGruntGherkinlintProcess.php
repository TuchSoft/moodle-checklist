<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\MoodleChecklist\Report\Issue;
use Tuchsoft\MoodleChecklist\Report\Report;

class MoodleCiGruntGherkinlintProcess extends AbstractMoodleCiGruntProcess
{

    protected const REGEX = '/^(?<line>\d+)\s+(?<message>.*?)\s+(?<code>[a-zA-Z0-9-]+)\s*$/';


    public function __construct(string $ploginRoot)
    {
        parent::__construct($ploginRoot, 'gherkinlint');
    }


    public function parseOutput(): bool
    {
        $output = preg_replace('/\x1b\[\d+(?:;\d+)*[A-Za-z]/', '', $this->stderr);
        if (empty($output)) {
            // No output means no issues were reported by stylelint.
            $this->issues = [];
            return true;
        }

        $issues = [];
        $lines = explode("\n", $output);
        $currentFile = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Check if the line is a file path (starts with '/')
            if (str_starts_with($line, '/')) {
                $currentFile = $line;
            } else {
                // Attempt to match the issue details
                if (preg_match(self::REGEX, $line, $matches)) {
                    // Ensure a file was set before trying to create an issue
                    if (!empty($currentFile)) {
                        $lineNumber = (int)$matches[1];
                        $message = trim($matches[2]); // Trim message to remove trailing spaces
                        $issueCode = $matches[3];

                        $this->issues[] = new Issue($issueCode, Report::SEVERITY_ERROR, $message, $currentFile, $lineNumber );

                    }
                }
            }
        }

        return true;
    }
}