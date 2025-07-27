<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\MoodleChecklist\Action\GitHubApi;

trait GitData
{
    use BaseCheckTrait;
    use FileExist;

    protected ?array $repoInfo = null;
    protected ?array $repoTags = null;
    protected ?array $repoCommits = null;
    protected ?array $repoTopics = null;
    protected bool $gitDataFailed = false;

    /**
     * Loads and caches all necessary Git repository data from the GitHub API.
     *
     * @return void
     */
    public function loadGitData(): void
    {
        if ($this->repoInfo || $this->gitDataFailed) {
            return;
        }

        $this->dirExist(
            "{$this->plugin->fullpath}/.git/",
            'missing_repository',
            "The '.git' directory is missing",
        );

        $configFilePath = "{$this->plugin->fullpath}/.git/config";

        $this->fileExist(
            $configFilePath,
            'missing_repository',
            "The '.git/config' file is missing, repository is corrupted"
        );

        $config = parse_ini_file($configFilePath, true);

        if (!isset($config['remote origin'], $config['remote origin']['url'])) {
            $this->addError(
                'missing_origin',
                "Missing 'remote `origin`', either is not configured or the name is different",
            );
            return;
        }

        $repoUrl = $config['remote origin']['url'];


        $github = new GitHubApi();
        $this->repoInfo = $github->getRepoInfo($repoUrl);

        if (!$this->repoInfo) {
            $this->runtimeError('Failed to fetch repository information: ' . $github->getLastError());
            $this->gitDataFailed = true;
            return;
        }

        $this->repoTags = $github->getRepoTags($repoUrl);
        if (!$this->repoTags) {
            $this->runtimeError('Failed to fetch repository tags: ' . $github->getLastError());
        }

        $this->repoCommits = $github->getRepoCommits($repoUrl);
        if (!$this->repoCommits) {
            $this->runtimeError('Failed to fetch repository commits: ' . $github->getLastError());
        }

        $this->repoTopics = $github->getRepoTopics($repoUrl);


    }
}