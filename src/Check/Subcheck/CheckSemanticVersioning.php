<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

trait CheckSemanticVersioning
{
    use BaseCheckTrait;
    use GitData;

    /**
     * Checks if a list of version strings follows the Semantic Versioning standard.
     *
     * @param array $versions A list of version strings (e.g., 'v1.2.3').
     * @param string $code The issue code for the report.
     * @return void
     */
    public function checkSemVer(array $versions, string $code): void
    {
        $semverRegex = '/^v?(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/';

        foreach ($versions as $version) {
            if (!preg_match($semverRegex, $version)) {
                $this->addError(
                    $code,
                    "Tag '{$version}' does not follow semantic versioning."
                );
            }
        }
    }
}