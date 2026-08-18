<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;

class TestCheck extends AbstractCheck
{

    use GetAllFile;

    protected function execute(): void
    {
        $files = $this->getAllFile(ext: ['php', 'json', 'js']);
        foreach ($files as $file) {
            $this->addError('hello-word', 'Change the title of your README.md document to match {token}', $file);
            $this->addWarning('hello-word', 'Heloooooo word', $file);
            $this->addTip('hello-word', 'Heloooooo word', $file);
        }
    }

    public static function getName(): string
    {
        return 'my.'.parent::getName();
    }
    
}