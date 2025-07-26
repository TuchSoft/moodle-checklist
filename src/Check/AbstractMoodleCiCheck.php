<?php

namespace Tuchsoft\MoodleChecklist\Check;

abstract class AbstractMoodleCiCheck extends AbstractCheck
{

    public function getName(): string
    {
        $classname = explode('\\', get_class($this));
        return 'moodle-plugin-ci.'.strtolower(str_replace('Check', '', array_pop($classname)));
    }
}