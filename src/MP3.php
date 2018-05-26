<?php
/**
 * MP3 file class | src/MP3.php.
 * Taken from Playlist-Downloader.com
 *
 * @author Stormiix <madadj4@gmail.com>
 * @license BSD 3-Clause License
 *
 * @version 0.0
 *
 * @copyright Copyright (c) 2018, Stormix.co
 */

namespace Stormiix\MP3Fixer;

use Stormiix\EyeD3\EyeD3;

class MP3
{
    public $file;
    public $title = "";
    public $artist = "";
    public $album = "";
    public $year = "";
    public $genre = "";


    public function __construct($file)
    {
        $eyed3 = new EyeD3($file);
        $tags = $eyed3->readMeta();
        $this->file = $file;
        $title = array_key_exists("title",$tags) ? $tags["title"] : basename($file,".mp3");
        $data = explode('-',$title);
        $this->title = array_key_exists(1,$data) ? trim($data[1]) : $tags["title"];
        $this->artist = array_key_exists(1,$data) ? trim($data[0]) : $tags["artist"];
        $this->album = array_key_exists("album", $tags) ? $tags["album"]  : array_key_exists("title", $tags) ? $this->title ." [Single]" : '';
        $this->year =array_key_exists("year", $tags) ? $tags["year"]  : '';
        $this->genre = array_key_exists("genre", $tags) ? $tags["genre"]["genre"]  :  'Unknown';
    }

    public function writeTags($tags)
    {
        if ($tags == []) {
            return false;
        } else {
            $eyed3 = new EyeD3($this->file);
            $eyed3->updateMeta($tags);
            return True;
        }
    }
}
