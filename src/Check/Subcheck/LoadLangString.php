<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\MoodleChecklist\Action\AbstractAction;
use Tuchsoft\MoodleChecklist\Action\LangParser;
use Tuchsoft\MoodleChecklist\Action\PhpFileLoader;
use \Tuchsoft\MoodleChecklist\Action\TokenFinder;
use Tuchsoft\MoodleChecklist\Plugin;
use Tuchsoft\MoodleChecklist\Report\Report;


trait LoadLangString
{
    use BaseCheckTrait;

    protected ?array $langStrings = null;
    protected bool $langStringsFailed = false;

    public function loadLangString(): void
    {
        if (!$this->langStrings && !$this->langStringsFailed) {
            $langParser = new PhpFileLoader();
            $this->langStrings = $langParser->load("{$this->plugin->fullpath}/lang/en/{$this->plugin->component}.php", "string");
            if ($langParser->getLastError()) {
                $this->runtimeError("Cannot parse language file: ". $langParser->getLastError());
                $this->langStringsFailed = true;
            }
        }

    }



}