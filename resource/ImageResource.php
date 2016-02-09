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

namespace pdyn\httputils\resource;

use \pdyn\base\Exception;

/**
 * This handles image resources.
 */
class ImageResource extends \pdyn\httputils\HttpResource {
	/** Priority of the class for handling the resource. If multiple classes match a link, the higher priority class wins. */
	const PRIORITY = 1;

	/**
	 * Determine whether the class applies to the passed resource information.
	 *
	 * @param string $url The URL of the resource.
	 * @param string $mime A mime type.
	 * @param string $body The received body text.
	 * @return bool Whether the class can handle the resource.
	 */
	public static function applies($url, $mime, $body) {
		return (mb_strpos($mime, 'image/') === 0) ? true : false;
	}

	/**
	 * Fetch meta information - author, title, description.
	 *
	 * @return array Array of meta information.
	 */
	protected function fetch_meta() {
		return [
			'author' => '',
			'title' => $this->url,
			'description' => 'Image from '.$this->url
		];
	}

	/**
	 * Fetch applicable thumbnail images.
	 *
	 * @return array Array of thumbnail images, relative to basedir.
	 */
	protected function fetch_images() {
		return [
			$this->url,
			\pdyn\httputils\HttpResource::get_default_thumbnail()
		];
	}
}
