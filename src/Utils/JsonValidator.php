<?php

namespace Tuchsoft\MoodleChecklist\Utils;

use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Util\HttpDownloader;
use Seld\JsonLint\ParsingException;


class JsonValidator extends JsonFile
{


    public static function validate($content)
    {
        return self::validateSyntax($content);
    }

}