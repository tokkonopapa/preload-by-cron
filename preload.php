<?php
/**
 * Application Name: Super Preloading By Cron
 * Application URI: https://github.com/tokkonopapa/preload-by-cron
 * Description: A helper function to improve the cache hit ratio.
 * Version: 1.0.2
 * Author: tokkonopapa
 * Author URI: http://tokkono.cute.coocan.jp/blog/slow/
 * Author Email: tokkonopapa@gmail.com
 *
 * @example:
 *     wget -q "http://example.com/preload.php?key=your-secret-key&fetches=10&interval=100&debug=1"
 * or
 *     php -q preload.php --key "your-secret-key" --fetches 10 --interval 100 --debug 1
 *
 * @param $_GET['key']: A secret string to execute crawl.
 * @param $_GET['test']: Just test, do not update the next split.
 * @param $_GET['retry']: Retry to fetch timed out pages.
 * @param $_GET['debug']: A level to output to debug log file.
 * @param $_GET['agent']: Additional user agent strings.
 * @param $_GET['cache']: Cache duration in seconds.
 * @param $_GET['gc']: Interval of garbage collection in seconds.
 * @param $_GET['wait']: Wait in seconds for garbage collection.
 * @param $_GET['fetches']: A number of concurrent connections to fetch.
 * @param $_GET['timeout']: Timeout in seconds for each connection.
 * @param $_GET['interval']: Interval in milliseconds per connections.
 *
 * @global string 'your-secret-key': A secret key.
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
 * Get options from command line interface
 *
 * @global array $argv
 */
if ( isset( $argv ) ) {
	$_GET = array_merge( $_GET, getopt( '', array(
		'key:',
		'test:',
		'retry:',
		'debug:',
		'agent:',
		'cache:',
		'gc:',
		'wait:',
		'fetches:',
		'timeout:',
		'interval:',
	) ) ); // PHP 4 >= 4.3.0, PHP 5
}

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
 * fetch WordPress wp-cron.php, or use WP-Cron Control plugin.
 *
 * @global string $garbage_collector: url for WP-Cron.
 * @link http://wordpress.org/extend/plugins/wp-cron-control/
 * @example http://example.com/wp-cron.php?doing_wp_cron&your-secret-key
 */
$garbage_collector = 'http://example.com/wp-cron.php?doing_wp_cron';

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
	'Mozilla/5.0 (Super Preloading By Cron)',
);

// Default settings
define( 'CACHE_DURATION',     3600 ); // in seconds
define( 'GARBAGE_COLLECTION',  600 ); // in seconds
define( 'WAIT_FOR_GC_WPCRON',   10 ); // in seconds
define( 'CONCURRENT_FETCHES',    5 ); // in number
define( 'TIMEOUT_OF_FETCHE',    10 ); // in seconds
define( 'INTERVAL_OF_FETCHES', 500 ); // in milliseconds

// Level of debug log
define( 'DEBUG_NON', 0 );
define( 'DEBUG_ERR', 1 );
define( 'DEBUG_LOG', 2 );
define( 'DEBUG_LEN', 6 * 24 ); // Ring buffer length

// Options settings
$options = array(
	'test'     => FALSE,
	'retry'    => FALSE,
	'debug'    => DEBUG_NON,
	'agent'    => array(),
	'cache'    => CACHE_DURATION,
	'gc'       => GARBAGE_COLLECTION,
	'wait'     => WAIT_FOR_GC_WPCRON,
	'fetches'  => CONCURRENT_FETCHES,
	'timeout'  => TIMEOUT_OF_FETCHE,
	'interval' => INTERVAL_OF_FETCHES,
);

// Parse and check queries
foreach ( $options as $key => $value ) {
	if ( isset( $_GET[ $key ] ) ) {
		if ( is_string( $options[ $key ] ) ) {
			$options[ $key ] = strip_tags( $_GET[ $key ] );
		} else if ( is_numeric( $_GET[ $key ] ) && 0 < (int)$_GET[ $key ] ) {
			$options[ $key ] = intval( $_GET[ $key ] );
		}
	}
}

/**
 * Output log to a file
 *
 * @param string $msg: message strings.
 */
