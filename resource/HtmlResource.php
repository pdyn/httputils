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
 * This handles HTML resources.
 */
class HtmlResource extends \pdyn\httputils\HttpResource {
	/** Priority of the class for handling the resource. If multiple classes match a link, the higher priority class wins. */
	const PRIORITY = 1;

	/** @var array Array of metatag information */
	protected $metatags = null;

	/** @var array Array of opengraph information */
	protected $og = null;

	/**
	 * Determine whether the class applies to the passed resource information.
	 *
	 * @param string $url The URL of the resource.
	 * @param string $mime A mime type.
	 * @param string $body The received body text.
	 * @return bool Whether the class can handle the resource.
	 */
	public static function applies($url, $mime, $body) {
		$body = \pdyn\httputils\HttpResource::cleantext($body);
		$indicators = ['<!doctype', '<html'];
		foreach ($indicators as $indicator) {
			if (mb_stripos($body, $indicator) === 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Extract metatags and opengraph data and save to instance.
	 */
	protected function extract_data() {
		if ($this->metatags === null || $this->og === null) {
			$this->get('basic');
			$html = new \pdyn\datatype\Html($this->body());
			if ($this->metatags === null) {
				$this->metatags = $html->extract_metatags();
			}
			if ($this->og === null) {
				$this->og = $html->extract_opengraph();
			}
		}
	}

	/**
	 * Force UTF-8 encoding on a string.
	 *
	 * @param string $s A string of questionable encoding.
	 * @return string A UTF-8 string.
	 */
	protected function force_utf8($s) {
		return \pdyn\datatype\Text::force_utf8($s);
	}

	/**
	 * Fetch meta information - author, title, description.
	 *
	 * @return array Array of meta information.
	 */
	protected function fetch_meta() {
		$this->extract_data();

		$return = [
			'title' => $this->url,
			'description' => '',
			'author' => '',
			'links' => [],
		];

		// Meta link tags.
		if (!empty($this->metatags['link'])) {
			$return['links'] = $this->metatags['link'];
		}

		// Title.
		if (isset($this->og['title'])) {
			$return['title'] = $this->force_utf8($this->og['title']);
		} elseif (isset($this->metatags['title'])) {
			$return['title'] = $this->force_utf8($this->metatags['title']);
		} else {
			preg_match('#<title>(.+)</title>#isU', $this->body(), $matches);
			$return['title'] = (isset($matches[1]))
				? $this->force_utf8($matches[1]) : $this->url;
		}

		// Description.
		if (isset($this->og['description'])) {
			$return['description'] = $this->force_utf8($this->og['description']);
		} elseif (isset($this->metatags['description'])) {
			$return['description'] = $this->force_utf8($this->metatags['description']);
		}

		return $return;
	}

	/**
	 * Fetch applicable thumbnail images.
	 *
	 * @return array Array of thumbnail images, relative to basedir.
	 */
	protected function fetch_images() {
		$this->extract_data();
		$images = [];

		// Opengraph.
		if (!empty($this->og['image'])) {
			$images[] = $this->og['image'];
		}

		// Link rel="image_url" tag.
		if (!empty($this->metatags['link']['image_src'])) {
			$images[] = $this->metatags['link']['image_src'];
		}

		// Extract <img> tags.
		$html = new \pdyn\datatype\Html($this->body(), $this->url);
		$img_tags = $html->extract_images();
		if (!empty($img_tags)) {
			$images = array_merge($images, $img_tags);
		}

		$images[] = \pdyn\httputils\HttpResource::get_default_thumbnail();

		return $images;
	}

	/**
	 * Extract linked RSS feeds.
	 *
	 * @return array Array of linked RSS feed URLs.
	 */
	protected function fetch_feeds() {
		$html = new \pdyn\datatype\Html($this->body(), $this->url);
		return $html->extract_rssfeeds();
	}
}
