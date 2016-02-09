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

/**
 * Represents an incoming request.
 */
class HttpRequest {
	use \pdyn\orm\ReadOnlyPropertiesTrait;

	/** @var string The request method. */
	protected $method = 'get';

	/** @var string The request URI. */
	protected $uri;

	/** @var bool Whether the request is secure. */
	protected $ishttps = false;

	/**
	 * Constructor.
	 *
	 * @param array $serverarray The PHP $_SERVER array.
	 */
	public function __construct($serverarray, $relroot) {
		$this->serverarray = $serverarray;

		if (isset($this->serverarray['REQUEST_METHOD'])) {
			$this->method = strtolower($this->serverarray['REQUEST_METHOD']);
		}

		if (isset($this->serverarray['REQUEST_URI'])) {
			$this->set_requesturi($this->serverarray['REQUEST_URI'], $relroot);
		}

		$this->ismobile = $this->ismobile();
		$this->ishttps = (isset($this->serverarray['HTTPS'])) ? true : false;
	}

	/**
	 * Determine if the request is from a mobile device.
	 *
	 * @return bool Whether the request is from a mobile device (true) or not (false).
	 */
	protected function ismobile() {
		if (isset($this->serverarray['HTTP_USER_AGENT'])) {
			if (strpos(strtolower($this->serverarray['HTTP_USER_AGENT']), 'iphone') !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get a request header value.
	 *
	 * @param string $header The header to get.
	 * @return mixed The value of the header, or null if not present.
	 */
	public function header($header) {
		$header = strtolower($header);
		switch ($header) {
			case 'accept':
				return (!empty($_SERVER['HTTP_ACCEPT'])) ? $_SERVER['HTTP_ACCEPT'] : null;

			default:
				return null;
		}
	}

	/**
	 * Determine the media type of the request based on the ACCEPT header.
	 *
	 * @return string The requested media type.
	 */
	public function requested_mediatype() {
		$accept = $this->header('accept');
		$accept = explode(',', $accept);
		$accept = explode('/', $accept[0]);
		return $accept[0];
	}

	/**
	 * Determine if the request accepts a given encoding.
	 *
	 * @param string $encoding The encoding to check for support.
	 * @return bool Whether the request accepts the given encoding.
	 */
	public function accepts_encoding($encoding) {
		if (!isset($this->serverarray['HTTP_ACCEPT_ENCODING'])) {
			return false;
		}

		$acceptedencodings = explode(',', $this->serverarray['HTTP_ACCEPT_ENCODING']);
		foreach ($acceptedencodings as $enc) {
			$enc = trim($enc);
			if ($encoding === $enc) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get raw request data.
	 *
	 * @return string Raw request data.
	 */
	public function data() {
		return file_get_contents('php://input');
	}

	/**
	 * Get received JSON data.
	 *
	 * @return array Array of received data from a JSON request.
	 */
	public function jsondata() {
		$received = $this->data();
		$received = @json_decode($received, true);
		return (!empty($received) && is_array($received)) ? $received : [];
	}

	/**
	 * Set the internal request URI.
	 *
	 * @param string $requesturi The new request URL to set.
	 * @param string $relroot The relative root to use.
	 */
	public function set_requesturi($requesturi, $relroot = '') {
		// Remove duplicate slashes.
		$requesturi = str_replace('//', '/', $requesturi);

		// Strip out any relative path, so we just get an internal path.
		if (!empty($relroot) && mb_strpos($requesturi, $relroot) === 0) {
			$requesturi = mb_substr($requesturi, mb_strlen($relroot));
		}

		$requesturi = trim($requesturi, '/');

		// Strip out query string.
		$q_pos = mb_strpos($requesturi, '?');
		if ($q_pos !== false) {
			$this->query = trim(mb_substr($requesturi, $q_pos), '?');
			$requesturi = mb_substr($requesturi, 0, $q_pos);
		}

		$this->uri = '/'.$requesturi;
	}
}
