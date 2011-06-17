<?php
/**
 * Converts WikiCreole, universal wiki markup, into HTML.
 *
 * @package WikiCreole
 * @author Paul Garvin <paul@paulgarvin.net>
 * @copyright Copyright 2011 Paul Garvin.
 * @license MIT License
 *
 * Copyright (c) 2011 Paul Garvin <paul@paulgarvin.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */

/**
 * Converts WikiCreole, universal wiki markup, into HTML.
 *
 * Usage:
 * <code>
 $parser = new WikiCreole($base_url_for_wiki_links, $base_url_for_images, $list_of_existing_page_slugs);
 * echo $parser->parse($wiki_markup);
 * </code>
 * The $base_url_for_wiki_links and $base_url_for_images are required, but can be
 * relative (path only) or absolute (scheme & host).
 *
 * $list_of_existing_page_slugs is optional. The keys of the array must be just the URL slug
 * for a page after any illegal characters have stripped and formatting been applied (i.e.
 * spaces to dashes). See {@link linkCallback, $url_special_chars} for more info on the URL
 * formatting. If this array is provided a class of `notcreated` is given to the link and
 * a title of `This wiki page does not exist yet. Click to create it.` The class and title
 * may be configurable in future versions. If no array is passed no class or title is given
 * to the link.
 *
 * A class of `external` is given to external (not wiki) links. This may be configurable
 * in future versions.
 *
 * Known issues:
 * - Multi-line list items do not work.
 * - Putting }}} inside a no wiki tag will trip up the parser.
 * - Macro/placeholders are not implemented.
 * - Otherwise everything in the Creole 1.0 spec works.
 *
 * @package WikiCreole
 * @link http://www.wikicreole.org/
 * @todo Make multiline list items work
 * @todo Implement Placeholder/Macro <<<x>>>
 * @todo Solve embeding }}} in nowiki tags
 */
class WikiCreole
{
	/**
	 * Regex to recognise URLs in text.
	 * Based on http://daringfireball.net/2010/07/improved_regex_for_matching_urls
	 * with extra info from http://en.wikipedia.org/wiki/Percent-encoding
	 * and some other URL regexs I've seen around.
	 * Characters allowed in URLs: a-z0-9_-.~
	 * Reserved characters, have special meaning and will appear in URLs: !*'();:@&=+$,/?#[]
	 * That leaves: `^{}\|"<> as ones that shouldn't show up.
	 * @var string
	 */
	const URL_REGEX = '(?:(https?|ftps?|sftp|news|mailto|irc|cvs|svn|git|bzr)://|[a-zA-Z0-9.\-]+\.[a-z]{2,4}\/)(?:[^\s{}<>"`]+)+(?:\(([^\s{}<>"`])*\)|[^\s`!?.()\[\]{};:\'",<>])';

	/**
	 * Characters that should be replaced in URL slugs.
	 * @var array
	 */
	protected $url_special_chars = array('`', '#', '%', '^', '&', '*', '=', '[', ']',
									 	 '{', '}', '|', '\\', '\'', '"', '<', '>', '/', '?');

	/**
	 * Collection of all block type nowiki elements found in markup.
	 * @var array
	 */
	protected $nowikiBlocks = array();

	/**
	 * Collection of all inline type nowiki elements found in markup.
	 * @var array
	 */
	protected $nowikiInline = array();

	/**
	 * Collection of all lists found in the markup.
	 * @var array
	 */
	protected $lists = array();

	/**
	 * Collection of all tables found in markup.
	 * @var array
	 */
	protected $tables = array();

	/**
	 * Collection of all headings found in markup.
	 * @var array
	 */
	protected $headings = array();

	/**
	 * Base part of URL to be used for wiki links.
	 * @var string
	 */
	protected $urlBase;

	/**
	 * Base part of URL to be used for wiki images.
	 * @var string
	 */
	protected $imgBase;

	/**
	 * Collection of existing wiki pages, used to make non-existing page links red.
	 * @var array
	 */
	protected $existingPages = array();

