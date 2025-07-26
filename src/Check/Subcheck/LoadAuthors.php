<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;


use Tuchsoft\MoodleChecklist\Action\DocblockParser;



trait LoadAuthors
{
    use BaseCheckTrait;

    protected ?array $authors = null;
    protected ?array $licenses = null;
    protected bool $authorsFailed = false;

    public function loadAuthors(): void
    {
        if (!$this->authors && !$this->authorsFailed) {
            $parser = new DocblockParser($this->plugin->fullpath);
            $this->authors = $parser->parse();
            if ($parser->getLastError()) {
                $this->runtimeError("Cannot parse authors: ". $parser->getLastError());
                $this->authorsFailed = true;
            }
        }

    }



}