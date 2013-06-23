Preload by Cron
===============

### Description:
It crawls your WordPress pages to make pages being cached in the fresh 
based on sitemap.xml (ex: [Google XML Sitemaps][GXS]).

If you are using some of WordPress caching plugins, this will always keep 
every page being cached and improve the cache hit ratio. Then your visitors 
always feel your site so fast.

### Feature:
This plugin uses `curl_multi` to crawl pages in parallel, so it will generate 
fresh pages in a short period of time.

Additionally, this plugin has ability to synchronize with garbage collection of 
some cache plugin. It means that expiration time of each page are aligned and 
every page is almost always cached.

Split preloading is supported to reduce the load of your server.

### Usage:
This **is not** a WordPress plugin.
Call preload.php directly from your server's cron.

	wget -q "http://example.com/preload.php?key=your-secret-key&requests=10&interval=100&debug=1"

where:

* 'key': A secret string to execute crawl.
* 'ping': Send ping before fetching.
* 'test': Just test, do not update the next split.
* 'debug': A level to output to debug log file.
* 'agent': Additional user agent strings.
* 'limit': Maximum execution time in seconds.
* 'delay': Initial delay in seconds to wait garbage collection.
* 'split': A number of requests per split preloading.
* 'fetches': A number of urls to be fetched in parallel.
* 'timeout': Timeout in seconds for each fetch.
* 'interval': Interval in milliseconds between parallel fetches.

### Configuration:
* `string your-secret-key`: A secret key.
* `string $garbage_collector`: url to kick off WP-Cron.
* `array $sitemap_urls`: list of sitemap url.
* `array $additional_urls`: list of additional url.
* `array $user_agent`: user agent list when fetch urls.

[secret key generator - WordPress API][SKG] is useful for generating `your-secret-key`.

### Synchronization with cache garbage collection:
If you set `$garbage_collector` to URL specified by [WP-Cron Control][WCC], 
you can synchronize with cache garbage collection. 
This feature is highly recommended.

#### Settings:
If you want to set the period of garbage collection to `X` seconds, 
and this plugin needs `Y` seconds to crawl whole of your site, 
then you should reset the period of garbage collection to `(X - Y)` seconds.

### Todo:
- [x] handle errors and exceptions.
- [x] additional crawl with smart phone UA.
- [x] loosely synchronize with cache garbage collection via WP-Cron Control.

### Similar plugins:
- [AskApache Crazy Cache][ACC]
- [Warm Cache][WMC]
- [Generate Cache][GEN]

### License:
Licensed under the [GPL v2][GPL] or later.

[GXS]: http://wordpress.org/extend/plugins/google-sitemap-generator/
[SKG]: https://api.wordpress.org/secret-key/1.1/
[WCC]: http://wordpress.org/extend/plugins/wp-cron-control/
[ACC]: http://wordpress.org/extend/plugins/askapache-crazy-cache/
[WMC]: http://wordpress.org/extend/plugins/warm-cache/
[GEN]: http://wordpress.org/extend/plugins/generate-cache/
