<?php
/**
 * Application Name: Super Preloading By Cron
 * Application URI: https://github.com/tokkonopapa/preload-by-cron
 * Description: A helper function to improve the cache hit ratio.
 * Version: 0.5.0
 * Author: tokkonopapa
 * Author URI: http://tokkono.cute.coocan.jp/blog/slow/
 * Author Email: tokkonopapa@gmail.com
 *
 * @example:
 *     preload.php?key=your-secret-key&requests=10&interval=100&debug=1
 *
 * @param $_GET['key']: A secret string to execute crawl.
 * @param $_GET['agent']: Additional user agent.
 * @param $_GET['requests']: A number of urls to be requested in parallel.
 * @param $_GET['interval']: Interval in milliseconds between parallel requests.
 * @param $_GET['timeout']: Time out in seconds for each request.
 * @param $_GET['limit']: Time out in seconds for whole preloading.
 * @param $_GET['delay']: Initial delay in seconds for waiting garbage collection.
 * @param $_GET['split']: A number of requests per preloading.
 * @param $_GET['debug']: Output log to a file.
 *
 * @global string $garbage_collector: url to kick off WP-Cron.
 * @global array $sitemap_urls: list of sitemap url.
 * @global array $additional_urls: list of additional url.
 * @global array $user_agent: user agent list when fetch urls.
 *
 * @see https://github.com/jmathai/php-multi-curl
 * @see https://github.com/petewarden/ParallelCurl
 *
 * License:
 *
 * Copyright 2013 tokkonopapa (tokkonopapa@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

/**
 * Check secret key
 *
 * @global $_GET['key']: a secret string to execute crawl.
 */
if ( ! isset( $_GET['key'] ) || $_GET['key'] != 'your-secret-key' ) {
	exit;
}

/**
 * Synchronize Garbage Collection
 *
 * Garbage collection will be scheduled with WP-Cron functions.
 * To synchronize preloading with garbage collection, you should simply 
 * fetch your WordPress top page, or use WP-Cron Control plugin.
 *
 * @global string $garbage_collector: url for WP-Cron.
 * @link http://wordpress.org/extend/plugins/wp-cron-control/
 * @example http://example.com/wp-cron.php?doing_wp_cron&secret_string
 */
$garbage_collector = 'http://example.com/wp-cron.php';

/**
 * XML Sitemap Setting
 *
 * This plugin crawls based on the URLs in `sitemap.xml`.
 * Such as main and category, you can specify multiple sitemaps.
 *
 * @global array $sitemap_urls: list of sitemap url.
 */
$sitemap_urls = array(
	'http://example.com/sitemap.xml',
);

/**
 * Additional URLs other than listed in XML Sitemap
 *
 * @global array $additional_urls: list of pages.
 */
$additional_urls = array(
	'http://example.com/sample-page/',
);

/**
 * User Agent of preloading crawler
 *
 * If you want to make cache for iPhone, simply add 'iPhone'.
 *
 * @global array $user_agent: list of user agent strings.
 */
$user_agent = array(
	'Super Preloading By Cron',
);

// Default settings
define( 'CURL_FETCH_REQUESTS',   10 ); // in number
define( 'CURL_FETCH_INTERVAL',  250 ); // in milliseconds
define( 'CURL_FETCH_TIMEOUT',    60 ); // in seconds
define( 'EXECUTION_TIME_LIMIT', 600 ); // in seconds
define( 'INITIAL_TIME_DELAY',    10 ); // in seconds
define( 'REQUESTS_PER_SPLIT',   100 ); // in number

// Options settings
$options = array(
	'agent'    => '',
	'requests' => CURL_FETCH_REQUESTS,
	'interval' => CURL_FETCH_INTERVAL,
	'timeout'  => CURL_FETCH_TIMEOUT,
	'limit'    => EXECUTION_TIME_LIMIT,
	'delay'    => INITIAL_TIME_DELAY,
	'split'    => REQUESTS_PER_SPLIT,
	'debug'    => FALSE,
);

// Parse queries
foreach ( $options as $key => $value ) {
	if ( isset( $_GET[ $key ] ) ) {
		if ( is_string( $options[ $key ] ) ) {
			$options[ $key ] = strip_tags( $_GET[ $key ] );
		} else if ( is_numeric( $_GET[ $key ] ) ) {
			$options[ $key ] = intval( $_GET[ $key ] );
		}
	}
}

