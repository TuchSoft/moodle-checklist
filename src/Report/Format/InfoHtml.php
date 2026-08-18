<?php

namespace Tuchsoft\MoodleChecklist\Report\Format;

// The TableBuilder class is not needed for this library.
use Tuchsoft\MoodleChecklist\Report\Format\Base\AbstractFormat;
use Tuchsoft\MoodleChecklist\Report\Format\Base\HtmlFormatTrait;
use Tuchsoft\MoodleChecklist\Report\Format\Base\InfoFormatTrait;
use Tuchsoft\MoodleChecklist\Report\Format\Base\MdFormatTrait;
use Tuchsoft\MoodleChecklist\Report\Report;

class InfoHtml extends InfoMd
{
    use InfoFormatTrait;
    use HtmlFormatTrait;

    /**
     * Generates a Markdown report from a Report object.
     *
     * @param Report $report The report data to format.
     * @return string The Markdown string of the formatted report.
     */
    public function generate(Report $report): string
    {
        // Use MarkdownBuilder's h1() method for the main title.
        $this->builder->h1($report->getName());

        $this->generateSummary($report);
        $this->generateDetails($report);

        // The getMarkdown() method retrieves the final, built Markdown string.
        return $this->writeHtml();

    }

    /**
     * Returns a brief description of the report format.
     *
     * @return string
     */
    public static function getDesc(): string
    {
        return "Html detailed info";
    }
}
