<?php
/**
 * Test script for WikiCreole using the test case at the WikiCreole website.
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

define('INPUT_DIR', __DIR__ . DIRECTORY_SEPARATOR);
define('OUTPUT_DIR', INPUT_DIR);

include INPUT_DIR . 'WikiCreole.php';

$parser = new WikiCreole('http://www.example.org/wiki/', 'http://www.example.org/images/');

$test_input = file_get_contents(INPUT_DIR . 'CreoleTestInput.txt');
$expected = file_get_contents(INPUT_DIR . 'CreoleTestExpected.html');

$output = $parser->parse($test_input);

file_put_contents(OUTPUT_DIR . 'FinalOutput.html', $output);
        
if ($output == $expected) {
    echo "All tests passed!!" . PHP_EOL;
} else {
    echo "Some tests failed. :(" . PHP_EOL;
}

