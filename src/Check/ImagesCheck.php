<?php


namespace Tuchsoft\MoodleChecklist\Check;


use Tuchsoft\MoodleChecklist\Check\Subcheck\FileExist;

class ImagesCheck extends AbstractCheck
{

    use FileExist;

    protected function execute(): void
    {
        $moodleplugindir = $this->plugin->fullpath . '/.moodleplugin';

        if ($this->isActive(($code = 'moodleplugin-meta-dir'))) {
            $this->dirExist(
                $moodleplugindir,
                $code,
                "The `$moodleplugindir` directory is missing.");
        }


        if ($this->isActive(($code = 'poster-image'))) {
            $files = glob($moodleplugindir . '/*.{jpeg,png,jpg}', GLOB_BRACE);
            $posterregex = '/' . preg_quote($this->plugin->component, '/') . '_poster\.(?:jpeg|png|jpg)$/i';
            $posterfile = array_filter($files, fn($file) => preg_match($posterregex, basename($file)));
            if (empty($posterfile)) {
                $this->addWarning(
                    $code,
                    "A poster image is missing: `.moodleplugin/[xx_]{$this->plugin->component}_poster.png|jpg`"
                );
            }
        }


        if ($this->isActive(($code = 'screenshot-dir'))) {
            $screenshotdir = $moodleplugindir . '/screenshots';
            if (!is_dir($screenshotdir)) {
                $this->addError(
                    $code,
                    'The `.moodleplugin/screenshots` directory is missing'
                );
            }
        }


        if ($this->isActive(($code = 'screenshots'))) {
            $screenshotdir = $moodleplugindir . '/screenshots';
            $screenshots = glob($screenshotdir . '/*.{jpeg,png,jpg}', GLOB_BRACE);
            if (empty($screenshots)) {
                $this->addError(
                    $code,
                    'No screenshots were found in `.moodleplugin/screenshots` (must be jpeg\png)'
                );
            }
        }
    }
}