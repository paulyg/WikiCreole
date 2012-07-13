<?php
/**
 * Test script for WikiCreole using the test case at the WikiCreole website.
 *
 * This file runs the official tests from the wikicreole.org website, located
 * at http://www.wikicreole.org/attach/Creole1.0TestCases/creole1.0test.txt.
 * This test is kind of an all-or-nothing, pass-or-fail situation. For that
 * reason there is also more fine grained unit tests located in
 * WikiCreoleTest.php.
 *
 * @package WikiCreole
 * @author Paul Garvin <paul@paulgarvin.net>
 * @copyright Copyright 2011, 2012 Paul Garvin.
 * @license MIT License
 * @link https://github.com/paulyg/WikiCreole Project Homepage
 * @link http://www.wikicreole.org/ Wiki Creole Homepage 
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

include '../WikiCreole.php';

$parser = new WikiCreole(array(
    'urlBase' => 'http://www.example.org/wiki/',
    'imgBase' => 'http://www.example.org/images/'
));

$test_input = file_get_contents('./CreoleTestInput.txt');
$expected = file_get_contents('./CreoleTestExpected.html');

$output = $parser->parse($test_input);

file_put_contents('./FinalOutput.html', $output);
        
if ($output == $expected) {
    echo "All tests passed!!" . PHP_EOL;
} else {
    echo "Some tests failed. :(" . PHP_EOL;
}

