<?php
/**
 * MP3Fixer main class | src/MP3Fixer.php.
 *
 * @author Stormiix <madadj4@gmail.com>
 * @license BSD 3-Clause License
 *
 * @version 0.0
 *
 * @copyright Copyright (c) 2018, Stormix.co
 */

namespace Stormiix\MP3Fixer;

/**
 *  MP3Fixer class.
 *
 *  A PHP app to fix broken MP3 files ID3 Tags
 *
 *  @author Stormiix <madadj4@gmail.com>
 *  @license BSD 3-Clause License
 *
 *  @version 0.0
 */

// TODO: Use spotify API as a tag source
// TODO: Use itunes API as a tag source
// TODO: Use last API as a tag source

class MP3Fixer
{
    /**
     * Input directory that holds the broken MP3 files.
     *
     * @var string
     */
    public $inputFolder = '';

    /**
     * Output folder.
     *
     * @var string
     */
    public $outputFolder = '';

    public function __construct($inputFolder = 'input', $outputFolder = 'output')
    {
        $this->inputFolder = $inputFolder;
        $this->outputFolder = $outputFolder;
    }

    public function isMP3($filename)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        return finfo_file($finfo, $filename) == 'audio/mpeg';
    }

    public function getInputFiles()
    {
        try {
            $iterator = new \DirectoryIterator($this->inputFolder);
            $mp3s = [];
            foreach ($iterator  as $fileInfo) {
                if ($fileInfo->isFile()) {
                    if ($this->isMP3($fileInfo->getPathname())) {
                        $mp3s[] = $fileInfo->getPathname();
                    }
                }
            }

            return $mp3s;
        } catch (\RuntimeException $e) {
            echo 'Input Folder ('.$this->inputFolder.") can't be empty !";
        }
    }
}
