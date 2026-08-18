<?php

namespace Tuchsoft\MoodleChecklist\Report;







use Tuchsoft\MoodleChecklist\Report\Format\Info;
use Tuchsoft\MoodleChecklist\Report\Format\InfoMd;

class Reporter
{

    public static function registerFormats(string ...$formats): void
    {
        foreach ($formats as $format) {
            FormatFactory::register($format);
        }
    }


    public static function getOptionsDefinition(): array {
        $output = [];
        foreach ( FormatFactory::getRegisteredFormats() as $format) {
            foreach ($format::getOptionsDefinition() as $option) {
                $output[$option->getName()] = $option;
            }
        }
        return array_values($output);
    }


    /**
     * Generates and prints a single final report.
     *
     * @param string $reportType The report type to print (e.g., 'full', 'json').
     *
     * @return void
     */
    public static function printReport(Report $report, string $reportType, string $output = 'php://stdout', array $options = []): void
    {
        $reportClass = FormatFactory::createFormat($reportType, $options);
        file_put_contents($output, $reportClass->generate($report));
    }
}