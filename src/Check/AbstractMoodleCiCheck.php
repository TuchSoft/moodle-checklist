<?php

namespace Tuchsoft\MoodleChecklist\Check;

abstract class AbstractMoodleCiCheck extends AbstractCheck
{

    public static function getName(): string
    {
        return 'moodle-plugin-ci.'.parent::getName();
    }
}