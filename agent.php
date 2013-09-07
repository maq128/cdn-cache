<?php
$GLOBALS['cacheDir'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . '.cache';

// 从浏览器转发过来的 proxy 请求，$_SERVER['REQUEST_URI'] 是原始的目标网址
$url = $_SERVER['REQUEST_URI'];

$req_headers = getAllHeaders();
list( $resp_headers, $resp_body ) = loadFromCache( $url );
if ( empty($resp_body) ) {
	list( $resp_headers, $resp_body ) = doAgent( str_replace( '-min.js', '.js', $url ), $req_headers );
	if ( empty($resp_body) ) {
	    list( $resp_headers, $resp_body ) = doAgent( $url, $req_headers );
	}
	if ( ! empty($resp_body) ) {
		saveToCache( $url, $resp_headers, $resp_body );
	}
}

foreach ( $resp_headers as $resp_header ) {
	$tokens = explode(':', $resp_header);
	$whitelist = array('content-type', 'last-modified');
	if ( in_array( strtolower($tokens[0]), $whitelist ) ) {
		header( $resp_header );
	}
}
echo $resp_body;

function convUrlToFilename( $url )
{
	$fnHead = $GLOBALS['cacheDir'] . DIRECTORY_SEPARATOR . md5($url);
	$fnBody = $fnHead . '.';

	preg_match( '/^.*(\\.\\w{1,4}).*$/', $url, $matches );
	if ( !empty($matches[1]) ) {
		$fnBody = $fnHead . $matches[1];
	}
	return array( $fnHead, $fnBody );
}

function loadFromCache( $url )
{
	list( $fnHead, $fnBody ) = convUrlToFilename( $url );

	if ( file_exists( $fnHead ) && file_exists( $fnBody ) ) {
		$resp_headers = explode( "\r\n", file_get_contents( $fnHead ) );
		array_shift( $resp_headers ); // $url
		array_shift( $resp_headers ); // $fnBody
		$resp_body = file_get_contents( $fnBody );
		return array($resp_headers, $resp_body);
	}
	return array(null,null);
}

function saveToCache( $url, $resp_headers, $resp )
{
	if ( ! is_dir($GLOBALS['cacheDir']) ) {
		mkdir( $GLOBALS['cacheDir'] );
	}
	list( $fnHead, $fnBody ) = convUrlToFilename( $url );
	$out = array_merge( array( $url, $fnBody ), $resp_headers );
	file_put_contents( $fnHead, implode("\r\n", $out) );
	file_put_contents( $fnBody, $resp );
}

function doAgent( $url, $headers )
{
	$crack = parse_url($url);
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
	curl_setopt( $ch, CURLOPT_FAILONERROR, false );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_HEADER, 1 );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
	$response = curl_exec( $ch );
	$contentType = 'text/plain';
	if ( curl_errno($ch) ) {
		$resp_body = '';
	} else {
		$httpStatusCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		if ( $httpStatusCode == 200 ) {
			$contentType = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
			$resp_header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
			$resp_headers = substr( $response, 0, $resp_header_size );
			$resp_body = substr( $response, $resp_header_size );

			$tmp_headers = explode("\r\n", $resp_headers);
			array_shift($tmp_headers);
			$resp_headers = array();
			foreach ( $tmp_headers as $header ) {
				if ( ! empty($header) ) {
					$resp_headers[] = $header;
				}
			}
		} else {
			$resp_body = '';
		}
	}
	curl_close( $ch );

	return array( $resp_headers, $resp_body );
}
