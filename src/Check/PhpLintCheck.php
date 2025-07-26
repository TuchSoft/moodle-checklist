<?php

namespace Tuchsoft\MoodleChecklist\Check;


use JakubOnderka\PhpParallelLint\Error;
use JakubOnderka\PhpParallelLint\Manager;
use JakubOnderka\PhpParallelLint\Settings;

use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;

class PhpLintCheck extends AbstractMoodleCiCheck
{

    use GetAllFile;

    protected function _execute(): void
    {
        if ($this->isActive(($code = 'php-lint'))) {
            $settings = new Settings();
            $manager = new Manager();


            $settings->format = Settings::FORMAT_JSON;
            $settings->colors = Settings::DISABLED;
            $settings->addPaths($this->getAllFile(ext: ['php']));
            $settings->showDeprecated = true;

            try {
                ob_start();
                $result = $manager->run($settings);
                ob_end_clean();
            } catch (\Exception $e) {
                $this->runtimeError("Failed to run PHP Lint: " . $e->getMessage());
            }


            if ($result) {
                /** @var Error $error */
                foreach ($result->getErrors() as $error) {
                    $this->addError(
                        $code,
                        $error->getMessage(),
                        $error->getFilePath()
                    );
                }
            }
        }
    }
}