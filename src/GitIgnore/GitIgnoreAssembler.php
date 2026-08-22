<?php

namespace Tuchsoft\MoodleChecklist\GitIgnore;

/**
 * Parses and re-assembles a .gitignore file managed by gitignore.io-style markers.
 *
 * The file is treated as a sequence of blocks. Each block starts with a line like:
 *   # Created by <origin>
 * and ends with a line like:
 *   # End of <origin>
 *
 * Recognised origins:
 *   - https://www.toptal.com/developers/gitignore/api/...   -> gitignore.io template
 *   - moodle-checklist                                      -> Moodle-specific additions
 *
 * Everything outside those markers is user-defined and is preserved in the
 * "### Project defined ###" section.
 */
class GitIgnoreAssembler
{
    /** @var string[] */
    private array $moodlePatterns;

    private string $moodleHeader = "# Created by moodle-checklist";

    private string $moodleFooter = "# End of moodle-checklist";

    /**
     * @param string[] $moodlePatterns Patterns to inject into the Moodle block.
     */
    public function __construct(array $moodlePatterns)
    {
        $this->moodlePatterns = $moodlePatterns;
    }

    /**
     * Build a complete .gitignore from a gitignore.io template and an optional
     * existing file.
     *
     * @param string      $template  The gitignore.io template content.
     * @param string|null $existing  The current .gitignore content, if any.
     */
    public function assemble(string $template, ?string $existing = null): string
    {
        $parts = [];

        $parts[] = $this->topComment();
        $parts[] = $this->normalize($template);
        $parts[] = $this->buildMoodleBlock();

        $userSection = $this->extractUserSection($existing);
        if ($userSection !== '') {
            $parts[] = "### Project defined ###\n" . $userSection;
        }

        return $this->joinParts($parts);
    }

    /**
     * Top explanatory comment telling humans not to touch the markers.
     */
    private function topComment(): string
    {
        return <<<'COMMENT'
# This .gitignore file is auto-managed by moodle-checklist.
#
# It is built from three parts, in order:
#   1. A template fetched from https://www.toptal.com/developers/gitignore
#   2. Moodle-specific additions injected by moodle-checklist
#   3. Project-defined patterns at the end of the file
#
# Do not edit the "Created by" / "End of" markers or section headers,
# because they are used by the fixer to keep the file up to date.
# Add your own patterns only in the Project defined section below.
COMMENT;
    }

    /**
     * Builds the Moodle-specific block.
     */
    private function buildMoodleBlock(): string
    {
        $lines = [$this->moodleHeader];

        $patterns = $this->moodlePatterns;
        if (!empty($patterns)) {
            $lines[] = "";
            $lines[] = "### Moodle ###";
            $lines = array_merge($lines, $patterns);
        }

        $lines[] = "";
        $lines[] = $this->moodleFooter;

        return implode("\n", $lines);
    }

    /**
     * Extract user-defined content from an existing .gitignore.
     *
     * The file is treated as a sequence of managed sections:
     *   - top explanatory comment (our known header)
     *   - gitignore.io template block
     *   - Moodle block
     *   - "### Project defined ###" section
     *
     * Everything inside those markers is stripped; the content of the
     * "### Project defined ###" section is preserved as user content.
     */
    private function extractUserSection(?string $existing): string
    {
        if ($existing === null || trim($existing) === '') {
            return '';
        }

        $content = $existing;

        // Strip our known header if it appears at the start of the file.
        $content = $this->stripManagedHeader($content);

        // Strip managed blocks (gitignore.io, moodle-checklist, etc.).
        $content = $this->stripManagedBlocks($content);

        // If a "Project defined" section remains, extract its body.
        $content = $this->extractProjectDefinedSection($content);

        return trim($content);
    }

    /**
     * Strip the auto-managed header comment from the start of the content.
     */
    private function stripManagedHeader(string $content): string
    {
        $header = $this->topComment();
        $headerPattern = '/^\s*' . preg_quote($header, '/') . '\s*/s';
        $content = preg_replace($headerPattern, '', $content);

        return $content;
    }

    /**
     * Strip managed blocks delimited by "# Created by ..." / "# End of ..." markers.
     */
    private function stripManagedBlocks(string $content): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $resultLines = [];
        $insideManagedBlock = false;

        foreach ($lines as $line) {
            if ($this->isManagedBlockStart($line)) {
                $insideManagedBlock = true;
                continue;
            }

            if ($insideManagedBlock && $this->isManagedBlockEnd($line)) {
                $insideManagedBlock = false;
                continue;
            }

            if (!$insideManagedBlock) {
                $resultLines[] = $line;
            }
        }

        return implode("\n", $resultLines);
    }

    /**
     * Extract the body of the "### Project defined ###" section if present.
     */
    private function extractProjectDefinedSection(string $content): string
    {
        $marker = '### Project defined ###';
        $pos = strpos($content, $marker);

        if ($pos === false) {
            return $content;
        }

        return substr($content, $pos + strlen($marker));
    }

    /**
     * Whether a line marks the start of a managed block.
     *
     * Recognises gitignore.io blocks and our own moodle-checklist block.
     */
    private function isManagedBlockStart(string $line): bool
    {
        $line = trim($line);

        return $line === $this->moodleHeader
            || str_starts_with($line, '# Created by https://www.toptal.com/');
    }

    /**
     * Whether a line marks the end of a managed block.
     */
    private function isManagedBlockEnd(string $line): bool
    {
        $line = trim($line);

        return $line === $this->moodleFooter
            || str_starts_with($line, '# End of https://www.toptal.com/');
    }

    /**
     * Trim leading/trailing whitespace and ensure a single trailing newline.
     */
    private function normalize(string $content): string
    {
        $content = trim($content);

        return $content . "\n";
    }

    /**
     * Join non-empty parts with a single blank line between them.
     *
     * @param string[] $parts
     */
    private function joinParts(array $parts): string
    {
        $parts = array_filter($parts, fn ($p) => trim($p) !== '');
        $parts = array_map(fn ($p) => rtrim($p, "\n") . "\n", $parts);

        return implode("\n", $parts);
    }
}
