Preload by Cron
===============

### Description:
It crawls your WordPress pages to make pages being cached in the fresh 
based on sitemap.xml (ex: [Google XML Sitemaps][GXS]).

If you are using some of WordPress caching plugins, this will always keep 
every page being cached and improve the cache hit ratio. Then your visitors 
always feel your site so fast.

### Feature:
This program uses `curl_multi` to crawl pages in parallel. Then it will 
generate fresh pages in a short period of time.

Additionally, this program has ability to synchronize with garbage collection 
of some cache plugin. It means that expiration time of each page are aligned 
and every page is almost always cached.

Split preloading is supported to reduce the load of your server.

### Usage:
This **is not** a WordPress plugin.
Call preload.php directly from your server's cron.

	wget -q "http://example.com/preload.php?key=your-secret-key&requests=10&interval=100&debug=1"

or

	php preload.php --key "your-secret-key" --fetches 10 --interval 100 --debug 1

where:

* `key`: A secret string to execute crawl.
* `ping`: Send ping before fetching.
* `test`: Just test, do not update the next split.
* `debug`: A level to output to debug log file.
* `agent`: Additional user agent strings.
* `cache`: Cache duration in seconds. (3600 sec)
* `gc`: Interval of garbage collection in seconds. (600 sec)
* `wait`: Wait in seconds for garbage collection. (10 sec)
* `fetches`: A number of urls to be fetched in parallel. (10)
* `timeout`: Timeout in seconds for each fetch. (15 sec)
* `interval`: Interval in milliseconds between parallel fetches. (250 msec)

### Configuration:
* string `your-secret-key`: A secret key.
* string `$garbage_collector`: url to kick off WP-Cron.
* array `$sitemap_urls`: list of sitemap url.
* array `$additional_urls`: list of additional url.
* array `$user_agent`: user agent list when fetch urls.

[secret key generator - WordPress API][SKG] is useful for generating `your-secret-key`.

### Synchronization with cache garbage collection:
If you set `$garbage_collector` to URL specified by [WP-Cron Control][WCC], 
you can synchronize with cache garbage collection. 
This feature is highly recommended.

#### Fine settings of your plugin:
Crawling pages at each preloading needs a certain period of time.
Let's say it as `D` seconds. If you set the cache duration to `X` seconds, 
and period of garbage collection to `Y` seconds, you should set those values 
such as `X - D`, `Y - D` seconds in order to synchronize with this program.

### Todo:
- [x] handle errors and exceptions.
- [x] additional crawl with smart phone UA.
- [x] loosely synchronize with cache garbage collection via WP-Cron Control.
- [x] make a ring buffer for debug log. (v0.9)
- [x] add options parser for command line interface. (v0.9)

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
[GPL]: http://www.gnu.org/licenses/gpl-2.0.html
