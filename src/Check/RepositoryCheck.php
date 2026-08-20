<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckConventionalCommits;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckSemanticVersioning;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GitData;
use Tuchsoft\MoodleChecklist\Check\Subcheck\LoadLangString;
use Tuchsoft\MoodleChecklist\Settings;

class RepositoryCheck extends AbstractCheck
{
    use GitData;
    use CheckConventionalCommits;
    use CheckSemanticVersioning;
    use LoadLangString;

    /**
     * GitCheck constructor.
     *
     * @param Plugin $plugin
     */
    public function __construct(Settings $settings)
    {
        $this->path = 'Git Repository';
        parent::__construct($settings);
    }

    /**
     * Executes all Git repository checks.
     *
     * @return void
     */
    protected function execute(): void
    {
        $this->loadGitData();
        if ($this->gitDataFailed) {
            return;
        }

        if ($this->isActive(($code = 'repo-is-public'))) {
            if ($this->repoInfo['private'] === true) {
                $this->addError(
                    $code,
                    'The repository is private. Moodle plugins should be hosted in a public repository.',
                );
            }
        }

        if ($this->isActive(($code = 'correct-repo-name'))) {
            $expectedName = 'moodle-' . $this->plugin->component;
            if ($this->repoInfo['name'] !== $expectedName) {
                $this->addWarning(
                    $code,
                    "The repository name '{$this->repoInfo['name']}' does not follow the standard naming convention of '{$expectedName}'."
                );
            }
        }

        if ($this->isActive(($code = 'repo-description-matches-lang'))) {
            $this->loadLangString();
            if ($this->langStrings && ($this->repoInfo['description'] !== $this->langStrings['plugindesc'])) {
                $this->addWarning(
                    $code,
                    "The repository description does not match the 'plugindesc' string from the language file."
                );
            }
        }

        if ($this->isActive(($code = 'default-branch-main'))) {
            if ($this->repoInfo['default_branch'] !== 'main') {
                $this->addWarning(
                    $code,
                    "The default branch is not named 'main'. It is recommended to use 'main' instead of 'master'."

                );
            }
        }

        if ($this->isActive(($code = 'tags-semver'))) {
            if ($this->repoTags) {
                $tagNames = array_column($this->repoTags, 'name');
                $this->checkSemVer($tagNames, $code);
            } else {
                $this->addTip(
                    $code,
                    'No tags found. It is recommended to create tags for releases following Semantic Versioning.'
                );
            }
        }

        if ($this->isActive(($code = 'conventional-commits'))) {
            if ($this->repoCommits) {
                $this->checkConventionalCommits($this->repoCommits, $code);
            } else {
                $this->addTip(
                    $code,
                    'No commits found. A well-maintained repository should use clear commit messages.',
                );
            }
        }
    }
}