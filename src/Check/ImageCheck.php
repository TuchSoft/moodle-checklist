<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileMimeType;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileSize;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Process\Image\AbstractImageOptimizerProcess;
use Tuchsoft\MoodleChecklist\Process\Image\CwebpProcess;
use Tuchsoft\MoodleChecklist\Process\Image\GifsicleProcess;
use Tuchsoft\MoodleChecklist\Process\Image\MozjpegProcess;
use Tuchsoft\MoodleChecklist\Process\Image\PngquantProcess;
use Tuchsoft\MoodleChecklist\Process\Image\SvgoProcess;

class ImageCheck extends AbstractCheck
{
    use GetAllFile;
    use CheckFileMimeType;
    use CheckFileSize;

    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
    private const KNOWN_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp', 'tiff', 'tif', 'psd', 'ico', 'raw'];
    private const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/svg+xml',
        'image/webp',
    ];
    private const MAX_FILE_SIZE = 512000; // 500 KB
    private const MAX_DIMENSION = 2048;
    private const MAX_PNG_SIZE_FOR_EFFICIENCY = 200000; // 200 KB

    public function canFix(): bool
    {
        return (new PngquantProcess(''))->isAvailable()
            || (new MozjpegProcess(''))->isAvailable()
            || (new SvgoProcess(''))->isAvailable()
            || (new GifsicleProcess(''))->isAvailable()
            || (new CwebpProcess(''))->isAvailable();
    }

    protected function execute(): void
    {
        $files = $this->getImageFiles();
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            $this->checkFile($file);
        }
    }

    /**
     * @return string[]
     */
    private function getImageFiles(): array
    {
        return $this->getAllFile(ext: self::KNOWN_IMAGE_EXTENSIONS);
    }

    private function checkFile(string $file): void
    {
        $relative = $this->getRelativePath($file);
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $this->addError('format-unsupported', "Image '{$relative}' uses unsupported extension '.{$extension}'.", $relative);
            return;
        }

        $this->checkFileMimeType($file, 'mime-mismatch', self::ALLOWED_MIME_TYPES);
        $this->checkFileSize($file, 'size-exceeded', 0, self::MAX_FILE_SIZE);
        $this->checkLocation($relative);
        $this->checkNaming($relative);

        if ($extension === 'svg') {
            $this->checkSvgDimensions($file, $relative);
        } else {
            $this->checkRasterDimensions($file, $relative);
        }

        if ($extension === 'jpg' || $extension === 'jpeg') {
            $this->checkExif($file, $relative);
        }

        if ($extension === 'png') {
            $this->checkPngEfficiency($file, $relative);
        }
    }

    private function getRelativePath(string $file): string
    {
        $pluginPath = rtrim($this->plugin->fullpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_replace($pluginPath, '', $file);
    }

    private function checkLocation(string $relative): void
    {
        if (!$this->isActive('location-invalid')) {
            return;
        }
        if (!str_starts_with($relative, 'pix/')) {
            $this->addError('location-invalid', "Image '{$relative}' is not inside the 'pix/' directory.", $relative);
        }
    }

    private function checkNaming(string $relative): void
    {
        if (!$this->isActive('naming-invalid')) {
            return;
        }
        $basename = basename($relative);
        if (!preg_match('/^[a-z0-9._-]+$/', $basename)) {
            $this->addError('naming-invalid', "Image '{$relative}' has an invalid name. Use lowercase letters, numbers, dots, dashes, and underscores only.", $relative);
        }
    }

    private function checkRasterDimensions(string $file, string $relative): void
    {
        if (!$this->isActive('dimensions-exceeded')) {
            return;
        }

        $info = getimagesize($file);
        if ($info === false) {
            $this->addError('corrupt', "Could not read image dimensions for '{$relative}'.", $relative);
            return;
        }

        [$width, $height] = $info;
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            $this->addError('dimensions-exceeded', "Image '{$relative}' is {$width}x{$height}px; maximum allowed is " . self::MAX_DIMENSION . 'px on any side.', $relative);
        }
    }

    private function checkSvgDimensions(string $file, string $relative): void
    {
        if (!$this->isActive('dimensions-exceeded')) {
            return;
        }

        $xml = @simplexml_load_file($file);
        if ($xml === false) {
            $this->addError('corrupt', "Could not parse SVG '{$relative}'.", $relative);
            return;
        }

        $width = (string) $xml['width'];
        $height = (string) $xml['height'];

        if ($width === '' || $height === '') {
            // No explicit dimensions; not an error, could use viewBox.
            return;
        }

        $width = $this->parseSvgLength($width);
        $height = $this->parseSvgLength($height);

        if (($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION)) {
            $this->addError('dimensions-exceeded', "SVG '{$relative}' is {$width}x{$height}px; maximum allowed is " . self::MAX_DIMENSION . 'px on any side.', $relative);
        }
    }

    private function parseSvgLength(string $value): float
    {
        return (float) preg_replace('/[^0-9.]/', '', $value);
    }

    private function checkExif(string $file, string $relative): void
    {
        if (!$this->isActive('metadata-present')) {
            return;
        }
        if (!function_exists('exif_read_data')) {
            $this->addTip('metadata-present', "Cannot check EXIF metadata for '{$relative}': ext-exif is not available.", $relative);
            return;
        }

        $exif = @exif_read_data($file);
        if (!empty($exif) && is_array($exif) && count($exif) > 0) {
            $keys = implode(', ', array_slice(array_keys($exif), 0, 5));
            $this->addError('metadata-present', "Image '{$relative}' contains EXIF/metadata ({$keys}). Strip metadata before release.", $relative);
        }
    }

    private function checkPngEfficiency(string $file, string $relative): void
    {
        if (!$this->isActive('compression-inefficient')) {
            return;
        }
        $size = filesize($file);
        if ($size !== false && $size > self::MAX_PNG_SIZE_FOR_EFFICIENCY) {
            $this->addWarning('compression-inefficient', "PNG '{$relative}' is " . round($size / 1024) . " KB; consider converting large photographic PNGs to JPEG or WebP.", $relative);
        }
    }

    public function fix(bool $apply): bool
    {
        $files = $this->getImageFiles();
        if (empty($files)) {
            return true;
        }

        $overall = true;
        foreach ($files as $file) {
            $relative = $this->getRelativePath($file);
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $process = $this->getOptimizerProcess($extension, $file);
            if (!$process || !$process->isAvailable()) {
                continue;
            }
            if ($apply) {
                $before = filesize($file) ?: 0;
                $process->execute();
                $after = filesize($file) ?: 0;
                $saved = $before - $after;
                if ($process->isSuccessful()) {
                    $this->io->text("Optimized {$relative} (saved {$saved} bytes).");
                } else {
                    $this->io->warning("Failed to optimize {$relative}.");
                    $overall = false;
                }
            } else {
                $this->io->text("Would optimize {$relative}.");
            }
        }
        return $overall;
    }

    private function getOptimizerProcess(string $extension, string $file): ?AbstractImageOptimizerProcess
    {
        return match ($extension) {
            'png' => new PngquantProcess($file),
            'jpg', 'jpeg' => new MozjpegProcess($file),
            'svg' => new SvgoProcess($file),
            'gif' => new GifsicleProcess($file),
            'webp' => new CwebpProcess($file),
            default => null,
        };
    }
}
