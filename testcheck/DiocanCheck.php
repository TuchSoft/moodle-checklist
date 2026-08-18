<?php

namespace My\Testcheck;

use \Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use \Tuchsoft\MoodleChecklist\Check\AbstractCheck;

class DiocanCheck extends AbstractCheck
{

    use GetAllFile;

    protected function execute(): void
    {
        $files = $this->getAllFile(ext: ['php']);
        foreach ($files as $file) {
            $this->addError('dio-can', 'Diooooooo cane', $file);
        }
    }

    public static function getName(): string
    {
        return 'my.'.parent::getName();
    }
    
}