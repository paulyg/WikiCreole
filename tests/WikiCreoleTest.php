<?php
/**
 * WikiCreole is a library to convert Creole, a common & standardized wiki markup, into HTML.
 *
 * Copyright (c) 2011, 2012 Paul Garvin <paul@paulgarvin.net>
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

if (!defined('__DIR__')) {
    define('__DIR__', dirname(__FILE__));
}
require_once dirname(__DIR__) . '/WikiCreole.php';

/**
 * Unit tests for the WikiCreole class.
 *
 * This test class requires PHPUnit 3.4 or newer. To run the tests simply execute
 * <code>phpunit WikiCreoleTest</code> at a command line prompt in the same
 * directory this file is in.
 *
 * @package WikiCreole
 * @author Paul Garvin <paul@paulgarvin.net>
 * @copyright Copyright 2011, 2012 Paul Garvin.
 * @license MIT License
 * @link https://github.com/paulyg/WikiCreole Project Homepage
 * @link http://www.wikicreole.org/ Wiki Creole Homepage
 */
class WikiCreoleTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var WikiCreoleParser
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new WikiCreole(array(
            'urlBase' => '/wiki/',
            'imgBase' => '/wiki/images/'));
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testReset()
    {
        // Test that tags in $input1 do not bleed though to $output2.
        $input1 = "=Title1=\nThis is a test of the reset method.\n\n*One\n**Two\n*Three";
        $input2 = "This is a test of the reset method.\n\n{{{\nA block of preformatted text.\n}}}\n";

        $expected1 = "<h1>Title1</h1>\n<p>This is a test of the reset method.</p>\n<ul>\n<li>One\n<ul>\n<li>Two</li>\n</ul>\n</li>\n<li>Three</li>\n</ul>\n";
        $expected2 = "<p>This is a test of the reset method.</p>\n<pre>\nA block of preformatted text.\n</pre>";

        $output1 = $this->object->parse($input1);
        $output2 = $this->object->parse($input2);

        $this->assertEquals($expected1, $output1);
        $this->assertEquals($expected2, $output2);
    }

    public function testPreformat()
    {
        $input = "Testing. \r\nAnother line.\r\n   \r\nParagraph 2\r We only want to see one &amp; and < and \" encoded\r\n";
        $expected = "Testing. \nAnother line.\n\nParagraph 2 We only want to see one &amp; and < and \" encoded\n";
        $output = $this->object->preformat($input);
    }

    /**
     * @covers WikiCreoleParser::matchNowikiBlocks
     * @covers WikiCreoleParser::nowikiBlockCallback
     */
    public function testNowikiBlocks()
    {
        $input = "A paragraph.\n\n{{{\nSome text. some(\$code); \n//Some bold//\n}}}\n\nAnother paragraph.";
        $expected = "<p>A paragraph.</p>\n<pre>\nSome text. some(\$code); \n//Some bold//\n</pre>\n<p>Another paragraph.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchNowikiInline
     * @covers WikiCreoleParser::nowikiInlineCallback
     */
    public function testNowikiInline()
    {
        $input = 'Here is some inline text. {{{This should **be escaped**.}}}';
        $expected = '<p>Here is some inline text. <tt>This should **be escaped**.</tt></p>';
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchLists
     * @covers WikiCreoleParser::listCallback
     */
    public function testSimpleUnorderedList()
    {
        $input = "* Alpha\n* Beta\n* Gamma\n* Delta";
        $expected = "<ul>\n<li>Alpha</li>\n<li>Beta</li>\n<li>Gamma</li>\n<li>Delta</li>\n</ul>\n";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testSimpleOrderedList()
    {
        $input = "# One\n# Two\n# Three\n# Four";
        $expected = "<ol>\n<li>One</li>\n<li>Two</li>\n<li>Three</li>\n<li>Four</li>\n</ol>\n";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testOrderedListFunkyWhitespace()
    {
        $input = " #One\n # Two\n #Three\n# Four";
        $expected = "<ol>\n<li>One</li>\n<li>Two</li>\n<li>Three</li>\n<li>Four</li>\n</ol>\n";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testSimpleNestedUl()
    {
        $input = "* Item 1\n** Item 1.1\n* Item 2";
        $expected = "<ul>\n<li>Item 1\n<ul>\n<li>Item 1.1</li>\n</ul>\n</li>\n<li>Item 2</li>\n</ul>\n";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testComplicatedNestedList()
    {
        $input = <<<'EoWiki'
* FrontendController
## Does URL exist in //pages// table?
## Yes - what type is it?
### Static page: load model indicated by class_name and render
### Alias: grab what alias points to
### Function: Load controller indicated by class_name, get //ParamMap// from controller to remap params and call action
## No - bubble up the path
### Try to match each path segment above requested one. Matches must be a function, not static.
### If a match found load controller indicated by class_name, get //ParamMap// from controller to remap params and call action
### If no match found display 404 error
* Backend Controller/program flow
## Check PHP version, register_globals, magic_quotes
## Read in file with tm_bail function, only problem with reading in common.php before config.php is that common.php uses $db_config['prefix'] **Split common.php into check.php & bootstrap.php**
## Does [[http://www.wikipedia.org/wiki/installer|installer]] exist? Does config file exist?
### Installer & no config, include installer
### Installer & config, die
### No installer & no config, die
### Config & no installer, include installer
## Init Tm_Request object
## use hostname to set cookie constants
## Set error reporting
## date_default_timezone_set()
## session settings
## session_start()
## ob_start()
## Create [[Tm_AppController]] object with $db_config
## Connect to database & get options (Tm_Config object), allow to tm_bail
## Create Router object - do before checking auth since Auth may need to set error controller in router.
## Create View object
## Set Request, Router, View in AppController
## Check if user is auth'd
### if not send to errorNoAuth page
### User is auth'd, setup user/Locale settings
## Dispatch (Route)
## Render layout
## ob_end_flush()
* Plugins can do any/all of:
## Create a new page_type
## Register a content filter
*** Tm_ContentFilter::register()
*** $filter = Tm_ContentFilter::get('filter_name')
*** $html = $filter->parse($source)
## Register view helpers
*** Tm_ViewAbstract::registerHelper() or Tm_Helper_Abstract::register()
EoWiki;
        $expected = <<<'EoHtml'
<ul>
<li>FrontendController
<ol>
<li>Does URL exist in <em>pages</em> table?</li>
<li>Yes - what type is it?
<ol>
<li>Static page: load model indicated by class_name and render</li>
<li>Alias: grab what alias points to</li>
<li>Function: Load controller indicated by class_name, get <em>ParamMap</em> from controller to remap params and call action</li>
</ol>
</li>
<li>No - bubble up the path
<ol>
<li>Try to match each path segment above requested one. Matches must be a function, not static.</li>
<li>If a match found load controller indicated by class_name, get <em>ParamMap</em> from controller to remap params and call action</li>
<li>If no match found display 404 error</li>
</ol>
</li>
</ol>
</li>
<li>Backend Controller/program flow
<ol>
<li>Check PHP version, register_globals, magic_quotes</li>
<li>Read in file with tm_bail function, only problem with reading in common.php before config.php is that common.php uses $db_config[&#039;prefix&#039;] <strong>Split common.php into check.php &amp; bootstrap.php</strong></li>
<li>Does <a href="http://www.wikipedia.org/wiki/installer" class="external">installer</a> exist? Does config file exist?
<ol>
<li>Installer &amp; no config, include installer</li>
<li>Installer &amp; config, die</li>
<li>No installer &amp; no config, die</li>
<li>Config &amp; no installer, include installer</li>
</ol>
</li>
<li>Init Tm_Request object</li>
<li>use hostname to set cookie constants</li>
<li>Set error reporting</li>
<li>date_default_timezone_set()</li>
<li>session settings</li>
<li>session_start()</li>
<li>ob_start()</li>
<li>Create <a href="/wiki/Tm_AppController">Tm_AppController</a> object with $db_config</li>
<li>Connect to database &amp; get options (Tm_Config object), allow to tm_bail</li>
<li>Create Router object - do before checking auth since Auth may need to set error controller in router.</li>
<li>Create View object</li>
<li>Set Request, Router, View in AppController</li>
<li>Check if user is auth&#039;d
<ol>
<li>if not send to errorNoAuth page</li>
<li>User is auth&#039;d, setup user/Locale settings</li>
</ol>
</li>
<li>Dispatch (Route)</li>
<li>Render layout</li>
<li>ob_end_flush()</li>
</ol>
</li>
<li>Plugins can do any/all of:
<ol>
<li>Create a new page_type</li>
<li>Register a content filter
<ul>
<li>Tm_ContentFilter::register()</li>
<li>$filter = Tm_ContentFilter::get(&#039;filter_name&#039;)</li>
<li>$html = $filter-&gt;parse($source)</li>
</ul>
</li>
<li>Register view helpers
<ul>
<li>Tm_ViewAbstract::registerHelper() or Tm_Helper_Abstract::register()</li>
</ul>
</li>
</ol>
</li>
</ul>

EoHtml;
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchTables
     * @covers WikiCreoleParser::tableCallback
     */
    public function testSimpleTable()
    {
        $input = <<<'EoWiki'
|Irene|Paul|
|Wife|Husband|
|28|34|
|IT|Engineer|
EoWiki;
        $expected = <<<'EoHTML'
<table>
<tr>
<td>Irene</td>
<td>Paul</td>
</tr>
<tr>
<td>Wife</td>
<td>Husband</td>
</tr>
<tr>
<td>28</td>
<td>34</td>
</tr>
<tr>
<td>IT</td>
<td>Engineer</td>
</tr>
</table>

EoHTML;
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testTableHorizontalHeader()
    {
        $input = <<<'EoWiki'
|=Name|=Relationship|=Age|=Occupation|
|Irene|Wife|28|IT|
|Paul|Husband|34|Engineer|
EoWiki;
        $expected = <<<'EoHTML'
<table>
<tr>
<th>Name</th>
<th>Relationship</th>
<th>Age</th>
<th>Occupation</th>
</tr>
<tr>
<td>Irene</td>
<td>Wife</td>
<td>28</td>
<td>IT</td>
</tr>
<tr>
<td>Paul</td>
<td>Husband</td>
<td>34</td>
<td>Engineer</td>
</tr>
</table>

EoHTML;
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testTableVerticalHeader()
    {
        $input = <<<'EoWiki'
|=Name|Irene|Paul|
|=Relationship|Wife|Husband|
|=Age|28|34|
|=Occupation|IT|Engineer|
EoWiki;
        $expected = <<<'EoHTML'
<table>
<tr>
<th>Name</th>
<td>Irene</td>
<td>Paul</td>
</tr>
<tr>
<th>Relationship</th>
<td>Wife</td>
<td>Husband</td>
</tr>
<tr>
<th>Age</th>
<td>28</td>
<td>34</td>
</tr>
<tr>
<th>Occupation</th>
<td>IT</td>
<td>Engineer</td>
</tr>
</table>

EoHTML;
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testTableCenterAlign()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    public function testTableRightAlign()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    public function testMarkupInTables()
    {
        $input = <<<'EoWiki'
|=Directory|=Software Package|=Installed Version|=Current Version|
|blog|[[http://wordpress.org|Wordpress]]|3.0.5|3.0.5|
|forum|phpBB|3.0.5|**3.0.7pl1**|
|gallery2|[[Gallery|Menalto Gallery]]|2.3.3|//3.0//|
|kitchen|[[Wordpress]]|3.0.5|3.0.5|
EoWiki;
        $expected = <<<'EoHtml'
<table>
<tr>
<th>Directory</th>
<th>Software Package</th>
<th>Installed Version</th>
<th>Current Version</th>
</tr>
<tr>
<td>blog</td>
<td><a href="http://wordpress.org" class="external">Wordpress</a></td>
<td>3.0.5</td>
<td>3.0.5</td>
</tr>
<tr>
<td>forum</td>
<td>phpBB</td>
<td>3.0.5</td>
<td><strong>3.0.7pl1</strong></td>
</tr>
<tr>
<td>gallery2</td>
<td><a href="/wiki/Gallery">Menalto Gallery</a></td>
<td>2.3.3</td>
<td><em>3.0</em></td>
</tr>
<tr>
<td>kitchen</td>
<td><a href="/wiki/Wordpress">Wordpress</a></td>
<td>3.0.5</td>
<td>3.0.5</td>
</tr>
</table>

EoHtml;
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchHeadings
     * @covers WikiCreoleParser::headingCallback
     */
    public function testHeadingsH1()
    {
        $input = "=This is the page title.=";
        $expected = "<h1>This is the page title.</h1>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testHeadingsH3()
    {
        $input = "===This is a sub-section heading.===";
        $expected = "<h3>This is a sub-section heading.</h3>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testHeadingsLeadingWhitespace()
    {
        $input = " ==This is still a heading despite of the leading space.==";
        $expected = "<h2>This is still a heading despite of the leading space.</h2>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testNoBoldOrItalicInHeadings()
    {
        $input = "==This is an H2. **Maybe this is bold?** No. //How about italic?// No.==";
        $expected = "<h2>This is an H2. **Maybe this is bold?** No. //How about italic?// No.</h2>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testHeadingsNoClosingTags()
    {
        $input = "==This should still be rendered as a heading without the trailing tags.";
        $expected = "<h2>This should still be rendered as a heading without the trailing tags.</h2>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchHorizontalRules
     */
    public function testMatchHorizontalRules()
    {
        $input = "La-de-da this is some sample text.\n----\nThis is a new section.";
        $expected = "<p>La-de-da this is some sample text.</p>\n<hr />\n<p>This is a new section.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchBold
     * @covers WikiCreoleParser::fontCallback
     */
    public function testBold()
    {
        $input = 'Here is some text. **This is bold!** This is not.';
        $expected = '<p>Here is some text. <strong>This is bold!</strong> This is not.</p>';
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testMultilineBold()
    {
        $input = "Here is some text. **This is bold!\nThis is still bold.** This is not.";
        $expected = "<p>Here is some text. <strong>This is bold!\nThis is still bold.</strong> This is not.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchItalic
     * @covers WikiCreoleParser::fontCallback
     */
    public function testMatchItalic()
    {
        $input = 'Here is some text. //This is italic!// This is not.';
        $expected = '<p>Here is some text. <em>This is italic!</em> This is not.</p>';
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testMultilineItalic()
    {
        $input = "Here is some text. //This is italic!\nThis should still be italic.// This should not.";
        $expected = "<p>Here is some text. <em>This is italic!\nThis should still be italic.</em> This should not.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testBoldItalic()
    {
        $input = 'Wow you really **//have to check this out.//** It\'s so cool.';
        $expected = '<p>Wow you really <strong><em>have to check this out.</em></strong> It&#039;s so cool.</p>';
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testItalicBold()
    {
        $input = 'Wow you really //**have to check this out.**// It\'s so cool.';
        $expected = '<p>Wow you really <em><strong>have to check this out.</strong></em> It&#039;s so cool.</p>';
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchStrikethrough
     * @covers WikiCreoleParser::fontCallback
     */
    public function testMatchStrikethrough()
    {
        $input = 'I have a task. --This one is done!-- This one is not.';
        $expected = '<p>I have a task. <span style="text-decoration: line-through">This one is done!</span> This one is not.</p>';
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchUnderline
     * @covers WikiCreoleParser::fontCallback
     */
    public function testMatchUnderline()
    {
        $input = 'Read this. __I really want you to read this!__ I <3 this formatting.';
        $expected = '<p>Read this. <span style="text-decoration: underline">I really want you to read this!</span> I &lt;3 this formatting.</p>';
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchSubAndSup
     * @covers WikiCreoleParser::subSupCallback
     */
    public function testSub()
    {
        $input = "y = x^^2^^ + 5x + 3 is a quadratic function.";
        $expected = "<p>y = x<sup>2</sup> + 5x + 3 is a quadratic function.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchSubAndSup
     * @covers WikiCreoleParser::subSupCallback
     */
    public function testSup()
    {
        $input = "C,,2,,H,,4,, is a hyrdrocarbon more commonly known as Ethylene.";
        $expected = "<p>C<sub>2</sub>H<sub>4</sub> is a hyrdrocarbon more commonly known as Ethylene.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testMatchMonospace()
    {
        $input = "Put the source code in the ##src## directory and the tests in the ##test## directory.";
        $expected = "<p>Put the source code in the <code>src</code> directory and the tests in the <code>test</code> directory.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchImages
     * @covers WikiCreoleParser::imgCallback
     */
    public function testImagesWithAlt()
    {
        $input = "We are going to specify an inline image. {{hawaii.jpg|Beautiful Hawaii}} Isn't it beautiful?";
        $expected = "<p>We are going to specify an inline image. <img src=\"/wiki/images/hawaii.jpg\" alt=\"Beautiful Hawaii\" /> Isn&#039;t it beautiful?</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testImagesNoAlt()
    {
        $input = "Check out our 4th quarter earnings. {{earnings.png}} Look at those sales!";
        $expected = "<p>Check out our 4th quarter earnings. <img src=\"/wiki/images/earnings.png\" /> Look at those sales!</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::matchLinkTags
     * @covers WikiCreoleParser::linkCallback
     */
    public function testBareWikiLink()
    {
        $input = "Learn to [[install]] our software.";
        $expected = "<p>Learn to <a href=\"/wiki/install\">install</a> our software.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testWikiLinkWithText()
    {
        $input = "Learn to use our software by reading [[UserGuide|our User's Guide]].";
        $expected = "<p>Learn to use our software by reading <a href=\"/wiki/UserGuide\">our User&#039;s Guide</a>.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testWikiLinkWithTextFormatting()
    {
        $input = "**Our online [[documentation]] is not so great.**";
        $expected = "<p><strong>Our online <a href=\"/wiki/documentation\">documentation</a> is not so great.</strong></p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @todo Implement testWikiLinkThatDoesntExist().
     */
    public function testWikiLinkThatDoesntExist()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    public function testExternalLink()
    {
        $input = "Check out [[http://www.paulgarvin.net/php]] to learn about my other PHP projects.";
        $expected = "<p>Check out <a href=\"http://www.paulgarvin.net/php\" class=\"external\">http://www.paulgarvin.net/php</a> to learn about my other PHP projects.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testExternalLinkWithText()
    {
        $input = "Visit our [[http://www.github.com/wikicreoleparser/|Github repository]] where you can contribute to the project by forking it, improving it, and then making a pull request.";
        $expected = "<p>Visit our <a href=\"http://www.github.com/wikicreoleparser/\" class=\"external\">Github repository</a> where you can contribute to the project by forking it, improving it, and then making a pull request.</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testExternalLinkWithTextFormatting()
    {
        $input = "**The [[http://www.php.net/manual/en/|PHP online documentation]] is great.**";
        $expected = "<p><strong>The <a href=\"http://www.php.net/manual/en/\" class=\"external\">PHP online documentation</a> is great.</strong></p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::linkFreeUrls
     */
    public function testFreeStandingUrls()
    {
        $input = "I wonder who owns http://www.example.org?";
        $expected = "<p>I wonder who owns <a href=\"http://www.example.org\" class=\"external\">http://www.example.org</a>?</p>";
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    /**
     * @covers WikiCreoleParser::parseInline
     */
    public function testForceLinebreak()
    {
        $input = 'Elite Technology Services\\\\1234 Main Street\\\\Springfield, PA 19999\\\\123-555-1234';
        $expected = '<p>Elite Technology Services<br />1234 Main Street<br />Springfield, PA 19999<br />123-555-1234</p>';
        $output = $this->object->parse($input);
        $this->assertEquals($expected, $output);
    }

    public function testBlockMacro()
    {
        $input = "The Mazdaspeed3 is a fantastic car that is fast, corners well, has great utility due to the hatchback, and is just the right size.\n\n<<div .warning\nDisclaimer:\nI own a Mazdaspeed3 purchased at S-Plan pricing though my involvement in the Mazdaspeed Motorsports program.\nMazda has given me no other considerations or payment.\n>>\nThe best part of the car is it's low price compared to other cars in it's segment. You get a lot for the money.";
        $expected = "<p>The Mazdaspeed3 is a fantastic car that is fast, corners well, has great utility due to the hatchback, and is just the right size.</p>\n<div class=\"warning\">\nDisclaimer:\nI own a Mazdaspeed3 purchased at S-Plan pricing though my involvement in the Mazdaspeed Motorsports program.\nMazda has given me no other considerations or payment.\n</div>\n<p>The best part of the car is it&#039;s low price compared to other cars in it&#039;s segment. You get a lot for the money.</p>";
        $this->object->registerMacro('div', function($klass, $text) {
            return '<div class="' . substr($klass, 1) . "\">" . $text . "</div>";
        });

        $output = $this->object->parse($input);

        $this->assertEquals($expected, $output);
    }
}