/**
 * Output log to a file
 *
 * @param string $msg: message strings.
 */
function debug_log( $msg = NULL, $force = FALSE ) {
	if ( $options['debug'] || $force ) {
		$msg = trim( $msg );
		$file = basename( __FILE__, '.php' ) . '.log';
		$fp = @fopen( $file, is_null( $msg ) ? 'w' : 'a' );
		@fwrite( $fp, date( "Y/m/d,D,H:i:s" ) . " ${msg}\n" );
		@fclose( $fp );
	}
}

/**
 * Get contents of specified URL
 *
 * @param mixed $url: URL to be fetched.
 * @param int $timeout: Time out in seconds. If 0 then forever.
 * @return string: Strings of Contents.
 */
function url_get_contents( $url, $timeout = 0 ) {
/*	$ch = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_CONNECTTIMEOUT => $timeout,
	) );
	if ( ( $str = curl_exec( $ch ) ) === FALSE ) {
		debug_log( curl_error( $ch ), TRUE );
	}
	curl_close( $ch );
	return $str;*/
	return file_get_contents( $url );
}

/**
 * Simulate multiple threads request
 *
 * @param array $url_list: Array of URLs to be fetched.
 * @param int $timeout: Time out in seconds. If 0 then forever.
 * @param string $ua: `User-Agent:` header for request.
 * @return array of string: Array of contents.
 * @link http://techblog.ecstudio.jp/tech-tips/php-multi.html
 * @link http://techblog.yahoo.co.jp/architecture/api1_curl_multi/
 */
function fetch_multi_urls( $url_list, $timeout = 0, $ua = NULL ) {
	// Prepare multi handle
	$mh = curl_multi_init(); // PHP 5

	// List of cURLs hadles
	$ch_list = array();

	foreach ( $url_list as $i => $url ) {
		$ch_list[$i] = curl_init( $url ); // PHP 4 >= 4.0.2, PHP 5
		curl_setopt( $ch_list[$i], CURLOPT_RETURNTRANSFER, TRUE );
//		curl_setopt( $ch_list[$i], CURLOPT_FAILONERROR, TRUE );
		curl_setopt( $ch_list[$i], CURLOPT_FOLLOWLOCATION, TRUE );
		curl_setopt( $ch_list[$i], CURLOPT_MAXREDIRS, 3 );

		// No cookies
		curl_setopt( $ch_list[$i], CURLOPT_COOKIE, '' );

		// Ignore SSL Certification
		curl_setopt( $ch_list[$i], CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt( $ch_list[$i], CURLOPT_SSL_VERIFYHOST, FALSE );

		// Set timeout
		if ( $timeout )
			curl_setopt( $ch_list[$i], CURLOPT_TIMEOUT, $timeout );

		// Set User Agent
		if ( ! is_null( $ua ) )
			curl_setopt( $ch_list[$i], CURLOPT_USERAGENT, $ua );

		curl_multi_add_handle( $mh, $ch_list[$i] ); // PHP 5
	}

	// Run the sub-connections of the current cURL handle
	// @link http://www.php.net/manual/function.curl-multi-init.php
	// @link http://www.php.net/manual/function.curl-multi-exec.php
	$running = NULL;
	do {
		curl_multi_exec( $mh, $running ); // PHP 5
	} while ( $running );

	// Get status of each request
	$res = 0;
	foreach ( $url_list as $i => $url ) {
		// if CURLOPT_FAILONERROR is set to false, curl_error() will return empty.
		// So curl_getinfo() should be used to get HTTP status code.
		// if ( empty( ( $err = curl_error( $ch_list[$i] ) ) ) { // PHP 4 >= 4.0.3, PHP 5
		$err = intval( curl_getinfo( $ch_list[$i], CURLINFO_HTTP_CODE ) ); // PHP 4 >= 4.0.4, PHP 5
		if ( $err < 400 ) {
//			( $options['debug'] and debug_log( curl_multi_getcontent( $ch_list[$i] ) ) );
			( $options['debug'] and debug_log( $url ) );
			$res++;
		} else {
			( $options['debug'] and debug_log( "$err at $url" ) );
			throw new RuntimeException( "$err at $url" ); // PHP 5 >= 5.1.0
		}

		curl_multi_remove_handle( $mh, $ch_list[$i] ); // PHP 5
		curl_close( $ch_list[$i] ); // PHP 4 >= 4.0.2, PHP 5
	}

	// Close multi handle
	curl_multi_close( $mh ); // PHP 5

	return $res;
}

/**
 * Get urls from sitemap
 *
 * @param string $sitemap: url of sitemap.
 * @param int $timeout: time out in seconds.
 * @todo consider to use `simplexml_load_file()` and `simplexml_load_string()`.
 * @link http://www.php.net/manual/en/function.simplexml-load-file.php
 * @link http://php.net/manual/en/function.simplexml-load-string.php
 */
function get_urls_sitemap( $sitemap, $timeout = 0 ) {
	// Get contents of sitemap
	$xml = url_get_contents( $sitemap, $timeout );

	// Get URLs from sitemap
	// @todo consider sub sitemap.
	$urls = array();
	if ( preg_match_all( "/\<loc\>(.+?)\<\/loc\>/i", $xml, $matches ) !== FALSE ) {
		if ( is_array( $matches[1] ) && ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $url ) {
				$urls[] = trim( $url );
			}
		}
	}
	return $urls;
}