function debug_log( $msg, $level = DEBUG_LOG ) {
	global $options;
	if ( $options['debug'] >= $level ) {
		$msg = date( "Y/m/d,D,H:i:s " ) . trim( $msg );
		$fp = @fopen($file = basename( __FILE__, '.php' ) . '.log', 'c+' ); // PHP 5 >= 5.2.6
		$stat = @fstat( $fp );
		$buff = explode( "\n", @fread( $fp, $stat['size'] ) . $msg );
		$buff = array_slice( $buff, -DEBUG_LEN );
		@rewind( $fp );
		@fwrite( $fp, implode( "\n", $buff ) . "\n" );
		@fflush( $fp );
		@ftruncate( $fp, ftell( $fp ) );
		@fclose( $fp );
	}
}

/**
 * Simulate multiple threads request
 *
 * @param array $url_list: Array of URLs to be fetched.
 * @param int $timeout: Time out in seconds. If 0 then forever.
 * @param string $ua: `User-Agent:` header for request.
 * @param array &$fail: Unfetched urls because of error.
 * @return int: A Number of urls which have been fetched successfully.
 * @link http://techblog.ecstudio.jp/tech-tips/php-multi.html
 * @link http://techblog.yahoo.co.jp/architecture/api1_curl_multi/
 */
function fetch_multi_urls( $url_list, $timeout = TIMEOUT_OF_FETCHE, $ua = NULL, $fail = array() ) {
	// Prepare multi handle
	$mh = curl_multi_init(); // PHP 5

	// List of cURLs hadles
	$ch_list = array();

	foreach ( $url_list as $i => $url ) {
		$ch_list[$i] = curl_init( $url ); // PHP 4 >= 4.0.2, PHP 5
		curl_setopt( $ch_list[$i], CURLOPT_FAILONERROR, TRUE );
		curl_setopt( $ch_list[$i], CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ch_list[$i], CURLOPT_FOLLOWLOCATION, TRUE );
		curl_setopt( $ch_list[$i], CURLOPT_MAXREDIRS, 5 );
		curl_setopt( $ch_list[$i], CURLOPT_HEADER, FALSE );

		// No cookies
		curl_setopt( $ch_list[$i], CURLOPT_COOKIE, '' );

		// Ignore SSL Certification
		curl_setopt( $ch_list[$i], CURLOPT_SSL_VERIFYPEER, FALSE );

		// Set timeout ('0' means indefinitely)
		curl_setopt( $ch_list[$i], CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $ch_list[$i], CURLOPT_CONNECTTIMEOUT, $timeout );

		// Set User Agent
		if ( ! is_null( $ua ) )
			curl_setopt( $ch_list[$i], CURLOPT_USERAGENT, $ua );

		curl_multi_add_handle( $mh, $ch_list[$i] ); // PHP 5
	}

	// Run the sub-connections of the current cURL handle
	// @link http://www.php.net/manual/function.curl-multi-init.php
	// @link http://www.php.net/manual/function.curl-multi-exec.php
	$active = NULL;
	do {
		$res = curl_multi_exec( $mh, $active ); // PHP 5
	} while ( CURLM_CALL_MULTI_PERFORM === $res );

	while ( $active && CURLM_OK === $res ) {
		if ( curl_multi_select( $mh ) !== -1 ) { // PHP 5
			do {
				$res = curl_multi_exec( $mh, $active );
			} while ( CURLM_CALL_MULTI_PERFORM === $res );
		}
	}

	// Get status of each request
	$res = 0;
	foreach ( $url_list as $i => $url ) {
		// CURLOPT_FAILONERROR should be true
		$err = curl_error( $ch_list[$i] ); // PHP 4 >= 4.0.3, PHP 5
		if ( empty( $err ) ) {
			$res++;
			debug_log( $url );
//			debug_log( curl_multi_getcontent( $ch_list[$i] ) );
		} else {
			$fail[] = $url;
			debug_log( "$err at $url", DEBUG_ERR );
		}

		curl_multi_remove_handle( $mh, $ch_list[$i] ); // PHP 5
		curl_close( $ch_list[$i] ); // PHP 4 >= 4.0.2, PHP 5
	}

	// Close multi handle
	curl_multi_close( $mh ); // PHP 5

	return $res;
}

/**
 * Get contents of specified URL
 *
 * @param mixed $url: URL to be fetched.
 * @param int $timeout: Time out in seconds. If 0 then forever.
 * @return string: Strings of Contents.
 */
