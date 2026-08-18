<?php

namespace My\Testcheck;

use \Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use \Tuchsoft\MoodleChecklist\Check\AbstractCheck;

class HelloCheck extends AbstractCheck
{

    use GetAllFile;

    protected function execute(): void
    {
        $files = $this->getAllFile(ext: ['php']);
        foreach ($files as $file) {
            $this->addError('hello-word', 'Heloooooo word', $file);
        }
    }

    public static function getName(): string
    {
        return 'my.'.parent::getName();
    }
    
}