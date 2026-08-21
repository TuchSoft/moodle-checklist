<?php

namespace Tuchsoft\MoodleChecklist\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tuchsoft\MoodleChecklist\Process\Image\CwebpProcess;
use Tuchsoft\MoodleChecklist\Process\Image\GifsicleProcess;
use Tuchsoft\MoodleChecklist\Process\Image\MozjpegProcess;
use Tuchsoft\MoodleChecklist\Process\Image\PngquantProcess;
use Tuchsoft\MoodleChecklist\Process\Image\SvgoProcess;

class ImageOptimizerProcessTest extends TestCase
{
    public function testOptimizersAreAvailable(): void
    {
        $this->assertTrue((new PngquantProcess(''))->isAvailable(), 'pngquant should be available');
        $this->assertTrue((new MozjpegProcess(''))->isAvailable(), 'mozjpeg should be available');
        $this->assertTrue((new SvgoProcess(''))->isAvailable(), 'svgo should be available');
        $this->assertTrue((new GifsicleProcess(''))->isAvailable(), 'gifsicle should be available');
        $this->assertTrue((new CwebpProcess(''))->isAvailable(), 'cwebp should be available');
    }
}
