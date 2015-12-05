<?php

namespace CodeBird;

/**
 * Proxy to the Twitter API, adding CORS headers to replies.
 *
 * @package codebird
 * @version 1.5.0
 * @author Jublo Solutions <support@jublo.net>
 * @copyright 2013-2015 Jublo Solutions <support@jublo.net>
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

if (! function_exists('http_get_request_headers')) {
    function http_get_request_headers()
    {
        $arh = [];
        $rx_http = '/\AHTTP_/';
        foreach ($_SERVER as $key => $val) {
            if (preg_match($rx_http, $key)) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = [];
                // do some nasty string manipulations to restore the original letter case
                // this should work in most cases
                $rx_matches = explode('_', $arh_key);
                if (count($rx_matches) > 0 && strlen($arh_key) > 2) {
                    foreach ($rx_matches as $ak_key => $ak_val) {
                        $rx_matches[$ak_key] = ucfirst(strtolower($ak_val));
                    }
                    $arh_key = implode('-', $rx_matches);
                }
                $arh[$arh_key] = $val;
            }
        }
        return $arh;
    }
}

if (! function_exists('http_get_request_body')) {
    function http_get_request_body()
    {
        $body = '';
        $fh   = @fopen('php://input', 'r');
        if ($fh) {
            while (! feof($fh)) {
                $s = fread($fh, 1024);
                if (is_string($s)) {
                    $body .= $s;
                }
            }
            fclose($fh);
        }
        return $body;
    }
}

$constants = [
    'CURLE_SSL_CERTPROBLEM' => 58,
    'CURLE_SSL_CACERT' => 60,
    'CURLE_SSL_CACERT_BADFILE' => 77,
    'CURLE_SSL_CRL_BADFILE' => 82,
    'CURLE_SSL_ISSUER_ERROR' => 83
];
foreach ($constants as $id => $i) {
    defined($id) or define($id, $i);
}
unset($constants);
unset($i);
unset($id);


$url = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$cors_headers = [
    'Access-Control-Allow-Origin: *',
    'Access-Control-Allow-Headers: '
        . 'Origin, X-Authorization, Content-Type, Content-Range, '
        . 'X-TON-Expires, X-TON-Content-Type, X-TON-Content-Length',
    'Access-Control-Allow-Methods: POST, GET, OPTIONS',
    'Access-Control-Expose-Headers: '
        . 'X-Rate-Limit-Limit, X-Rate-Limit-Remaining, X-Rate-Limit-Reset'
];

foreach($cors_headers as $cors_header) {
    header($cors_header);
}

if ($method == 'OPTIONS') {
    die();
}

// get request headers
$headers_received = http_get_request_headers();
$headers = ['Expect:'];

// extract authorization header
if (isset($headers_received['X-Authorization'])) {
    $headers[] = 'Authorization: ' . $headers_received['X-Authorization'];
}

// get request body
$body = null;
if ($method === 'POST') {
    $body = http_get_request_body();

    // allow custom content types
    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers[] = 'Content-Type: '
            . str_replace(["\r", "\n"], [' ', ' '], $_SERVER['CONTENT_TYPE']);
    }

    // check for media parameter
    // for uploading multiple medias, use media_data, see
    // https://dev.twitter.com/docs/api/multiple-media-extended-entities

    if (isset($_POST['media']) && is_array($_POST['media'])) {
        $body = $_POST;

        // write media file to temp
        $media_file = tempnam(sys_get_temp_dir(), 'codebird-media-');
        $fp = fopen($media_file, 'w');
        fwrite($fp, base64_decode($_POST['media'][0]));
        fclose($fp);

        // add file to uploads
        unset($body['media']);
        $body['media[]'] = '@' . $media_file;
    }

    // check for other base64 parameters
    foreach ($_POST as $key => $value) {
        $possible_files = [
            // media[] is checked above
            'image',
            'banner'
        ];

        if (! in_array($key, $possible_files)) {
            continue;
        }

        // skip arrays
        if (! is_scalar($value)) {
            continue;
        }

        // check if valid base64
        if (base64_decode($mystring, true) === false) {
            continue;
        }

        $body[$key] = base64_decode($value);
    }

}

// URLs always start with 1.1, oauth or a separate API prefix
$api_host = 'ton.twitter.com';
$version_pos = strpos($url, '/ton/1.1/');
if ($version_pos !== false) {
    $version_pos += 4; // strip '/ton' prefix
}
if ($version_pos === false) {
    $version_pos = strpos($url, '/1.1/');
    $api_host = 'api.twitter.com';
}
if ($version_pos === false) {
    $version_pos = strpos($url, '/oauth/');
}
if ($version_pos === false) {
    $version_pos = strpos($url, '/oauth2/');
}
if ($version_pos === false) {
    $version_pos = strpos($url, '/ads/0/');
    $api_host = 'ads-api.twitter.com';
    if ($version_pos !== false) {
        $version_pos += 4; // strip '/ads' prefix
    }
}
if ($version_pos === false) {
    $version_pos = strpos($url, '/ads-sandbox/0/');
    $api_host = 'ads-api-sandbox.twitter.com';
    if ($version_pos !== false) {
        $version_pos += 12; // strip '/ads-sandbox' prefix
    }
}
if ($version_pos === false) {
    header('HTTP/1.1 412 Precondition failed');
    die(
        'This proxy only supports requests to REST API version 1.1, '
        . 'to the Twitter TON API and to the Twitter Ads API.'
    );
}
// use media endpoint if necessary
$is_media_upload = strpos($url, 'media/upload.json') !== false;
if ($is_media_upload) {
    $api_host = 'upload.twitter.com';
}
$url = 'https://' . $api_host . substr($url, $version_pos);

// send request to Twitter API
$ch = curl_init($url);

if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLINFO_HEADER_OUT, 1);

$reply = curl_exec($ch);

// delete media file, if any
if (isset($media_file) && file_exists($media_file)) {
    @unlink($media_file);
}

// certificate validation results
$validation_result = curl_errno($ch);
if (in_array(
        $validation_result,
        [
            CURLE_SSL_CERTPROBLEM,
            CURLE_SSL_CACERT,
            CURLE_SSL_CACERT_BADFILE,
            CURLE_SSL_CRL_BADFILE,
            CURLE_SSL_ISSUER_ERROR
        ]
    )
) {
    die('Error ' . $validation_result . ' while validating the Twitter API certificate.');
}

$httpstatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// split off headers
$reply = explode("\r\n\r\n", $reply, 2);
$reply_headers = explode("\r\n", $reply[0]);

foreach($reply_headers as $reply_header) {
    header($reply_header);
}
if (isset($reply[1])) {
    $reply = $reply[1];
}

// send back all data untouched
die($reply);

