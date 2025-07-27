<?php

namespace Tuchsoft\MoodleChecklist\Action;


use Jasny\PhpdocParser\PhpdocParser;
use Jasny\PhpdocParser\Tag\RegExpTag;
use Jasny\PhpdocParser\TagSet;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Parses a directory to extract all authors and licenses from docblock comments in PHP files.
 */
class DocblockParser extends AbstractAction
{
    /**
     * The directory to parse.
     * @var string
     */
    private string $directoryPath;



    public function __construct(string $directoryPath)
    {

        parent::__construct();
        $this->directoryPath = $directoryPath;
        $this->parse();
    }

    /**
     * Parses the specified directory to extract docblock information.
     *
     * @return array
     */
    public function parse(): array
    {
        $this->checkForError();

        if (!is_dir($this->directoryPath)) {
            $this->lastError = 'Directory not found: ' . $this->directoryPath;
            return [];
        }

        $parser = new PhpdocParser(new TagSet([
            new RegExpTag('copyright', '/^(?:(?<year>\d{4})\s*)?(?:(?<name>(?:[^\<\(]\S*\s+)*[^\<\(]\S*)?\s*)?(?:[\<\(](?<email>[^\>\)]+)[\>\)])?/'),
        ]));
        $authors = [];

        $directory = new RecursiveDirectoryIterator($this->directoryPath);
        $iterator = new RecursiveIteratorIterator($directory);

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = @file_get_contents($file->getPathname());
                if ($content === false) {
                    continue;
                }

                $tokens = token_get_all($content);
                foreach ($tokens as $token) {
                    if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                        $parsed = $parser->parse($token[1]);


                        if (isset($parsed['copyright'])) {
                            $cr = $parsed['copyright'];
                            $authors[$cr[0]]  = [
                                'full' => $cr[0],
                                'year' => $cr['year'] ?? '',
                                'name' => $cr['name'] ?? '',
                                'email' => $cr['email'] ?? '',

                            ];
                        }
                    }
                }
            }

        }

        return array_values($authors);
    }


}