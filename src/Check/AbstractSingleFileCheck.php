<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileEncoding;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileMimeType;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileSize;
use Tuchsoft\MoodleChecklist\Check\Subcheck\FileExist;
use Tuchsoft\MoodleChecklist\Settings;


abstract class AbstractSingleFileCheck extends AbstractCheck
{
    use FileExist;
    use CheckFileSize;
    use CheckFileEncoding;
    use CheckFileMimeType;

    protected string $filename;
    protected array $mimeType = [];
    protected string $encoding = 'UTF-8';
    protected int $minAllowedSize = 0;
    protected int $maxAllowedSize = PHP_INT_MAX;

    /**
     * AbstractSingleFileCheck constructor.
     *
     * @param Settings $settings settings class
     */
    public function __construct(protected Settings $settings)
    {
        parent::__construct($settings);
        $this->filename = basename($this->path);
    }

    /**
     * Executes the standard file checks defined in this abstract class.
     * Concrete implementations can override this to add more specific checks
     * or call parent::execute() to run these standard checks first.
     */
    protected function _execute(): void
    {
        $exist = $this->fileExist(
            $this->path, // Use $this->path instead of $this->file
            'file-not-found',
            "File '{$this->filename}' is missing."
        );

        if (!$exist) {
            return;
        }

        if ($this->isActive(($code = 'file-size'))) {
            $this->checkFileSize($this->path, $code, $this->minAllowedSize, $this->maxAllowedSize);
        }

        if ($this->isActive(($code = 'file-encoding'))) {
            $this->checkFileEncoding($this->path, $code, $this->encoding);
        }

        if ($this->isActive(($code = 'file-mimetype'))) {
            $this->checkFileMimeType($this->path, $code, $this->mimeType);
        }
    }
}