function url_get_contents( $url, $timeout = TIMEOUT_OF_FETCHE ) {
	$ch = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_FAILONERROR    => TRUE,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_FOLLOWLOCATION => TRUE,
		CURLOPT_MAXREDIRS      => 5,
		CURLOPT_CONNECTTIMEOUT => $timeout,
		CURLOPT_TIMEOUT        => $timeout,
		CURLOPT_HEADER         => FALSE,
	) );

	if ( FALSE === ( $str = curl_exec( $ch ) ) )
		debug_log( curl_error( $ch ) . " at $url", DEBUG_ERR );

	curl_close( $ch );
	return $str;
//	return file_get_contents( $url );
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
function get_urls_sitemap( $sitemap, $timeout = TIMEOUT_OF_FETCHE ) {
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
	$fp = fopen( $file, "c" ); // $fp is on the beginning of the file. PHP 5 >= 5.2.6

	// lock exclusively
	// @link http://php.net/manual/function.flock.php
	if ( flock( $fp, LOCK_EX ) ) {
		fwrite( $fp, "<?php \$preload_options = array(\n" );
		foreach ( $updates as $key => $val ) {
			fwrite( $fp, "\t'$key' => $val,\n" );
		}
		fwrite( $fp, "); ?>" );
		fflush( $fp );
		ftruncate( $fp, ftell( $fp ) );
		flock( $fp, LOCK_UN ); // released lock
	}

	fclose( $fp );
}

/**
 * Get start number of split preloading
 */
function get_split( $requests, $total ) {
	$file = get_option_filename();
	$updates = get_option( $file );

	$start = intval( $updates['next_preload'] );
	$updates['next_preload'] = $start + $requests;

	if( $updates['next_preload'] >= $total )
		$updates['next_preload'] = 0;

	global $options;
	if ( ! $options['test'] && $total > 0 )
		update_option( $file, $updates );

	return $start;
}

// Ignore client aborts and disallow the script to run forever
ignore_user_abort( TRUE );
set_time_limit( $options['gc'] );

// Call cron job to kick garbage collector
if ( ! empty( $garbage_collector ) ) {
	$msg = url_get_contents( $garbage_collector, $options['timeout'] );

	// Retry once
	if ( FALSE === $msg && $options['retry'] )
		$msg = url_get_contents( $garbage_collector, $options['timeout'] );

	debug_log( $msg );

	// Wait for garbage collection
	sleep( $options['wait'] );
}

// Start crawling
$time = microtime( TRUE );

// Get URLs from sitemap
$urls = array();
foreach ( $sitemap_urls as $url ) {
	$urls = array_merge(
		$urls,
		get_urls_sitemap( $url, $options['timeout'] )
	);
}

// Additional URLs
if ( ! empty( $additional_urls ) )
	$urls = array_merge( $urls, $additional_urls );

// Remove duplicate URLs
$urls = array_unique( $urls );

// Split preloading
$t = count( $urls );
$n = ceil( $t * $options['gc'] / $options['cache'] );
$urls = array_slice( $urls, get_split( $n, $t ), $n );

// Additional UA
if ( ! empty( $options['agent'] ) )
	$user_agent = array_merge( $user_agent, (array)$options['agent'] );

// millisecond to microsecond
$options['interval'] *= 1000;

// Fetch URLs
$n = 0;
foreach ( $user_agent as $ua ) {
	$t = $treq = 0;
	$pages = $urls;

	while ( count( $pages ) ) {
		// Fetch pages
		$retry = array();
		$t = microtime( TRUE );
		$n += fetch_multi_urls(
			array_splice( $pages, 0, $options['fetches'] ),
			$options['timeout'],
			$ua,
			&$retry
		);

		// Retry
		if ( $options['retry'] && ! empty( $retry ) ) {
			// array_unshift( $pages, $retry ); possibly infinit loop
			$n += fetch_multi_urls( $retry, $options['timeout'], $ua );
		}

		// Take a break
		usleep( $options['interval'] );
		$treq += microtime( TRUE ) - $t;
	}
}

// End of crawling
if ( $options['debug'] ) {
	$time = microtime( TRUE ) - $time;
	$treq = $treq ? $n / $treq : 0;
	debug_log( sprintf( "%3d pages | %5.2f sec | %5.2f req/sec", $n, $time, $treq ), DEBUG_LOG );
}
