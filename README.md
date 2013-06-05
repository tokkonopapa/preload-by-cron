Preload by Cron
===============

### Description:
It crawls your WordPress pages to make pages being cached based on sitemap.xml 
(ex: [Google XML Sitemaps][GXS]).

If you are using some of WordPress caching plugins, this will always keep 
every page being cached and improve the cache hit ratio. Then your visitors 
always feel your site so fast.

### Feature:
This plugin uses `curl_multi` to crawl pages in parallel, so it will generate 
fresh pages in a short period of time.

Additionally, this plugin has ability to synchronize with garbage collection of 
some cache plugin. It means that expiration time of each page are aligned and 
every page is almost always cached.

### Usage:
This **is not** a WordPress plugin.
Call preload.php directly from your server's cron.

	preload.php?key=your-secret-key&requests=10&interval=100&debug=1

where:

* `key`: A secret string to execute crawling.
* `requests`: A number of urls to fetch at a time.
* `interval`: Time interval in millisecond between each fetch.
* `timeout`: A timeout in seconds for each fetch.
* `limit`: Maximum execution time in seconds.
* `delay`: Initial delay to wait garbage collection.
* `split`: A number of requests per preloading.
* `debug`: Output to log file.

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

### License:
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA

[GXS]: http://wordpress.org/extend/plugins/google-sitemap-generator/
[SKG]: https://api.wordpress.org/secret-key/1.1/
[WCC]: http://wordpress.org/extend/plugins/wp-cron-control/
[ACC]: http://wordpress.org/extend/plugins/askapache-crazy-cache/
[WMC]: http://wordpress.org/extend/plugins/warm-cache/
