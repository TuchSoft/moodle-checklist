<?php

namespace Tuchsoft\MoodleChecklist\Tests\Unit\GitIgnore;

use PHPUnit\Framework\TestCase;
use Tuchsoft\MoodleChecklist\GitIgnore\GitIgnoreAssembler;

class GitIgnoreAssemblerTest extends TestCase
{
    private GitIgnoreAssembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new GitIgnoreAssembler(['vendor/', 'node_modules/']);
    }

    public function testAssembleOnEmptyFile(): void
    {
        $template = "# Created by https://www.toptal.com/developers/gitignore/api/foo\n### Foo ###\nbar\n# End of https://www.toptal.com/developers/gitignore/api/foo";
        $result = $this->assembler->assemble($template);

        $this->assertStringContainsString('# This .gitignore file is auto-managed by moodle-checklist.', $result);
        $this->assertStringContainsString($template, $result);
        $this->assertStringContainsString("# Created by moodle-checklist\n\n### Moodle ###\nvendor/\nnode_modules/\n\n# End of moodle-checklist", $result);
        $this->assertStringNotContainsString("\n### Project defined ###\n", $result);
    }

    public function testAssemblePreservesUserDefinedPatterns(): void
    {
        $template = "# Created by https://www.toptal.com/developers/gitignore/api/foo\nfoo\n# End of https://www.toptal.com/developers/gitignore/api/foo";
        $existing = "/my-custom-dir\n*.secret\n";
        $result = $this->assembler->assemble($template, $existing);

        $this->assertStringContainsString('### Project defined ###', $result);
        $this->assertStringContainsString("/my-custom-dir\n*.secret", $result);
    }

    public function testAssembleReplacesManagedBlocksOnReRun(): void
    {
        $template = "# Created by https://www.toptal.com/developers/gitignore/api/foo\nnew-foo\n# End of https://www.toptal.com/developers/gitignore/api/foo";
        $existing = <<<'GITIGNORE'
# This .gitignore file is auto-managed by moodle-checklist.

# Created by https://www.toptal.com/developers/gitignore/api/foo
old-foo
# End of https://www.toptal.com/developers/gitignore/api/foo

# Created by moodle-checklist

### Moodle ###
vendor/
node_modules/

# End of moodle-checklist

### Project defined ###
/my-stuff
GITIGNORE;

        $result = $this->assembler->assemble($template, $existing);

        $this->assertStringContainsString('new-foo', $result);
        $this->assertStringNotContainsString('old-foo', $result);
        $this->assertStringContainsString('/my-stuff', $result);
        $this->assertSame(1, substr_count($result, '# Created by https://www.toptal.com/'));
        $this->assertSame(1, substr_count($result, '# Created by moodle-checklist'));
        $this->assertSame(1, substr_count($result, '### Project defined ###'));
    }

    public function testAssembleDetectsExistingGitignoreIoBlock(): void
    {
        $template = "# Created by https://www.toptal.com/developers/gitignore/api/foo\nupdated\n# End of https://www.toptal.com/developers/gitignore/api/foo";
        $existing = <<<'GITIGNORE'
# Created by https://www.toptal.com/developers/gitignore/api/foo
existing
# End of https://www.toptal.com/developers/gitignore/api/foo

/my-user-pattern
GITIGNORE;

        $result = $this->assembler->assemble($template, $existing);

        $this->assertStringContainsString('updated', $result);
        $this->assertStringNotContainsString('existing', $result);
        $this->assertStringContainsString('/my-user-pattern', $result);
        $this->assertStringContainsString('### Project defined ###', $result);
    }

    public function testAssemblePreservesUserCommentsAndBlankLines(): void
    {
        $template = "# Created by https://www.toptal.com/developers/gitignore/api/foo\nfoo\n# End of https://www.toptal.com/developers/gitignore/api/foo";
        $existing = "# my comment\n\n/my-dir\n";
        $result = $this->assembler->assemble($template, $existing);

        $this->assertStringContainsString("### Project defined ###\n# my comment\n\n/my-dir", $result);
    }

    public function testAssembleDoesNotStripUserCreatedByComments(): void
    {
        $template = "# Created by https://www.toptal.com/developers/gitignore/api/foo\nfoo\n# End of https://www.toptal.com/developers/gitignore/api/foo";
        $existing = "# Created by hand\n*.secret\n# End of hand\n";
        $result = $this->assembler->assemble($template, $existing);

        $this->assertStringContainsString('### Project defined ###', $result);
        $this->assertStringContainsString('# Created by hand', $result);
        $this->assertStringContainsString('# End of hand', $result);
        $this->assertStringContainsString('*.secret', $result);
    }
}
