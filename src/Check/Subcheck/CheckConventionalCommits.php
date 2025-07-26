<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\MoodleChecklist\Action\ConventionalCommitValidator;
use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Check\Subcheck\BaseCheckTrait;

trait CheckConventionalCommits
{
    use BaseCheckTrait;
    use GitData;

    /**
     * Checks a list of commits for adherence to the Conventional Commits standard.
     *
     * @param array $commits A list of commit objects.
     * @param string $code The issue code for the report.
     * @param float $threshold The percentage of commits that must follow the standard.
     * @return void
     */
    public function checkConventionalCommits(array $commits, string $code, float $threshold = 0.8): void
    {
        if (empty($commits)) {
            return;
        }

        $validator = new ConventionalCommitValidator();

        $conventionalCommitRegex = '/^(feat|fix|docs|style|refactor|test|chore|ci|build): .*$/';
        $validCommits = 0;

        foreach ($commits as $commit) {
            $message = $commit['commit']['message'] ?? '';
            if ($validator->validate($message)) {
                $validCommits++;
            } else {
                $this->addTip('conventional-commit.message', $validator->getLastError());
            }
        }

        $adherence = $validCommits / count($commits);

        if ($adherence < $threshold) {
            $this->addWarning(
                $code,
                "Only " . round($adherence * 100) . "% of recent commits follow the `Conventional Commits` standard. Autmatic CHANGELOG generation will be difficult"
            );
        }
    }
}