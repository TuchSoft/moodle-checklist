<?php

namespace Tuchsoft\MoodleChecklist\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use Tuchsoft\MoodleChecklist\Utils\ThirdPartyLibsParser;

class ThirdPartyLibsParserTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/mcl_thirdparty_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->tmpDir);
        }
    }

    public function testMissingFileReturnsEmptyArray(): void
    {
        $result = ThirdPartyLibsParser::parse($this->tmpDir);
        $this->assertSame([], $result);
    }

    public function testValidXmlWithMultipleLibraries(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<libraries>
    <library location="amd/src/select2" />
    <library location="lib/htmlpurifier" />
    <library location="vendor/foo" />
</libraries>
XML;
        file_put_contents($this->tmpDir . '/thirdpartylibs.xml', $xml);

        $result = ThirdPartyLibsParser::parse($this->tmpDir);
        $this->assertSame(['amd/src/select2', 'lib/htmlpurifier', 'vendor/foo'], $result);
    }

    public function testEmptyLibrariesTagReturnsEmptyArray(): void
    {
        $xml = '<?xml version="1.0"?><libraries></libraries>';
        file_put_contents($this->tmpDir . '/thirdpartylibs.xml', $xml);

        $result = ThirdPartyLibsParser::parse($this->tmpDir);
        $this->assertSame([], $result);
    }

    public function testMalformedXmlReturnsEmptyArray(): void
    {
        file_put_contents($this->tmpDir . '/thirdpartylibs.xml', 'not xml at all');

        $result = ThirdPartyLibsParser::parse($this->tmpDir);
        $this->assertSame([], $result);
    }

    public function testIgnoresLibraryWithoutLocationAttribute(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<libraries>
    <library location="amd/src/select2" />
    <library />
    <library location="lib/htmlpurifier" />
</libraries>
XML;
        file_put_contents($this->tmpDir . '/thirdpartylibs.xml', $xml);

        $result = ThirdPartyLibsParser::parse($this->tmpDir);
        $this->assertSame(['amd/src/select2', 'lib/htmlpurifier'], $result);
    }
}
