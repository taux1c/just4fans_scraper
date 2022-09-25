<?php

// Fill out the following information
$userId = 973519; // this is an integer
$posterId = 733001; // this is an integer
$type = "One"; // 99.9% chance you don't need to change this
$userHash = "place_hash_here"; // value of UserHash4, this is a string
$startAt = 0; // offset - unless you want to start at a different page, don't change this
$username = "user_name_goes_here"; // username of the profile you want to scrape

// Do not edit anything beyond this line //
class Fetcher
{
    public $profileUrl;

    public function __construct($profileUrl)
    {
        $this->profileUrl = $profileUrl;
    }

    public function get($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36 Edg/90.0.818.66");
        curl_setopt($ch, CURLOPT_REFERER, $this->profileUrl);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $source = curl_exec($ch);

        if (curl_error($ch)) {
            curl_close($ch);
            return [
                'status' => false,
                'data' => null
            ];
        }
        
        return [
            'status' => true,
            'data' => $source
        ];
    }

    public function download($source, $destination)
    {
        $fp = fopen($destination, 'w+');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36 Edg/90.0.818.66");
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_REFERER, $this->profileUrl);
        curl_setopt($ch, CURLOPT_URL, $source);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $source = curl_exec($ch);

        if (curl_error($ch)) {
            curl_close($ch);
            fclose($fp);
            return [
                'status' => false,
                'data' => null
            ];
        }
        curl_close($ch);
        fclose($fp);
        
        return [
            'status' => true,
            'data' => $source
        ];
    }
}

// Initialize fetcher
$profileUrl = "https://justfor.fans/" . $username;
$fetcher = new Fetcher($profileUrl);

// Start looping through the pages
$pageCounter = 1;
$fullSource = "";
echo "Initializing...";
do {
    echo "\nFetching page {$pageCounter}...";
    $url = "https://justfor.fans/ajax/getPosts.php?UserID={$userId}&PosterID={$posterId}&Type={$type}&StartAt={$startAt}&Page=Profile&UserHash4={$userHash}";
    echo "\n- Using URL {$url}";

    // Grab the page
    $response = $fetcher->get($url);
    if (!$response['status']) {
        // Something went wrong. Exit.
        echo "\nFailed to fetch data from URL.";
        exit();
    }

    // Count the number of posts
    $numPosts = substr_count($response['data'], "jffPostClass");

    // Add to full source
    $fullSource .= $response['data'];

    // Update variable values
    $pageCounter++;
    $startAt += $numPosts;
} while ($response['status'] && $response['data'] != "<div class='thatsIt'><i class='far fa-sad-cry faa-tada animated'></i>That's all! We're as sad as you are.</div>");

// Done fetching source.
echo "\nDone fetching sources.";

// Prepare directory
$imgDir = "{$username}/images/";
$vidDir = "{$username}/videos/";
@mkdir("{$username}/", 0775);
@mkdir($imgDir, 0755);
@mkdir($vidDir, 0755);

// Parse for images
echo "\nParsing for images...";
preg_match_all('/src=(.*)\.(jpg|png|gif|jpeg)/m', $fullSource, $images, PREG_SET_ORDER, 0);
$finalImages = [];
foreach ($images as $img) {
    if (strpos($img[0], "https://") !== false) {
        // This starts with https, include it as-is
        $im = str_replace("src=\"", "", $img[0]);
        $im = str_replace("src='", "", $im);
        $finalImages[] = ltrim($im, "/");
    } else {
        // Clean up
        $im = str_replace("src=\"", "", $img[0]);
        $im = str_replace("src='", "", $im);
        $im = "/" . ltrim($im, "/");
        if (strpos($im, "Profile-") === false) {
            $finalImages[] = "https://justfor.fans{$im}";
        }
    }
}
echo "\nFound " . count($finalImages) . " pictures. Processing...";
$imgCounter = 1;
foreach ($finalImages as $finalImage) {
    echo "\nDownloading image {$imgCounter}...";
    $filename = basename($finalImage);
    copy($finalImage, $imgDir . $filename);
    echo " done!";
    $imgCounter++;
}

// Parse for videos
echo "\nParsing for videos...";
preg_match_all('/\<a\ onClick\=\'MakeMovieHD\(\"[a-zA-Z0-9\-]+\"\,\ \{(.*)\}/m', $fullSource, $videos, PREG_SET_ORDER, 0);
$finalVideos = [];
foreach ($videos as $video) {
    $jsonStr = '{' . $video[1] . '}';
    $videoJson = json_decode($jsonStr, true);
    ksort($videoJson);
    foreach ($videoJson as $k => $url) {
        $finalVideos[] = $url;
        break;
    }
}
echo "\nFound " . count($finalVideos) . " videos. Processing...";
$videoCounter = 1;
foreach ($finalVideos as $finalVideo) {
    echo "\nDownloading video {$videoCounter}...";
    $filename = md5(basename($finalVideo)) . ".mp4";
    $fetcher->download($finalVideo, $vidDir . $filename);
    echo " done!";
    $videoCounter++;
}

echo "\nDone!";
