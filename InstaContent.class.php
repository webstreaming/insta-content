<?php
/**
 *
 * @license https://github.com/webstreaming/insta-content/blob/master/LICENSE LGPL 3
 * @version 1.0.0
 * @author Gabriel Carignano <gcarignano@webstreaming.com.ar>
 *
 */

class InstaContent
{

    /**
     * Specifies the life in seconds for cache
     *
     * @var int
     */
    private $_maxCacheLife = "30";
    private $_userId = 'user_id';
    private $_apiToken = 'token';
    private $_cachePath = '/';

    function __construct()
    {
        $this->_cachePath = $_SERVER['DOCUMENT_ROOT'] . '/instagram-feed';
    }

    /**
     * Returns wether the cache exists or not
     *
     * @return bool
     */
    function cacheExists()
    {
        return file_exists($this->_cachePath);
    }

    /**
     * If the cache exists, it will return all of its content in JSON format
     *
     * @return string|false
     */
    function getCache()
    {
        if ($this->cacheExists()) {
            $cacheSize = filesize($this->_cachePath);
            if ($cacheSize > 0) {
                $fileHandle = fopen($this->_cachePath, "r");
                $cache = unserialize(fread($fileHandle, $cacheSize));
                return $cache;
            }
        }
        return false;
    }

    /**
     * If cache exists it will return its last update time in unix timestamp format
     *
     * @return string|false
     */
    function getCacheLastUpdate()
    {
        if ($this->cacheExists()) {
            return filemtime($this->_cachePath);
        }
        return false;
    }

    /**
     * Will filter the cache and only return the records created after
     * the provided $timeFrom variable
     *
     * @param string $timeFrom Unix timestamp
     * @return array|false
     */
    function getCacheFiltered($timeFrom)
    {
        $cache = $this->getCache();
        return $this->filterResponse($cache, $timeFrom);
    }

    /**
     * Will connecto to instagram's api and return all of the entries for the
     * specified class $_userId variable in JSON format
     *
     * @return string JSON
     */
    function getInstagramFeed()
    {
        $apiCall = "https://api.instagram.com/v1/users/{$this->_userId}/media/recent/?access_token={$this->_apiToken}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiCall);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $instagramResponse = curl_exec($ch);
        curl_close($ch);
        return $instagramResponse;
    }

    /**
     * Giving a content it will update the cache file replacing all of its content
     * with the serialized content
     *
     * @param string $content
     * @return boolean
     */
    function updateCache($content)
    {
        $cacheHandle = fopen($this->_cachePath, 'w');
        if (!$cacheHandle) {
            throw new Exception('Cannot create cache file');
        }
        fwrite($cacheHandle, serialize($content));
        fclose($cacheHandle);
        return true;
    }

    /**
     * Provided an instagram response or cache file content, will filter
     * the results and return only the records that were created after
     * the $timeFrom value
     * If there are records to return it will be in JSON format
     *
     * @param string $response JSON
     * @param string $timeFrom Unix timestamp
     * @return string|false
     */
    function filterResponse($response, $timeFrom)
    {
        $arrRecords = false;
        if ($response && is_numeric($timeFrom)) {
            $response = json_decode($response, true);
            foreach ($response['data'] as $record) {
                if ($record['created_time'] > $timeFrom) {
                    $arrRecords[] = $record;
                } else {
                    break 1;
                }
            }
        }
        return ($arrRecords) ? json_encode($arrRecords) : $arrRecords;
    }

    /**
     * Will return true if the cache is older than the class $_maxCacheLife variable
     * in seconds
     *
     * @return bool
     */
    function canQueryInstagram()
    {
        $time = time();
        $cacheModTime = $this->getCacheLastUpdate();
        return (!$cacheModTime || ($time - $cacheModTime) > $this->_maxCacheLife);
    }

    /**
     * Will return all entries that are older than the passed $timeFrom value in
     * Unix timestamp format.
     *
     * The return result will be a JSON string
     *
     * @param string $timeFrom Unix timestamp
     * @return string
     */
    function getContent($timeFrom)
    {
        if ($this->canQueryInstagram()) {
            $feed = $this->getInstagramFeed();
            try {
                $this->updateCache($feed);
            } catch (Exception $e) {
                die($e->getMessage());
            }
        }
        return $this->getCacheFiltered($timeFrom);
    }

}
