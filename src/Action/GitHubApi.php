<?php

namespace Tuchsoft\MoodleChecklist\Action;

class GitHubApi extends AbstractAction
{
    /**
     * @var string|null The GitHub personal access token.
     */
    private ?string $token;

    /**
     * @var array Cache for API responses.
     */
    private array $cache = [];

    /**
     * @var string The base URL for the GitHub API.
     */
    private string $baseUrl = 'https://api.github.com';

    /**
     * GitHubApi constructor.
     *
     * @param string|null $token The GitHub personal access token.
     */
    public function __construct(?string $token = null)
    {
        parent::__construct();
        $this->token = $token ?? getenv('GITHUB_TOKEN') ?? null;
    }

    /**
     * Extracts the owner and repository name from a full Git URL.
     *
     * @param string $url The full Git URL.
     * @return array|null An array containing ['owner', 'repo'], or null on failure.
     */
    private function parseGitUrl(string $url): ?array
    {
        // Handle https://github.com/owner/repo.git
        if (preg_match('/github\.com\/([^\/]+)\/([^\/]+)\.git$/', $url, $matches)) {
            return ['owner' => $matches[1], 'repo' => $matches[2]];
        }
        // Handle https://github.com/owner/repo
        if (preg_match('/github\.com\/([^\/]+)\/([^\/]+)$/', $url, $matches)) {
            // The repo name might have a .git suffix, remove it if it exists.
            return ['owner' => $matches[1], 'repo' => str_replace('.git', '', $matches[2])];
        }

        $this->lastError = "Invalid Git URL format: {$url}";
        return null;
    }

    /**
     * Makes an HTTP GET request to the GitHub API.
     *
     * @param string $endpoint The API endpoint.
     * @param array $params Query parameters.
     * @return array|null The parsed JSON response, or null on failure.
     */
    private function get(string $endpoint, array $params = []): ?array
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        if (isset($this->cache[$url])) {
            return $this->cache[$url];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = [
            'Accept: application/vnd.github.v3+json',
            'User-Agent: tuchsoft-moodle-checklist'
        ];

        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $this->lastError = "GitHub API error: HTTP code {$httpCode} for endpoint {$endpoint}";
            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastError = "Failed to parse JSON response from GitHub API.: $response";
            return null;
        }

        $this->cache[$url] = $data;
        return $data;
    }

    /**
     * Fetches repository information.
     *
     * @param string $gitUrl The full Git URL.
     * @return array|null The repository data, or null on failure.
     */
    public function getRepoInfo(string $gitUrl): ?array
    {
        $parsedUrl = $this->parseGitUrl($gitUrl);
        if (!$parsedUrl) {
            return null;
        }
        return $this->get("/repos/{$parsedUrl['owner']}/{$parsedUrl['repo']}");
    }

    /**
     * Fetches a list of all tags/releases.
     *
     * @param string $gitUrl The full Git URL.
     * @return array|null A list of tags, or null on failure.
     */
    public function getRepoTags(string $gitUrl): ?array
    {
        $parsedUrl = $this->parseGitUrl($gitUrl);
        if (!$parsedUrl) {
            return null;
        }
        return $this->get("/repos/{$parsedUrl['owner']}/{$parsedUrl['repo']}/tags");
    }

    /**
     * Fetches a list of the most recent commits.
     *
     * @param string $gitUrl The full Git URL.
     * @param int $limit The number of commits to fetch.
     * @return array|null A list of commits, or null on failure.
     */
    public function getRepoCommits(string $gitUrl, int $limit = 20): ?array
    {
        $parsedUrl = $this->parseGitUrl($gitUrl);
        if (!$parsedUrl) {
            return null;
        }
        return $this->get("/repos/{$parsedUrl['owner']}/{$parsedUrl['repo']}/commits", ['per_page' => $limit]);
    }

    /**
     * Fetches the list of topics associated with the repository.
     *
     * @param string $gitUrl The full Git URL.
     * @return array|null A list of topics, or null on failure.
     */
    public function getRepoTopics(string $gitUrl): ?array
    {
        $parsedUrl = $this->parseGitUrl($gitUrl);
        if (!$parsedUrl) {
            return null;
        }
        $response = $this->get("/repos/{$parsedUrl['owner']}/{$parsedUrl['repo']}");
        return $response['topics'] ?? null;
    }

    /**
     * Checks for the existence of specific files in the repository.
     *
     * @param string $gitUrl The full Git URL.
     * @param string $path The file path to check.
     * @return bool True if the file exists, false otherwise.
     */
    public function fileExists(string $gitUrl, string $path): bool
    {
        $parsedUrl = $this->parseGitUrl($gitUrl);
        if (!$parsedUrl) {
            return false;
        }
        $response = $this->get("/repos/{$parsedUrl['owner']}/{$parsedUrl['repo']}/contents/{$path}");
        return $response !== null && !isset($response['message']);
    }
}