	/**
	 * Constructor.
	 * @param string $urlBase
	 * @param string $imgBase
	 * @param array $pages
	 * @return WikiCreole
	 */
	public function __construct($urlBase, $imgBase, $pages = array())
	{
		$this->urlBase = $urlBase;
		$this->imgBase = $imgBase;
		if (!empty($pages) && is_array($pages)) {
			$this->existingPages = $pages;
		}
	}
	
	/**
	 * Clear out the list of block level elements that are used for parsing to make way for a new run.
	 * @return void
	 */
	public function reset()
	{
		$this->nowikiBlocks = array();
		$this->nowikiInline = array();
		$this->lists = array();
		$this->tables = array();
		$this->headings = array();
		$this->images = array();
		$this->links = array();
	}

	public function parse($markup)
	{
		$this->reset();

		// These are split up into different methods to make tesing easier.
		$markup = $this->preformat($markup);

		$markup = $this->matchNowikiBlocks($markup);

		$markup = $this->matchNowikiInline($markup);

		$markup = $this->matchLists($markup);
		
		$markup = $this->matchTables($markup);
		
		$markup = $this->matchHeadings($markup);
		
		$markup = $this->matchHorizontalRules($markup);

		$markup = $this->makeParagraphs($markup);

		return $this->recombine($markup);
	}

	/**
	 * Do some preformatting to the wiki text before actual parsing.
	 * @param string $markup
	 * @return string
	 */
	public function preformat($markup)
	{
		// Normalize end of line characters to LF
		$markup = str_replace("\r", '', $markup);

		// Get rid of whitespace if it's the only thing on line
		$markup = preg_replace('/^[ \t]+$/m', '', $markup);

		// Escape any HTML special chars now while we can
		$markup = htmlspecialchars($markup, ENT_QUOTES, 'UTF-8', false);

		return $markup;
	}

	/**
	 * Create paragraphs wrapped in <p> tags from remainder of markup (separated by double LFs).
	 * @param string $markup
	 * @return string
	 */
	public function makeParagraphs($markup)
	{
		$markup = trim($markup);

		// Consolidate more than two LFs into just two LFs
		$markup = preg_replace('/\n{3,}/', "\n\n", $markup);

		$fragments = explode("\n\n", $markup);

		foreach ($fragments as $idx => $str) {
			if (empty($str)) {
				continue;
			}
			$str = trim($str);
			$last = strlen($str) - 1;
			if ((($str[0] == '<') && ($str[$last] == '>')) ||
				(($str[0] == '@') && ($str[$last] == '@'))) {
				continue;

			// Catch img tags but don't put them inside p tags.
			} elseif (($str[0] == '{' && $str[1] == '{') &&
					  ($str[$last - 1] == '}' && $str[$last] == '}')) {
				$fragments[$idx] = $this->parseInline($str);

			} else {
				$fragments[$idx] = '<p>' . $this->parseInline($str) . '</p>';
			}
		}

		return implode("\n", $fragments);
	}

	/**
	 * Handle inline formatting such as bold, italic, links & images.
	 * @param string $souce
	 * @return string
	 */
	public function parseInline($markup)
	{
		// These are split up into different methods to make tesing easier.
		$markup = $this->matchBold($markup);

		$markup = $this->matchItalic($markup);

		$markup = $this->matchStrikethrough($markup);

		$markup = $this->matchUnderline($markup);

		$markup = $this->matchSubAndSup($markup);

		$markup = $this->linkFreeUrls($markup);

		$markup = $this->matchImages($markup);

		$markup = $this->matchLinkTags($markup);

		$markup = str_replace('\\\\', '<br />', $markup);

		return $markup;
	}

	/**
	 * Re-insert all of the pulled block elements back into the markup.
	 * @param string $markup Markup with block markers
	 * @return string
	 */
	public function recombine($markup)
	{
		// Replace any '%s' in the markup with another marker.
		$markup = str_replace('%s', '~~s~~', $markup);

		$markup = str_replace('@nwb@', '%s', $markup);
		$markup = vsprintf($markup, $this->nowikiBlocks);

		$markup = str_replace('@list@', '%s', $markup);
		$markup = vsprintf($markup, $this->lists);

		$markup = str_replace('@table@', '%s', $markup);
		$markup = vsprintf($markup, $this->tables);

		$markup = str_replace('@head@', '%s', $markup);
		$markup = vsprintf($markup, $this->headings);

		$markup = str_replace('@nwi@', '%s', $markup);
		$markup = vsprintf($markup, $this->nowikiInline);

		$markup = str_replace('~~s~~', '%s', $markup);

		return $markup;
	}

