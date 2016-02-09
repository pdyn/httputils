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

class Utils {
	/**
	 * Send an HTTP status code to the browser.
	 *
	 * @param int $code The status code (404, 500, etc)
	 * @param string $desc A message to send. If omitted, a standard message corresponding to $code will be used.
	 * @param bool $die Whether to die after the status message.
	 * @return bool Success/Fail
	 */
	public static function send_status_code($code, $desc = null, $die = true) {
		$code = (int)$code;
		$codes = [
			// 1xx Informational.
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',

			// 2xx Success.
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			208 => 'Already Reported',

			// 3xx Redirection.
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			//306 => 'Switch Proxy',
			307 => 'Temporary Redirect',
			308 => 'Permanent Redirect',

			// 4xx Client Error.
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Payload Too Large',
			414 => 'URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Range Not Satisfiable',
			417 => 'Expectation Failed',
			418 => 'I\'m a teapot',
			419 => 'Authentication Timeout',
			421 => 'Misdirected Request',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			426 => 'Upgrade Required',
			428 => 'Precondition Required',
			429 => 'Too Many Requests',
			431 => 'Request Header Fields Too Large',
			451 => 'Unavailable For Legal Reasons',

			// 5xx Server Error.
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			508 => 'Loop Detected',
			510 => 'Not Extended',
			511 => 'Network Authentication Required',
		];

		if (empty($codes[$code])) {
			$code = 500;
		}

		$serverprotocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
		header($serverprotocol.' '.$code.' '.$codes[$code], true, $code);

		if ($die === true) {
			if ($code === 204) {
				header('Content-Length: 0', true);
				header('Content-Type: text/html', true);
				flush();
			}

			echo $code.': ';
			echo (empty($desc)) ? $codes[$code] : $desc;
			die();
		}
		return true;
	}

	/**
	 * Send an HTTP status code, and kill the script.
	 *
	 * @param int $code The integer status code (ex. 404).
	 * @param string $msg The message to include with the response.
	 */
	public static function die_with_status_code($code, $msg = null) {
		static::send_status_code($code, $msg);
		die();
	}

	/**
	 * Send headers to force the browser to refresh it's cache.
	 */
	public static function headers_force_cache_refresh() {
		$ts = gmdate('D, d M Y H:i:s').' GMT';
		header('Expires: '.$ts);
		header('Last-Modified: '.$ts);
		header('Pragma: no-cache');
		header('Cache-Control: no-cache, must-revalidate');
	}

	/**
	 * Send headers to allow the browser to cache, specific to a file.
	 *
	 * @param string $file A unique identifier for the file. I.e. the absolute filename.
	 * @param int $mtime The timestamp the file was last updated (filemtime)
	 * @param bool $private Set cache-control to public (false) or private (true)
	 */
	public static function caching_headers($file, $mtime = null, $private = true) {
		if ($mtime === null) {
			$mtime = filemtime($file);
		}
		$mtimegmt = gmdate('r', $mtime);
		$etag = md5($mtime.$file);
		header('ETag: "'.$etag.'"');

		header('Expires: '.gmdate('r', time() + 2592000) . ' GMT'); //expires in 30 days
		header('Last-Modified: '.$mtimegmt);

		if ($private === true) {
			header('Pragma: no-cache');
			header('Cache-Control: private, must-revalidate, no-cache');
		} else {
			header('Cache-Control: public');
		}

		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] === $mtimegmt) {
			header('HTTP/1.1 304 Not Modified');
			exit();
		}

		if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
			if (str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) === $etag) {
				header('HTTP/1.1 304 Not Modified');
				exit();
			}
		}
	}

	/**
	 * Serve a file.
	 *
	 * Includes support for range requests/partial content.
	 *
	 * @param $file The absolute filename
	 * @param $mime The mimetype of the file.
	 * @param $filename The filename to serve the file as.
	 */
	public static function serve_file($file, $mime = '', $filename = '', $forcedownload = false) {
		if (!file_exists($file)) {
			throw new Exception('File not found', Exception::ERR_RESOURCE_NOT_FOUND);
		}
		if (empty($mime)) {
			$mime = \pdyn\filesystem\FilesystemUtils::get_mime_type($file);
		}
		if (empty($filename)) {
			$filename = basename($file);
		}

		static::caching_headers($file, filemtime($file), true);

		clearstatcache();
		$fp = fopen($file, 'r');
		$size = filesize($file);
		$length = $size;
		$start = 0;
		$end = $size - 1;
		header('Accept-Ranges: 0-'.$length);
		if (isset($_SERVER['HTTP_RANGE'])) {
			$c_start = $start;
			$c_end = $end;
			list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			if (strpos($range, ',') !== false) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
				exit;
			}

			if ($range == '-') {
				$c_start = $size - mb_substr($range, 1);
			} else {
				$range = explode('-', $range);
				$c_start = $range[0];
				$c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}
			$c_end = ($c_end > $end) ? $end : $c_end;
			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
				exit;
			}
			$start = $c_start;
			$end = $c_end;
			$length = $end - $start + 1;
			fseek($fp, $start);
			header('HTTP/1.1 206 Partial Content');
		}
		header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
		header('Content-Length: '.$length);
		header('Content-Type: '.$mime);
		if ($forcedownload === true) {
			header('Content-disposition: attachment; filename="'.$filename.'"');
		}

		$buffer = 1024 * 8;
		while (!feof($fp) && ($p = ftell($fp)) <= $end) {
			if ($p + $buffer > $end) {
				$buffer = $end - $p + 1;
			}
			set_time_limit(0);
			echo fread($fp, $buffer);
			flush();
		}

		fclose($fp);
	}

	/**
	 * Redirect the user to a URL.
	 *
	 * @param string $url The URL to redirect to.
	 */
	public static function redirect($url) {
		header('Location: '.$url);
		die();
	}
}