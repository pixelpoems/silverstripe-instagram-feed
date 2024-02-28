<?php

namespace Pixelpoems\InstagramFeed\Services;

use Dompdf\Exception;
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
    private $error = null;

    public function __construct($path = "ig_token", $filename = "updated.json")
    {
        if (!$this->getToken()) {
            $this->setError('You need to add an Instagram ACCESS TOKEN to connect your instagram feed!');
        }

        $this->path = $path;
        $this->filename = $filename;
    }

    private function getToken(): ?string
    {
        return $this->config()->instagram_access_token ?: Environment::getEnv('INSTAGRAM_ACCESS_TOKEN');
    }

    private function setError($error = null)
    {
        $this->error = $error;
    }

    private function getError()
    {
        return $this->error;
    }

    public function getErrorDescription($render = false)
    {
        if(!$this->getError()) return null;
        if(!$render) return $this->getError();
        return $this->customise([
            'Error' => $this->getError()
        ])->renderWith('Pixelpoems\\InstagramFeed\\Includes\\MetaError');
    }

    public function checkOnErrors()
    {
        $this->getFeed(1);
        return $this->getError() ? true : false;
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

        try {
            $this->refreshToken();
            $instagramFeed = $this->getGraphEndpoint('me/media', $fields);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
        }

        if(isset($instagramFeed->error)) {
            $this->setError($instagramFeed->error->message);
        }
        if(!$instagramFeed || $this->getError()) return ArrayList::create();
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

        $date_json = null;
        try {
            $data = @file_get_contents("$path/$filename");
            $result = json_decode($data, true);
            if (isset($result['data'])) {
                $date_json = $result['data']["updated"];
            }
        } catch (\Exception $e) {
            throw new Exception('Instagram Feed could not be loaded. Please check your Instagram Access Token!', E_USER_WARNING);
        }

        if(!$date_json) return null;
        if (strtotime($date) - strtotime($date_json) > 86400) {
            $this->getGraphEndpoint('refresh_access_token', null, '&grant_type=ig_refresh_token');
            $array = ["updated" => $date];
            $fp = fopen("$path/$filename", "w");
            fwrite($fp, json_encode($array));
            fclose($fp);
        }
    }

    private function getGraphEndpoint($param = '', $fields = [], $endParam = '')
    {
        if(!$this->getToken()) return null;

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

    protected function request($url)
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);

            curl_close($ch);

            return json_decode($result);
        } catch (\Exception $e) {
            throw new Exception('Instagram Feed could not be loaded. Please check your Instagram Access Token!', E_USER_WARNING);
        }
    }
}
