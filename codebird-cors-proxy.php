<?php

namespace CodeBird;

/**
 * Proxy to the Twitter API, adding CORS headers to replies.
 *
 * @package codebird
 * @version 1.1.0-dev
 * @author J.M. <me@mynetx.net>
 * @copyright 2013 J.M. <me@mynetx.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

$url = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$cors_headers = array(
    'Access-Control-Allow-Origin: *',
    'Access-Control-Allow-Headers: Origin, X-Authorization',
    'Access-Control-Allow-Methods: POST, GET, OPTIONS'
);

foreach($cors_headers as $cors_header) {
    header($cors_header);
}

if ($method == 'OPTIONS') {
    die();
}

// get request headers
$headers_received = http_get_request_headers();
$headers = array('Expect:');

// extract authorization header
if (isset($headers_received['X-Authorization'])) {
    $headers[] = 'Authorization: ' . $headers_received['X-Authorization'];
}

// get request body
$body = null;
if ($method === 'POST') {
    $body = http_get_request_body();
}

// cut off first subfolder
$url = explode('/', $url);
array_shift($url);
array_shift($url);
$url = implode('/', $url);
$url = 'https://api.twitter.com/' . $url;

// send request to Twitter API
$ch = curl_init($url);

if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLINFO_HEADER_OUT, 1);

$reply = curl_exec($ch);
$httpstatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// split off headers
$reply = explode("\r\n\r\n", $reply, 2);
$reply_headers = explode("\r\n", $reply[0]);

foreach($reply_headers as $reply_header) {
    header($reply_header);
}
$reply = $reply[1];

// send back all data untouched
die($reply);

