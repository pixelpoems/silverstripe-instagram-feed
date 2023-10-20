<?php

namespace Pixelpoems\InstagramFeed\Services;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;

class InstagramService extends ContentController
{
    use Injectable;
    use Configurable;

    private static string $instagram_access_token = ''; // long-lived-access-token
    private $path; // your path to save the token for auto update
    private $filename;  // your filename
    private bool $reducedDisplay = false;
    private static int $default_post_size = 250;

    public function __construct($path = "ig_token", $filename = "updated.json")
    {
        if (!$this->getToken()) {
            user_error('You need to add an Instagram ACCESS TOKEN to connect your instagram feed!', E_USER_WARNING);
        }

        $this->path = $path;
        $this->filename = $filename;
    }

    private function getToken(): ?string
    {
        return $this->config()->instagram_access_token ?: Environment::getEnv('INSTAGRAM_ACCESS_TOKEN');
    }

    /**
     * All posts will only display the first image or (video) the thumbnail-image. The images will be displayed as Grid.
     * @param bool $reducedDisplay
     * @return void
     */
    public function setReducedDisplay(bool $reducedDisplay): void
    {
        $this->reducedDisplay = $reducedDisplay;
    }

    public function getFeed($limit = null): ArrayList
    {
        // https://developers.facebook.com/docs/instagram-basic-display-api/reference/media#fields
        $fields = ['id' ,'username','permalink','timestamp','caption','media_type','media_url','thumbnail_url'];
        $this->refreshToken();
        $instagramFeed = $this->getGraphEndpoint('me/media', $fields);

        if ($limit) {
            $instagramFeed = array_slice($instagramFeed, 0, $limit);
        }

        $prepFeed = ArrayList::create();
        foreach ($instagramFeed as $value) {
            $prepFeed->add($this->getSinglePost($value));
        }

        return $prepFeed;
    }

    // https://developers.facebook.com/docs/instagram-basic-display-api/reference/media
    private function getSinglePost($feedItem): DBHTMLText
    {
        $mediaType = $feedItem['media_type'];
        $data = [
            'ID' => $feedItem['id'],
            'MediaType' => $mediaType,
            'Link' => $feedItem['permalink'],
            'ProfileLink' => 'https://www.instagram.com/' . $feedItem['username'],
            'Username' => $feedItem['username'],
            'MediaSrc' => $feedItem['media_url'],
            'Caption' => $feedItem['caption'],
            'Timestamp' => $feedItem['timestamp'],
            'DefaultSize' => $this->config()->default_post_size
        ];

        $baseTemplatePath = 'Pixelpoems\\InstagramFeed\\Posts\\';
        $template = $baseTemplatePath . 'Image';

        switch ($mediaType) {
            case ('CAROUSEL_ALBUM'):
                $children = $this->getGraphEndpoint($feedItem['id'] . '/children', ['id', 'timestamp', 'media_url']);
                $data['Children'] = ArrayList::create();

                foreach ($children as $child) {
                    $data['Children']->push(ArrayData::create([
                        'ID' => $child['id'],
                        'MediaSrc' => $child['media_url'],
                        'DefaultSize' => $this->config()->default_post_size
                    ]));
                }

                if(!$this->reducedDisplay) $template = $baseTemplatePath . 'Carousel';
                break;

            case ('VIDEO'):
                $data['MediaSrc'] = $feedItem['thumbnail_url'];
                $data['VideoSrc'] = $feedItem['media_url'];
                if(!$this->reducedDisplay) $template = $baseTemplatePath . 'Video';
                break;

            default:
                break;
        }

        $data = ArrayData::create($data);
        $this->extend('updateInstagramPost', $data, $template);
        return $data->renderWith($template);
    }

    // Based on https://github.com/Yizack/instagram-feed
    protected function refreshToken()
    {
        $path = $this->path;
        $filename = $this->filename;

        $date = date("Y-m-d H:i:s");
        $array = ["updated" => $date];

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
            $fp = fopen("$path/$filename", "w");
            fwrite($fp, json_encode($array));
            fclose($fp);
        }

        $date_json = $this->request("$path/$filename")["updated"];

        if (strtotime($date) - strtotime($date_json) > 86400) {
            $this->getGraphEndpoint('refresh_access_token', null, '&grant_type=ig_refresh_token');
            $array = ["updated" => $date];
            $fp = fopen("$path/$filename", "w");
            fwrite($fp, json_encode($array));
            fclose($fp);
        }
    }

    private function getGraphEndpoint($param = '', $fields = [], $endParam = ''): array
    {
        $url =  "https://graph.instagram.com/";
        if ($param) {
            $url = $url . $param;
        }

        $url = $url . '?access_token=' . $this->getToken();

        if ($fields) {
            $fields = implode(',', $fields);
            $url = $url . '&fields=' . $fields;
        }

        if ($endParam) {
            $url = $url . $endParam;
        }

        return $this->request($url);
    }

    protected function request($path)
    {
        $result = json_decode(file_get_contents($path), true);
        if (isset($result['data'])) {
            return $result['data'];
        }
        return $result;
    }
}
