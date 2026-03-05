<?php

declare(strict_types=1);

namespace Pixelpoems\InstagramFeed\Services;

use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use Dompdf\Exception;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBHTMLText;
use TractorCow\Fluent\Model\Locale;

class InstagramService extends ContentController
{
    use Injectable;
    use Configurable;

    private static string $instagram_access_token = '';

     // long-lived-access-token
    private $path;

     // your path to save the token for auto update
    private $filename;

      // your filename
    private bool $reducedDisplay = false;

    private static int $default_post_size = 250;

    private static int $cache_time = 3600;

    private $identifier; // used to identify the feed cache, e.g. 'p_1' for page with id 1

    private $error;

    public function __construct($identifier = 'default', $path = "ig_token", $filename = "updated.json")
    {
        if (!$this->getToken()) {
            $this->setError('You need to add an Instagram ACCESS TOKEN to connect your instagram feed!');
        }

        $this->path = $path;
        $this->filename = $filename;
        $this->identifier = $identifier;
    }

    private function getToken(): ?string
    {
        return (string)$this->config()->instagram_access_token ?: (string)Environment::getEnv('INSTAGRAM_ACCESS_TOKEN');
    }

    public function hasToken(): bool
    {
        return (bool) $this->getToken();
    }

    private function setError($error = null): void
    {
        $this->error = $error;
    }

    private function getError()
    {
        return $this->error;
    }

    public function getErrorDescription($render = false)
    {
        if (!$this->getError()) {
            return null;
        }

        if (!$render) {
            return $this->getError();
        }

        return $this->customise([
            'Error' => $this->getError()
        ])->renderWith('Pixelpoems\\InstagramFeed\\Includes\\MetaError');
    }

    public function checkOnErrors(): bool
    {
        $this->getFeed(1);
        return (bool) $this->getError();
    }

    /**
     * All posts will only display the first image or (video) the thumbnail-image. The images will be displayed as Grid.
     */
    public function setReducedDisplay(bool $reducedDisplay): void
    {
        $this->reducedDisplay = $reducedDisplay;
    }

    /**
     * @throws NotFoundExceptionInterface
     */
    public function getFeed($limit = null): bool|ArrayList
    {
        $feed = $this->getFeedCache($limit);
        if(!$feed) {
            $feed = $this->getFeedUncached();
            $this->setFeedCache($feed);
            if ($feed->count() !== 0) {
                return $this->getFeedCache($limit);
            }

            return ArrayList::create();
        }

        return $feed;
    }

    /**
     * Get the feed from the cache. If there is no cache
     * then return false.
     *
     * @param null $limit
     * @return ArrayList|bool
     * @throws NotFoundExceptionInterface
     */
    public function getFeedCache($limit = null): bool|ArrayList
    {
        if ($this->getError()) {
            return false;
        }

        $cache = $this->getCacheFactory();
        $feedStore = $cache->get($this->getCacheKey());
        if (!$feedStore) {
            return false;
        }

        $feed = unserialize($feedStore);
        if (!$feed) {
            return false;
        }

        return $feed->limit($limit);
    }

    /**
     * Get the time() that the cache expires at.
     *
     * @return false|int
     */
    public function getFeedCacheExpiry(): false|int
    {
        $cache = $this->getCacheFactory();
        $metadata = $cache->getMetadatas($this->ID);
        if ($metadata && isset($metadata['expire'])) {
            return $metadata['expire'];
        }

        return false;
    }

    /**
     * Set the cache.
     */
    public function setFeedCache(ArrayList $feed): void
    {
        if (!$feed->count() || $this->getError()) {
            return;
        } // No feed to cache

        $cache = $this->getCacheFactory();
        $feedStore = serialize($feed);
        $cache->set($this->getCacheKey(), $feedStore, self::$cache_time);
    }

    /**
     * Clear the cache that holds this providers feed.
     */
    public function clearFeedCache(): void
    {
        $cache = $this->getCacheFactory();
        $cache->delete($this->getCacheKey());
    }

    /**
     * Refresh the cache.
     */
    public function refreshCache(): void
    {
        $this->clearFeedCache();
        $this->getFeedUncached();
    }

    protected function getCacheKey(): string
    {
        $cacheKey = 'InstagramFeed';
        if(class_exists(Locale::class)) {
            $cacheKey .= '-' . Locale::getCurrentLocale()->getLocale();
        }

        return $cacheKey . ('-' . $this->identifier);
    }

    /**
     * @throws NotFoundExceptionInterface
     */
    protected function getCacheFactory()
    {
        return Injector::inst()->get(CacheInterface::class . '.InstagramFeed');
    }

