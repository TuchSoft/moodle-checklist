<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\MoodleChecklist\Report\Issue;
use Tuchsoft\MoodleChecklist\Report\Report;

class MoodleCiGruntStylelintProcess extends AbstractMoodleCiGruntProcess
{


    public function __construct(string $ploginRoot)
    {
        parent::__construct($ploginRoot, 'stylelint');
    }


    public function parseOutput(): bool
    {
        $output = $this->getStdout();
        if (empty($output)) {
            // No output means no issues were reported by stylelint.
            $this->issues = [];
            return true;
        }

        $lines = explode("\n", $output);
        $currentFile = null;

        $filePathRegex = '/^(?:[a-z0-9]+\/)+[a-z0-9-_]+\.s?css$/';
        $issueRegex = '/^(?<line>\d+):(?<col>\d+)\s+(?<symbol>\S+)\s+(?<msg>\S.+?)\s{2,}(?<code>\S.+?)$/';

        foreach ($lines as $line) {
            $line = trim($line); // Trim whitespace from the line

            if (empty($line)) {
                continue; // Skip empty lines
            }

            // Check if the line is a file path
            if (preg_match($filePathRegex, $line)) {
                $currentFile = $line;
                continue; // Move to the next line to find issues for this file
            }

            // If we have a current file and the line matches an issue pattern
            if ($currentFile !== null && preg_match($issueRegex, $line, $matches)) {

                $severity = match (mb_ord($matches['symbol'])) {
                    10006 => Report::SEVERITY_ERROR,
                    9888 => Report::SEVERITY_WARNING,
                    default => Report::SEVERITY_TIP
                };

                $this->issues[] = new Issue(
                    $matches['code'],
                    $severity,
                    $matches['msg'],
                    $currentFile,
                    (int) $matches['line']
                );
            } else {
                $currentFile = null;
            }
        }
        return true;
    }
}