	/**
	 * Find all 'nowiki' blocks and pass them to the callback function for replacing.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchNowikiBlocks($markup)
	{
		return preg_replace_callback('/\n{{{\n(.*?)\n}}}\n/s',
									 array($this, 'nowikiBlockCallback'),
									 $markup);
	}

	/**
	 * Strip out 'nowiki' blocks out of markup and save for later.
	 * @param array $matches Matches from preg_replace_callback()
	 * @return string
	 */
	public function nowikiBlockCallback($matches)
	{
		$this->nowikiBlocks[] = "<pre>\n" . $matches[1] . "\n</pre>";
		return "\n@nwb@\n";
	}

	/**
	 * Find all inline 'nowiki' elements and pass them to the callback function for replacing.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchNowikiInline($markup)
	{
		return preg_replace_callback('/{{{(.+?)}}}/',
									 array($this, 'nowikiInlineCallback'),
									 $markup);
	}

	/**
	 * Strip out inline 'nowiki' elements out of markup and save for later.
	 * @param array $matches Matches from preg_replace_callback()
	 * @return string
	 */
	public function nowikiInlineCallback($matches)
	{
		$this->nowikiInline[] = '<tt>' . $matches[1] . '</tt>';
		return '@nwi@';
	}

	/**
	 * Find all lists and pass them to the callback function for replacing.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchLists($markup)
	{
		return preg_replace_callback('/^[\h]*(?:[*][^*#]|[#][^#*]).*$(?:\n[\h]*[*#]+.*)*/m',
									 array($this, 'listCallback'),
									 $markup);
	}

	/**
	 * Handle list blocks in markup.
	 * @param array $matches Matches from preg_replace_callback()
	 * @return string
	 */
	public function listCallback($matches)
	{
		$lines = array();

		if (!preg_match_all('/^[\h]*([*#]+)[\h]*(.+)$/m', $matches[0], $lines, PREG_SET_ORDER)) {
			return $matches[0];
		}
		$level = 0;
		$stack = array();
		$buffer = '';
		foreach ($lines as $line) {
			$bullet = $line[1];
			$text = $line[2];
			$symbol = $bullet[0];
			$diff = strlen($bullet) - $level;

			if ($diff == 1) {
				$level++;
				$stack[$level] = array('type' => $symbol, 'count' => 0);
				$buffer .= ($symbol == '*') ? "\n<ul>\n" : "\n<ol>\n";

			} elseif ($diff < 0) {
				for ($i = $diff; $i < 0; $i++) {
					$buffer .= ($stack[$level]['type'] == '*') ? "</li>\n</ul>\n" : "</li>\n</ol>\n";
					unset($stack[$level]);
					$level--;
				}

			} elseif ($diff == 0)  {
				if (($stack[$level]['type'] != $symbol)) {
					$buffer .= ($symbol == '*') ? "</li>\n</ul>\n" : "</li>\n</ol>\n";
					$buffer .= ($stack[$level]['type'] == '*') ? "\n<ul>\n" : "\n<ol>\n";
					$stack[$level] = array('type' => $symbol, 'count' => 0);
				}

			} else {
				return $matches[0];
			}

			if ($stack[$level]['count'] > 0) {
				$buffer.= "</li>\n";
			}
			$buffer .= '<li>' . $this->parseInline($text);
			$stack[$level]['count']++;
		}

		// assume $level is at least still 1
		while ($level > 0) {
			$buffer .= ($stack[$level]['type'] == '*') ? "</li>\n</ul>\n" : "</li>\n</ol>\n";
			$level--;
		}

		$this->lists[] = ltrim($buffer);
		return "\n@list@\n";
	}

	/**
	 * Find all tables and pass them to the callback function for replacing.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchTables($markup)
	{
		return preg_replace_callback('/^[ \t]*[|].*$(?:\n[ \t]*[|].*)*/m',
									 array($this, 'tableCallback'),
									 $markup);
	}

	/**
	 * Handle tables in markup.
	 * @param array $matches Matches from preg_replace_callback()
	 * @return string
	 */
	public function tableCallback($matches)
	{
		$rows = explode("\n", $matches[0]);
		$buffer = "<table>\n";
		foreach ($rows as $row) {
			$buffer .= "<tr>\n";
			$row = trim($row);
			$row = trim($row, '|');
			$cells = explode('|', $row);
			foreach ($cells as $idx => $cell) {
				// fix urls and images in cells being split
				if ((strpos($cell, '[[') !== false) && strpos($cells[$idx+1], ']]')) {
					$cells[$idx] = $cells[$idx] . '|' . $cells[$idx+1];
					unset($cells[$idx+1]);
				} elseif ((strpos($cell, '{{') !== false) && strpos($cells[$idx+1], '}}')) {
					$cells[$idx] = $cells[$idx] . '|' . $cells[$idx+1];
					unset($cells[$idx+1]);
				}
			}
			foreach ($cells as $text) {
				$text = trim($text);
				if ($text[0] == '=') {
					$buffer .= '<th>' . $this->parseInline(substr($text, 1)) . "</th>\n";
				} else {
					$buffer .= '<td>' . $this->parseInline($text) . "</td>\n";
				}
			}
			$buffer .= "</tr>\n";
		}
		$buffer .= "</table>\n";
		$this->tables[] = $buffer;
		return "\n@table@\n";
	}

	/**
	 * Find all heading elements and pass them to the callback function for replacing.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchHeadings($markup)
	{
		return preg_replace_callback('/^[\h]*(={1,6})(.*)$/m',
									 array($this, 'headingCallback'),
									 $markup);
	}

	/**
	 * Handle replacement of heading wiki markup with <hX> tags.
	 * @param array $matches Matches from preg_replace_callback()
	 * @return string
	 */
	public function headingCallback($matches)
	{
		$level = strlen($matches[1]);
		$text = trim($matches[2], ' =');
		$this->headings[] = "<h$level>$text</h$level>";
		return "\n@head@\n";
	}

	/**
	 * Find all horizontal rule elements and replace them with HTML equivalent.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchHorizontalRules($markup)
	{
		return preg_replace('/^----$/m', "\n<hr />\n", $markup);
	}

	/**
	 * Find all bold text elements and replace them with HTML equivalent.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchBold($markup)
	{
		return preg_replace_callback('/(?<!~)\*\*(.+?)\*\*/s',
									 array($this, 'fontCallback'),
									 $markup);
	}

	/**
	 * Find all italic text elements and replace them with HTML equivalent.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchItalic($markup)
	{
		return preg_replace_callback('/(?<!~|:)\/\/(.+?)(?<!:)\/\//s',
									 array($this, 'fontCallback'),
									 $markup);
	}

	/**
	 * Find all strikethrough text elements and replace them with HTML equivalent.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchStrikethrough($markup)
	{
		return preg_replace_callback('/(?<!~)--(.+?)--/s',
									 array($this, 'fontCallback'),
									 $markup);
	}

	/**
	 * Find all underline text elements and replace them with HTML equivalent.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchUnderline($markup)
	{
		return preg_replace_callback('/(?<!~)__(.+?)__/s',
									 array($this, 'fontCallback'),
									 $markup);
	}

	/**
	 * Handle replacement of bold, italic, strikethrough & underline in markup.
	 * @param array $matches Matches from preg_replace_callback()
	 * @return string
	 */
	public function fontCallback($matches)
	{
		$patterns = array('/(?<!~)\*\*/', '#(?<!:|~)//#', '/(?<!~)--/', '/(?<!~)__/');
		$results = array();
		$dummy_matches = array();
		$char = $matches[0][0];
		foreach ($patterns as $pattern) {
			$results[] = preg_match_all($pattern, $matches[1], $dummy_matches);
		}
		foreach ($results as $result) {
			if (($result != 0) and ($result != 2)) {
				return $matches[0];
			}
		}
		switch ($char) {
			case '*':
				return '<strong>' . $matches[1] . '</strong>';
			case '/':
				return '<em>' . $matches[1] . '</em>';
			case '-':
				return '<span style="text-decoration: line-through">' . $matches[1] . '</span>';
			case '_':
				return '<span style="text-decoration: underline">' . $matches[1] . '</span>';
			default:
				return $matches[0];
		}
	}

	/**
	 * Find all subscript and superscript elements and replace them with HTML equivalent.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchSubAndSup($markup)
	{
		$markup = preg_replace('/(?<!~)\^\^(.+?)\^\^/', '<sup>$1</sup>', $markup);
		return    preg_replace('/(?<!~),,(.+?),,/', '<sub>$1</sub>', $markup);
	}

	/**
	 * Handle replacement of subscript and superscript elements in markup.
	 * @param array $matches Matches from preg_replace_callback()
	 * @return string
	 */
	public function subSupCallback($matches)
	{
		$char = $matches[0][0];
		if ($char == '^') {
			return '<sup>' . $matches[1] . '</sup>';
		} elseif ($char = ',') {
			return '<sub>' . $matches[1] . '</sub>';
		} else {
			return $matches[0];
		}
	}

	/**
	 * Find all image tags and replace them with HTML equivalent.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchImages($markup)
	{
		return preg_replace_callback('/(?<!~){{(.+?)}}/',
									 array($this, 'imgCallback'),
									 $markup);
	}
		
	/**
	 * Handle creation of image tags in markup.
	 * @param array $matches Matches from preg_replace_callback()
	 * @return string
	 */
	public function imgCallback($matches)
	{
		if (strpos($matches[1], '|')) {
			$parts = explode('|', $matches[1]);
			if (count($parts) != 2) {
				return $matches[0];
			}
			list($url, $alt) = $parts;
		} else {
			$url = $matches[1];
			$alt = '';
		}
		if (!strpos($url, '/')) {
			$url = $this->imgBase . $url;
		}

		$image = "<img src=\"$url\" ";
		if (!empty($alt)) {
			$image .= "alt=\"$alt\" ";
		}
		$image .= '/>';

		return $image;
	}

	/**
	 * Find all url/link tags and replace them with HTML equivalent.
	 * @param string $markup Source markup text
	 * @return string Processed markup text
	 */
	public function matchLinkTags($markup)
	{
		return preg_replace_callback('/(?<!~)\[\[(.+?)\]\]/',
									 array($this, 'linkCallback'),
									 $markup);
	}

	/**
	 * Handle url/wiki tags in markup.
	 * @param array $matches Matches from preg_replace_callback()
	 * @return string
	 */
	public function linkCallback($matches)
	{
		if (strpos($matches[1], '|')) {
			$parts = explode('|', $matches[1]);
			if (count($parts) != 2) {
				return $matches[0];
			}
			list($url, $text) = $parts;
		} else {
			$url = $text = $matches[1];
		}
		if (preg_match('@' . self::URL_REGEX . '@', $url)) {
			$link = "<a href=\"$url\" class=\"external\">$text</a>";
		} else {
			$url = htmlspecialchars_decode($url, ENT_QUOTES);
			$url = str_replace(' ', '-', $url);
			$url = str_replace($this->url_special_chars, '', $url);
			$page = $url;
			$url = $this->urlBase . $page;
			if ($this->wikiPageExists($page)) {
				$link = "<a href=\"$url\">$text</a>";
			} else {
				$hover = 'This wiki page does not exist yet. Click to create it.';
				$link = "<a href=\"$url\" class=\"notcreated\" title=\"$hover\">$text</a>";
			}
		}
 
		return $link;
	}

	/**
	 * Converts anything that looks like a URL into an link.
	 * @param string $text Test in which to find links.
	 * @return string
	 */
	public function linkFreeUrls($text)
	{
		$repl = '<a href="$0" class="external">$0</a>';
		$text = preg_replace('@(?<= )' . self::URL_REGEX . '@', $repl, $text);
		return $text;
	}

	/**
	 * Internal function for checking if a page exists based on a user provided list.
	 * @param string $name
	 * @return bool
	 */
	public function wikiPageExists($name)
	{
		// Don't return false if we don't have a list of pages to check against.
		if (empty($this->existingPages)) {
			return true;
		}
		return in_array($this->existingPages, $name);
	}
}