    public function getFeedUncached(): ArrayList
    {
        // https://developers.facebook.com/docs/instagram-basic-display-api/reference/media#fields
        $fields = ['id' ,'username','permalink','timestamp','caption','media_type','media_url','thumbnail_url'];

        $instagramFeed = null;
        try {
            $this->refreshToken();
            $instagramFeed = $this->getGraphEndpoint('me/media', $fields);
        } catch (\Exception $exception) {
            $this->setError($exception->getMessage());
        }

        if(isset($instagramFeed->error)) {
            $this->setError($instagramFeed->error->message);
        }

        if (!$instagramFeed || $this->getError()) {
            return ArrayList::create();
        }

        $prepFeed = ArrayList::create();
        foreach ($instagramFeed->data as $value) {
            $prepFeed->add($this->getSinglePost($value));
        }

        return $prepFeed;
    }

    // https://developers.facebook.com/docs/instagram-basic-display-api/reference/media
    private function getSinglePost($feedItem): DBHTMLText
    {
        $mediaType = $feedItem->media_type;
        $data = [
            'ID' => $feedItem->id,
            'MediaType' => $mediaType,
            'Link' => $feedItem->permalink,
            'ProfileLink' => 'https://www.instagram.com/' . $feedItem->username,
            'Username' => $feedItem->username,
            'MediaSrc' => $feedItem->media_url,
            'Timestamp' => $feedItem->timestamp,
            'DefaultSize' => $this->config()->default_post_size
        ];

        // In case of no caption, we need to set the caption to null
        if ($feedItem->caption) {
            $data['Caption'] = $feedItem->caption;
        }

        $baseTemplatePath = 'Pixelpoems\\InstagramFeed\\Posts\\';
        $template = $baseTemplatePath . 'Image';

        switch ($mediaType) {
            case ('CAROUSEL_ALBUM'):
                $children = $this->getGraphEndpoint($feedItem->id . '/children', ['id', 'timestamp', 'media_url']);
                $children = $children->data;
                $data['Children'] = ArrayList::create();

                foreach ($children as $child) {
                    $data['Children']->push(ArrayData::create([
                        'ID' => $child->id,
                        'MediaSrc' => $child->media_url,
                        'DefaultSize' => $this->config()->default_post_size
                    ]));
                }

                if (!$this->reducedDisplay) {
                    $template = $baseTemplatePath . 'Carousel';
                }

                break;

            case ('VIDEO'):
                $data['MediaSrc'] = $feedItem->thumbnail_url;
                $data['VideoSrc'] = $feedItem->media_url;
                if (!$this->reducedDisplay) {
                    $template = $baseTemplatePath . 'Video';
                }

                break;

            default:
                break;
        }

        $data = ArrayData::create($data);
        $this->extend('updateInstagramPost', $data, $template);
        return $data->renderWith($template);
    }

    // Based on https://github.com/Yizack/instagram-feed
    protected function refreshToken(): null
    {
        $path = $this->path;
        $filename = $this->filename;

        $date = date("Y-m-d H:i:s");
        $array = ["updated" => $date];

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
            $fp = fopen(sprintf('%s/%s', $path, $filename), "w");
            fwrite($fp, json_encode($array));
            fclose($fp);
        }

        $date_json = null;
        try {
            $data = @file_get_contents(sprintf('%s/%s', $path, $filename));
            $result = json_decode($data, true);
            if (isset($result['updated'])) {
                $date_json = $result["updated"];
            }
        } catch (\Exception $exception) {
            throw new Exception('Instagram Feed could not be loaded. Please check your Instagram Access Token!', E_USER_WARNING);
        }

        if (!$date_json) {
            return null;
        }

        if (strtotime($date) - strtotime($date_json) > 86400) {
            $this->getGraphEndpoint('refresh_access_token', null, '&grant_type=ig_refresh_token');
            $array = ["updated" => $date];
            $fp = fopen(sprintf('%s/%s', $path, $filename), "w");
            fwrite($fp, json_encode($array));
            fclose($fp);
        }

        return null;
    }

    /**
     * This method is used to get the Instagram Graph API endpoint.
     * Docs: https://developers.facebook.com/docs/instagram-basic-display-api/guides/getting-profiles-and-media
     * @param $param
     * @param $fields
     * @param $endParam
     * @return mixed|null
     * @throws Exception
     */
    private function getGraphEndpoint(string $param = '', ?array $fields = [], string $endParam = '')
    {
        if (!$this->getToken()) {
            return null;
        }

        $url =  "https://graph.instagram.com/v22.0/";
        if ($param !== '' && $param !== '0') {
            $url .= $param;
        }

        $url = $url . '?access_token=' . $this->getToken();

        if ($fields) {
            $fields = implode(',', $fields);
            $url = $url . '&fields=' . $fields;
        }

        if ($endParam !== '' && $endParam !== '0') {
            $url .= $endParam;
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

            if($decoded = json_decode($result)) {
                return json_decode($result);
            }

            $this->setError($result);
            return;

        } catch (\Exception $exception) {
            throw new Exception('Instagram Feed could not be loaded. Please check your Instagram Access Token!', E_USER_WARNING);
        }
    }
}
