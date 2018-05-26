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

use Stormiix\MP3Fixer\MP3;
use Stormiix\MP3Fixer\iTunes;

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

// TODO: Use itunes API as a tag source

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

    /**
     * Temporary folder to hold downloaded files.
     *
     * @var string
     */
    public $tmpFolder = '';


    /**
     * Blacklisted terms.
     *
     * @var array
     */
    public $badWords = [
        'audio only',
        'Audio',
        'audio edit',
        'audio',
        'paroles/lyrics',
        'lyrics/paroles',
        'with lyrics',
        'w/lyrics',
        'w / lyrics',
        'avec paroles',
        'avec les paroles',
        'avec parole',
        'lyrics',
        'paroles',
        'parole',
        'radio edit.',
        'radio edit',
        'radio-edit',
        'shazam version',
        'shazam v...',
        'music video',
        'clip officiel',
        'officiel',
        'new song',
        'official video',
        'official'
    ];

    public function __construct($inputFolder = 'input', $outputFolder = 'output', $tmpFolder = 'tmp')
    {
        $this->inputFolder = $inputFolder;
        $this->outputFolder = $outputFolder;
        $this->tmpFolder = $tmpFolder;
    }

    public function isMP3($filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION) == 'mp3';
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

    public function cleanTitle($title)
    {

        $title = str_replace(array( '(', ')' , '[' , ']'), '', $title);
        $title = str_ireplace($this->badWords, '', $title);
        return trim($title);
    }
    public function fetchInternetTagsLastFM($title, $artist)
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', 'http://ws.audioscrobbler.com/2.0/?method=track.getInfo&api_key=220ab783c56c73fbed2f667f8539248e&artist='.$artist.'&track='.$title.'&format=json', ['verify'=>false]);
        $response = json_decode($res->getBody(), true);
        if (!empty($response) && !isset($response['error'])) {
            $response = $response['track'];
            $tags = [
                'title'                  => !empty($response['name']) ? $response['name'] : $title,
                'artist'                 => !empty($response['artist']['name']) ? $response['artist']['name'] : $artist,
                'album'                  => !empty($response['album']['title']) ? $response['album']['title'] : $title." [Single]",
                'genre'                  => !empty($response['genre']) ? $response['genre']: '',
                'year'                   => !empty($response['year']) ? $response['year']: '',
                'album_art'              => !empty($response['album']['image'][2]) ? $this->downloadTMP($response['album']['image'][2]["#text"],"lastfm"): "default.png",
                //'comment'                => ''// TODO Customize the comment !
            ];
            return $tags;
        } else {
            // No info were found
            return [];
        }
    }

    public function fetchInternetTagsiTunes($title, $artist)
    {

        $results = iTunes::search($title, array(
        	'media' => 'music'
        ))->results;

        $response = [];
        foreach ($results as $result) {
        	$artistName = preg_replace("/[^A-Za-z0-9]/", "", $result->artistName);
        	$artist = preg_replace("/[^A-Za-z0-9]/", "", $artist);
        	if(strcasecmp($artistName,$artist) == 0){ // This is the best way to compare the two strings
        		// We got the result
        		$response = $result;
        	}
        }
        if (!empty($response)) {
            $tags = [
                'title'                  => $response->trackName,
                'artist'                 => $response->artistName,
                'album'                  => $response->collectionName,
                'genre'                  => $response->primaryGenreName,
                'year'                   => substr($response->releaseDate,0,4),
                'album_art'              => $this->downloadTMP(str_replace('100x100', '400x400',$response->artworkUrl100),"itunes"),
                //'comment'                => ''// TODO Customize the comment !
            ];
            return $tags;
        } else {
            // No info were found
            return [];
        }
    }

    public function fetchInternetTagsSpotify($title, $artist)
    {
        // Generate access token : https://accounts.spotify.com/api/token
        $client = new \GuzzleHttp\Client();
        $res = $client->request('POST', 'https://accounts.spotify.com/api/token', [
            'verify'=>false,
            'form_params' => ['grant_type' => 'client_credentials'],
            'auth' => [
                '98c815f2b1414495b56f4472ee1880b5',
                '23fb88e7b5ed49e7adb04e2dae6aaeeb'
            ]
        ]);
        $response = json_decode($res->getBody(), true);
        $token = $response['access_token'];

        // Search request
        $title  = preg_replace('/[^a-zA-Z0-9_ -]/s','',$title);
        $SearchRes = $client->request('GET', 'https://api.spotify.com/v1/search?q='.$artist.' '.$title.'&type=track,artist&limit=1', [
            'verify'=>false,
            'headers'         => [
                'Authorization' => 'Bearer '.$token
            ]
        ]);
        $response = json_decode($SearchRes->getBody(), true);
        $track = array_key_exists(0,$response['tracks']['items']) ? $response['tracks']['items'][0] : [];
        if (!empty($track) && !isset($response['error']) &&  $track["album"]["artists"][0] == $artist) {
            $album = $track["album"];
            $artist = $track["album"]["artists"][0];
            $tags = [
                'title'                  => !empty($track['name']) ? $track['name'] : $title,
                'artist'                 => !empty($artist['name']) ? $artist['name'] : $artist,
                'album'                  => !empty($album['name']) ? $album['album_type'] == "single" ? $album['name']." [Single]" : $album['name'] : $title." [Single]",
                'year'                   => !empty($album['release_date']) ? substr($album['release_date'],0,4): '',
                'album_art'              => !empty($album['images'][0]) ? $this->downloadTMP($album['images'][0]["url"],"spotify"): "default.png",
                //'comment'                => '' // TODO Customize the comment !
            ];

            return $tags;
        } else {
            // No info were found
            return [];
        }
    }

    public function downloadTMP($imageLink,$source)
    {
        // Create the temp folder if it doesn't exist
        if (!file_exists($this->tmpFolder)) {
            mkdir($this->tmpFolder, 0777, true);
        }
        $ext = pathinfo($imageLink, PATHINFO_EXTENSION) == "" ? "png" : pathinfo($imageLink, PATHINFO_EXTENSION);
        $img = $this->tmpFolder.'/'.$source.'-'.time().".".$ext;
        if (!empty($imageLink) && file_put_contents($img, file_get_contents($imageLink))) {
            return $img;
        } else {
            return "default.png";
        }
    }
    public function getBestTags($title, $artist)
    {
        try {
            $tagsiTunes = $this->fetchInternetTagsiTunes($this->cleanTitle($title), $artist);
        } catch (Exception $e) {
            $tagsiTunes = [];
        }

        if (!empty($tagsiTunes)){
            $tags = $tagsiTunes;
        }else{
            try {
                $tags = $this->fetchInternetTagsSpotify($this->cleanTitle($title), $artist);
            } catch (Exception $e) {
                $tags = [];
            }
            if(empty($tags)){
                try {
                    $tags = $this->fetchInternetTagsLastFM($this->cleanTitle($title), $artist);
                } catch (Exception $e) {
                    $tags = [];
                }
            }
        }
        return $tags;
    }

    public function fixMP3S(){
        $inputFiles = $this->getInputFiles();
        if($this->inputFolder == $this->outputFolder){
            foreach ($inputFiles as $file) {
                $mp3 = new MP3($file);
                $tags = $this->getBestTags($mp3->title,$mp3->artist);
                if ($mp3->writeTags($tags)){
                    // rename file
                    $newfile = $this->outputFolder."/".$mp3->artist." - ".$mp3->title.".mp3";
                    rename($file,$newfile);
                    print("Fixed : ".$file." -> ".$newfile."\n");
                }else{
                    print("Failed to fix: ".$file);
                }
            }
        }else{
            foreach ($inputFiles as $file) {
                $copyfile = $this->outputFolder."/".basename($file);
                if(copy($file,$copyfile)){
                    $file = $copyfile;
                    $mp3 = new MP3($file);
                    $tags = $this->getBestTags($mp3->title,$mp3->artist);
                    if(empty($tags)){
                        print("No tags were found for : ".$file."\n");
                        continue;
                    }
                    if($mp3->writeTags($tags)){
                        // rename file
                        $newfile = $this->outputFolder."/".$tags['artist']." - ".$tags['title'].".mp3";
                        rename($copyfile,$newfile);
                        print("Fixed : ".$copyfile." -> ".$newfile."\n");
                    }else{
                        print("Failed to fix: ".$file."\n");
                    }
                }
            }
        }
    }
}
