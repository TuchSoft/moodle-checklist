<?php


namespace Tuchsoft\MoodleChecklist\Check;


use Markdown\Escape\MarkdownEscape;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckLangStringInFile;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckStringInFile;
use Tuchsoft\MoodleChecklist\Check\Subcheck\LintMarkdown;
use Tuchsoft\MoodleChecklist\Check\Subcheck\LoadAuthors;
use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Settings;

class ReadmeCheck extends AbstractSingleFileCheck
{

    use CheckStringInFile;
    use CheckLangStringInFile;
    use LintMarkdown;
    use LoadAuthors;

    private MarkdownEscape $escape;


    public function __construct(Settings $settings)
    {
        parent::__construct($settings);
        $this->path = "{$this->plugin->fullpath}/README.md";
        $this->mimeType = ['text/markdown', 'text/html'];

        $this->escape = MarkdownEscape::gfm();
    }


    protected function execute(): void
    {
        //Common file valifation
        parent::execute();




        //Default markdown linter
        if ($this->isActive(($code = 'lint'))) {
            $this->lintMarkdown($this->path,
                ['--rc-path='.realpath(__DIR__.'/../Config/markdown-lint.json')],
                Report::SEVERITY_TIP,
                Report::SEVERITY_WARNING
            );
        }



        if ($this->isActive(($code = 'standard-readme'))) {
            //Standard README (https://github.com/RichardLitt/standard-readme)
            $this->lintMarkdown(
                $this->path,
                ['--rc-path='.realpath(__DIR__.'/../Config/standard-readme.json')],
                Report::SEVERITY_ERROR);
        }



        //Document tile
        //https://github.com/RichardLitt/standard-readme/blob/main/spec.md#title
        if ($this->isActive(($code = 'title'))) {
            $this->loadLangString();
            $token = "# {$this->langStrings['pluginname']} _(moodle-" .
                $this->escape->escapeContent($this->plugin->component) .
                ')_';
            $this->checkStringInFile(
                $token,
                'README.md',
                $code,
                "The #1 heading of the README should be: '{token}'"
            );
        }


        //Get the token finder
        if ($this->isActive(($code = 'shortdesc'))) {
            $this->checkLangStringInFile(
                'plugindesc',
                'README.md',
                $code,
                "The document should include the same description defined in lang files (\$string['plugindesc'])"
            );
        }


        //Load all the author in docblock comments
        //Check that all the author in the code are also present in the README
        if ($this->isActive(($code = 'authors'))) {
            $this->loadAuthors();
            $authors = array_unique(array_map(fn($a) => $a['name'], $this->authors));
            $this->checkStringInFile(
                $authors,
                'README.md',
                $code,
                'Not all authors found in the codebase are listed in the document ({token})'
            );
        }


        //TODO: Check all the badge
        //TODO: Check all the images
        //TODO: Check all the link
    }


}