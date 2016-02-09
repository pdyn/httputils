<?php
/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @copyright 2010 onwards James McQuillan (http://pdyn.net)
 * @author James McQuillan <james@pdyn.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pdyn\httputils;

use \pdyn\base\Exception;

/**
 * Provides an interface for dealing with remote resources.
 *
 * This class should be extended to provide additional functionality for specific resource types.
 * Each sub-class can implement additional data types by providing a fetch_[type] function for each data type.
 * Each sub-class also needs to implement the applies() function to determine whether the class should be used based on mime/body.
 */
class HttpResource {

	/** @var \pdyn\cache\CacheInterface A caching object */
	protected $cache = null;

	/** @var string A hash of the URL to be used as the key for caching. */
	protected $cachekey = null;

	/** @var \pdyn\httpclient\HttpClientInterface An HttpClientInterface object, used for fetching resources. */
	protected $httpclient = null;

	/** @var string The URL of the resource */
	protected $url = '';

	/** @var int How long the resource will be cached */
	protected $ttl = 7200;

	/** @var string The mime type of the resource */
	protected $mime_type = '';

	/** @var string The body text/data of the resource */
	protected $body = '';

	/** @var array Stores all data about the resource, indicated by data type. */
	public $data = [];

	/**
	 * Get a list of subclasses that will be checked for support for each resource.
	 *
	 * @return array Array of fully-qualified classnames that will be checked for resource support.
	 */
	public static function getresourcetypes() {
		return [
			'\pdyn\httputils\resource\AudioResource',
			'\pdyn\httputils\resource\HtmlResource',
			'\pdyn\httputils\resource\ImageResource',
			'\pdyn\httputils\resource\VideoResource',
		];
	}

	/**
	 * Determine whether the class applies to the passed resource information.
	 *
	 * @param string $url The URL of the resource.
	 * @param string $mime A mime type.
	 * @param string $body The received body text.
	 * @return bool Whether the class can handle the resource.
	 */
	public static function applies($url, $mime, $body) {
		return false;
	}

	/**
	 * Get the default thumbnail file.
	 *
	 * @return string Get the default thumbnail.
	 */
	public static function get_default_thumbnail() {
		return __DIR__.'/resource/defaultthumbnail.png';
	}

	/**
	 * Create a resource image from a URL.
	 *
	 * @param string $url The URL to create the resource from.
	 * @param \pdyn\cache\CacheInterface $cache A caching object.
	 * @param \pdyn\httpclient\HttpClientInterface $httpclient An HttpClientInterface instance.
	 * @param int $ttl How long the cached information will stay valid, in seconds.
	 * @return HttpResource An HttpResource object, or one of it's decendents.
	 */
	public static function instance($url, \pdyn\cache\CacheInterface $cache, \pdyn\httpclient\HttpClientInterface $httpclient, $ttl = 7200) {
		// Normalize/validate URL.
		$url = \pdyn\datatype\Url::normalize($url);
		if (!\pdyn\datatype\Url::validate($url)) {
			throw new Exception('Invalid URL: '.$url, Exception::ERR_BAD_REQUEST);
		}

		$cache_key = sha1($url);
		$link_info = $cache->get('link_basic', $cache_key);
		if (!empty($link_info) && is_array($link_info)) {
			$link_info = $link_info['data'];
		} else {
			$response = $httpclient->get($url);
			$link_info = [
				'mime_type' => mb_strtolower($response->mime_type()),
				'body' => base64_encode(gzdeflate($response->body(), 9)),
				'url' => $url,
			];
			$link_info['handler'] = static::detect_resource_type($url, $link_info['mime_type'], $response->body());
			$cache->store('link_basic', $cache_key, $link_info, (time() + $ttl));
		}

		unset($link_info['body']);
		$resourcetypes = static::getresourcetypes();
		$classname = $link_info['handler'];
		if (in_array($classname, $resourcetypes)) {
			$resource = new $classname($url, $cache, $httpclient, $ttl, $link_info);
			return $resource;
		} else {
			throw new Exception('rmtRes: did not know how to handle '.$link_info['handler'].' resource type', Exception::ERR_BAD_REQUEST);
		}
	}

	/**
	 * Run through all the enabled resource types and attempt to determine if one can handle it.
	 *
	 * @param string $url The link's URL.
	 * @param string $mime The received mime type.
	 * @param string $body The body text received.
	 * @return string A fully-qualified class name that will handle the resource.
	 */
	public static function detect_resource_type($url, $mime, $body) {
		$resourcetypes = static::getresourcetypes();
		$matches = [];
		foreach ($resourcetypes as $class) {
			if ($class::applies($url, $mime, $body) === true) {
				$matches[$class] = $class::PRIORITY;
			}
		}
		if (!empty($matches)) {
			arsort($matches);
			reset($matches);
			return key($matches);
		} else {
			return '\\'.get_called_class();
		}
	}

	/**
	 * Remove common garbage at the start of body text so we can more accurately detect type.
	 *
	 * @param string $body Incoming body text.
	 * @return string Cleaned body text.
	 */
	public static function cleantext($body) {
		// Remove byte-order-mark.
		$body = (mb_substr($body, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) ? mb_substr($body, 3) : $body;
		// Remove leading comments.
		$body = (!empty($body) && $body{0} === '<') ? preg_replace('#<!--(.*)-->#iUsm', '', $body) : $body;
		// Remove meta characters.
		$body = ltrim($body, "\x00..\x20");
		return $body;
	}

	/**
	 * Constructor.
	 *
	 * @param string $url The URL to create the resource from.
	 * @param \pdyn\cache\CacheInterface $cache A caching object.
	 * @param \pdyn\httpclient\HttpClientInterface $httpclient An HttpClientInterface instance.
	 * @param int $ttl How long the cached information will stay valid, in seconds.
	 * @param array $initialdata Initial local object cache information. This is NOT cached, so that should be done elsewhere
	 *                           if using this parameter.
	 * @return HttpResource An HttpResource object, or one of it's decendents.
	 */
	public function __construct($url, \pdyn\cache\CacheInterface $cache, \pdyn\httpclient\HttpClientInterface $httpclient, $ttl = 7200,
								array $initialdata = array()) {
		$url = \pdyn\datatype\Url::normalize($url);

		// Basic data + dependencies.
		$this->url = $url;
		$this->cache = $cache;
		$this->httpclient = $httpclient;
		$this->ttl = $ttl;

		// Cache data.
		$this->cachekey = sha1($this->url);
		$this->cacheexpiry = (time() + $this->ttl);

		// Initial data.
		$this->data = $initialdata;
	}

	/**
	 * Gets the filesize of the resource at $url
	 * Modified from http://codesnippets.joyent.com/posts/show/1214
	 *
	 * @param string $url The URL to get the filesize of
	 * @return mixed The filesize, or false on failure
	 */
	public function get_filesize($url = null) {
		if (empty($url)) {
			$url = $this->url;
		}
		return \pdyn\httputils\HttpUtilities::get_remote_filesize($url);
	}

	/**
	 * Return the link body text, uncompressed.
	 *
	 * @return string The uncompressed body text.
	 */
	public function body() {
		return (!empty($this->data['basic']['body'])) ? gzinflate(base64_decode($this->data['basic']['body'])) : '';
	}

	/**
	 * Get protected properties - used for read-only properties.
	 *
	 * @param string $name The name of the property.
	 * @return mixed The value of the property.
	 */
	public function __get($name) {
		return (isset($this->$name)) ? $this->$name : false;
	}

	/**
	 * Determine whether a protected property is set. Used for read-only properties.
	 * @param string $name The name of the property.
	 * @return bool Whether the property is set or not.
	 */
	public function __isset($name) {
		return (isset($this->$name)) ? true : false;
	}

	/**
	 * Get a single piece of resource data.
	 *
	 * @param string $type The type of information to fetch.
	 * @param  boolean $forcerefresh Whether to use local caches, or force a remote-refresh.
	 * @return mixed Whatever the return is for the requested data type.
	 */
	public function get($type, $forcerefresh = false) {
		if (!is_string($type) || !ctype_alpha($type)) {
			throw new Exception('Bad data type requested', Exception::ERR_BAD_REQUEST);
		}

		if ($forcerefresh !== true) {
			// If data is cached locally, return that.
			if (isset($this->data[$type])) {
				return $this->data[$type];
			}

			// Next, check cache.
			$cacheddata = $this->cache->get('link_'.$type, $this->cachekey);
			if (!empty($cacheddata)) {
				$this->data[$type] = $cacheddata['data'];
				return $this->data[$type];
			}
		}

		// No data cached or force refresh.
		$function = 'fetch_'.$type;
		if (method_exists($this, $function)) {
			$this->data[$type] = $this->$function();
			$this->cache->store('link_'.$type, $this->cachekey, $this->data[$type], $this->cacheexpiry);
			return $this->data[$type];
		}
		return false;
	}

	/**
	 * Get multiple pieces of information.
	 *
	 * @param array $types An array of information to get. Leaving this empty will return all available information.
	 * @param bool $forcerefresh Whether to force the class to refresh the information. If false, will use local caches if available.
	 * @return array An array of resource information, with keys matching as many of $types as possible.
	 */
	public function get_all(array $types, $forcerefresh = false) {
		$types = array_flip($types);
		$return = [];

		if ($forcerefresh !== true) {
			// Check locally cached data.
			$localcache = array_intersect_key($this->data, $types);
			if (!empty($localcache)) {
				if (count($localcache) === count($types)) {
					return $localcache;
				} else {
					$return = array_merge($return, $localcache);
					unset($localcache);
					$types = array_diff_key($types, $return);
				}
			}

			// Check dbcache.
			$dbcached = $this->cache->get_all($this->cachekey, array_flip($types), 'link_');
			if (!empty($dbcached)) {
				foreach ($dbcached as $type => $cacherec) {
					$this->data[$type] = $cacherec['data'];
					$return[$type] = $this->data[$type];
					unset($types[$type]);
				}
				if (empty($types)) {
					return $return;
				}
			}
		}

		// Fetch any remaining data.
		foreach ($types as $type => $nothing) {
			$function = 'fetch_'.$type;
			if (method_exists($this, $function)) {
				$this->data[$type] = $this->$function();
				$return[$type] = $this->data[$type];
				$this->cache->store('link_'.$type, $this->cachekey, $this->data[$type], $this->cacheexpiry);
			}
		}

		return $return;
	}

	/**
	 * Get basic information.
	 *
	 * @return array An array of basic resource information.
	 */
	protected function fetch_basic() {
		$response = $this->httpclient->get($this->url);
		if (!empty($response)) {
			$link_info = [
				'mime_type' => mb_strtolower($response->mime_type()),
				'body' => base64_encode(gzdeflate(htmlentities($response->body()), 9)),
				'url' => $this->url,
			];
			$link_info['handler'] = static::detect_resource_type($this->url, $link_info['mime_type'], $link_info['body']);
			return $link_info;
		} else {
			return [
				'mime_type' => 'text/plain',
				'body' => '',
				'url' => $this->url,
				'handler' => '\\'.get_called_class(),
			];
		}
	}
}
