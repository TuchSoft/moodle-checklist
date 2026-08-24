<?php

namespace Tuchsoft\MoodleChecklist\Utils;

use SimpleXMLElement;

/**
 * Parses a plugin's thirdpartylibs.xml to discover vendored library paths.
 *
 * Moodle plugins declare third-party libraries in a file named thirdpartylibs.xml
 * at the plugin root. The format is:
 *
 * <libraries>
 *   <library location="amd/src/select2" />
 *   <library location="lib/htmlpurifier" />
 * </libraries>
 *
 * This parser returns the relative paths so that scanners and fixers can skip them.
 */
class ThirdPartyLibsParser
{
    /**
     * Read thirdpartylibs.xml and return declared library locations.
     *
     * @param string $pluginRoot Absolute path to the plugin directory.
     * @return array<int,string> Relative paths of declared libraries.
     */
    public static function parse(string $pluginRoot): array
    {
        $file = rtrim($pluginRoot, '/\\') . '/thirdpartylibs.xml';
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $xml = @file_get_contents($file);
        if ($xml === false) {
            return [];
        }

        $previousLibxmlUseInternalErrors = libxml_use_internal_errors(true);
        $previousLibxmlDisableEntityLoader = libxml_disable_entity_loader(true);

        $doc = @simplexml_load_string($xml);

        libxml_use_internal_errors($previousLibxmlUseInternalErrors);
        libxml_disable_entity_loader($previousLibxmlDisableEntityLoader);

        if ($doc === false) {
            return [];
        }

        $locations = [];
        foreach ($doc->library as $library) {
            $location = (string) (isset($library['location']) ? $library['location'] : '');
            if ($location === '') {
                continue;
            }
            $locations[] = $location;
        }

        return $locations;
    }
}