/**
 * Get options file name
 */
function get_option_filename() {
	return basename( __FILE__, '.php' ) . '_options.php';
}

/**
 * Get options from file
 */
function get_option( $file ) {
	$opts = array(
		'next_preload' => 0,
	);

	if ( file_exists( $file ) ) {
		require_once $file;
		$opts = $preload_options;
	}

	return $opts;
}

/**
 * Save options to file
 */
function update_option( $file, $updates ) {
	$fp = fopen( $file, "c+" );

	// lock exclusively
	// @link http://php.net/manual/function.flock.php
	if ( flock( $fp, LOCK_EX ) ) {
		$data = fread( $fp, length );
		ftruncate( $fp, 0 );

		fwrite( $fp, "<?php \$preload_options = array(\n" );
		foreach ( $updates as $key => $val ) {
			fwrite( $fp, "\t'$key' => $val,\n" );
		}
		fwrite( $fp, "); ?>" );

		fflush( $fp );
		flock( $fp, LOCK_UN ); // released lock
	}

	fclose( $fp );
}

/**
 * Get start number to split
 */
function get_split( $opt_requests, $total ) {
	$file = get_option_filename();
	$updates = get_option( $file );

	$start = intval( $updates['next_preload'] );
	$updates['next_preload'] = $start + intval( $opt_requests );

	if( $updates['next_preload'] >= $total )
		$updates['next_preload'] = 0;

	update_option( $file, $updates );

	return $start;
}

// Ignore client aborts and disallow the script to run forever
ignore_user_abort( TRUE );
set_time_limit( $options['limit'] );

// Initialize debug
// debug_log( NULL, TRUE );

// Call garbage collector
if ( ! empty( $garbage_collector ) ) {
	$msg = url_get_contents( $garbage_collector, $options['timeout'] );
	( $options['debug'] and debug_log( $msg ) ); // ex) <!-- 21 queries. 5.268 seconds. -->
}

// Wait to finish garbage collection
sleep( $options['delay'] );

// Start crawling
$time = microtime( TRUE );

// millisecond to microsecond
$options['interval'] *= 1000;

// Get URLs from sitemap
$urls = array();
foreach ( $sitemap_urls as $sitemap_url ) {
	$urls = array_merge(
		$urls,
		get_urls_sitemap( $sitemap_url, $options['timeout'] )
	);
}

// Additional URLs
if ( ! empty( $additional_urls ) )
	$urls = array_merge( $urls, $additional_urls );

// Remove duplicate URLs
$urls = array_unique( $urls );

// Split preloading
$urls = array_slice(
	$urls,
	intval( get_split( $options['split'], count( $urls ) ) ),
	intval( $options['split'] )
);

// Additional UA
if ( ! empty( $options['agent'] ) )
	$user_agent[] = $options['agent'];

// Fetch URLs
$n = 0;
foreach ( $user_agent as $ua ) {
	$pages = $urls;
	while ( count( $pages ) ) {
		try {
			$n += fetch_multi_urls(
				array_splice( $pages, 0, $options['requests'] ),
				$options['timeout'],
				$ua
			);
			( $options['debug'] and debug_log( $n ) );
		} catch ( Exception $e ) {
			debug_log( $e->getMessage(), TRUE );
		}
		usleep( $options['interval'] );
	}
}

// End crawling
// $time = microtime( TRUE ) - $time;
// debug_log( sprintf( "%3d pages / %2.3f [sec]", $n, $time ), TRUE );
