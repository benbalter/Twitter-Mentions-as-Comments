=== Twitter Mentions as Comments ===
Contributors: benbalter
Donate link: http://ben.balter.com/donate/?utm_source=wp&utm_medium=org_plugin_page&utm_campaign=tmac
Tags: comments, twitter, mentions, social, social media
Requires at least: 3.0
Tested up to: 3.2
Stable tag: 1.5

Twitter Mentions as Comments scours Twitter for people talking about your site & silently inserts their Tweets alongside your existing comments.

== Description ==

Twitter Mentions as Comments does exactly what it promises to do - scours Twitter for people talking about your blog posts and silently inserts their Tweets alongside your existing comments. The plugin leverages the power of WordPress's built-in commenting system - notification, comment moderation, author white/black listing - making Twitter an extension of your blog.

= Features = 
* Searches for Tweets linking to your blog posts, regardless of the URL shortener used (using Twitter's Search API)
* Pushes Tweets into WordPress's existing comment workflow - notifications, comment moderation, and author whitelists/blacklists work just like any other comment
* Fetches user's real name and profile picture and links directly to the original Tweet
* Checks automatically - no need to do a thing
* Option to automatically exclude ReTweets
* Option to store tweets as trackbacks/pingbacks
* Option to specify which posts to check (e.g., 10 most recent posts, all posts, front page only)
* Smart Caching of Tweets and user data - retrieves only what it needs to save on API calls and server load

= Planned Features = 
* Dynamic resizing of Twitter profile images to fit WordPress theme
* Prioritization of newer posts
* Oauth Authentication to raise API limit (currently unlimited Tweets, but limited to 150 *new* comment authors per hour)
* Smarter API throttling

You can see it in action on the [WP Resume Plugin page](http://ben.balter.com/2010/09/12/wordpress-resume-plugin/#comment-168).

**Questions? Comments? Feature Requests?** Any feedback you can provide in the comments section of the [Twitter Mentions as Comments Plugin Page](http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/) to improve the plugin is greatly appreciated.

**Developers,** have a killer feature you'd love to see included? Feel free to [fork the project on GitHub](https://github.com/benbalter/Twitter-Mentions-as-Comments/) and submit your contributions via pull request.

*Enjoy using Twitter Mentions as Comments? Feel free to [make a small donation](http://ben.balter.com/donate/) to support the software's continued development.*

[Photo via [joshsemans](http://www.flickr.com/photos/joshsemans/3414271359/)]

== Installation ==

1. Download and install the plugin
2. Activate

== Frequently Asked Questions ==

= Wouldn't it be great if..., I keep getting this error message, etc. =

It probably would be. Please let me know on the [Twitter Mentions as Comments Plugin Page](http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/).
 
= It keeps finding my own Tweets. Is there an easy way to blacklist a Twitter user? =

Yes. Because Tweets go through WordPress's built-in comment moderation system, if you navigate to Settings -> Discussions and add "[your Twitter username]@twitter.com" to the blacklist, your Tweets should not appear (or anyone else's you want for that matter).

== Changelog ==

= 1.5.1 =
* Added Spanish translation support, special thanks to [Eduardo Larequi](http://www.labitacoradeltigre.com/).
* Better translation support.

= 1.5 =
* Codebase completely rewritten with performance, stability, customizability, and documentation improvements
* Additional API hooks added for developers to further customize plugin's functionality
* Plugin now hosted [on GitHub](https://github.com/benbalter/Twitter-Mentions-as-Comments/) if developers would like to fork and contribute.
* Better internationalization support
* GPL license now distributed with plugin

= 1.0.4 = 
* **NOTE: you must manually reactivate the plugin after upgrading to this version**
* Fix for bug where the name of authors with previous tweets would display the twitter username multiple times
* Changed name of primary plugin file to conform to traditional plugin format

= 1.0.3 =
* Fix for including RTs option toggling opposite behavior of what should be expected (Special thanks [Joel Knight](http://www.packetmischief.ca/) for the patch)

= 1.0.2 = 
* Corrected bug that would prevent scheduled checks from properly firing.

= 1.0.1 =
* Improved scheduling of automatic checks

= 1.0 =
* Codebase completely re-written from the ground up
* Significant performance improvements by integrating with native WordPress caching class
* Extensive API hooks added for plugin developers to expand and customize functionality
* Debug information removed for performance considerations
* Added support for translations
* Added hideable donate button to the options page
* Moved javascript to a standalone file

= 0.4.3 =
* Fixed bug where TMAC debug info would appear on ALL duplicate comment errors

= 0.4.2 =
* Added support to check ?p=### formats for some shortened URLs

= 0.4.1 =
* Fixed bug where update check would fail if plugin had been upgraded from pre-0.4 version

= 0.4 =
* Fixed duplicate comment bug

= 0.3 =
* Fixed bug where Tweet ID was not being properly stored
* Added ability to run check manually based on cron job
* Better API throttling with feedback when limit reached

= 0.2 =
* Now relies solely on the public Twitter API, no API keys needed
* Ability to specify type of comment (Pingback/Trackback/Comment)
* Ability to specify which posts to check (e.g., 10 most recent posts, all posts, front page)
* Better caching of Twitter user's real names
* Better caching of last checked ID (when no mentions were found)
* More descriptive feedback on options page for manual refreshes (# of Mentions found)
* API call throttling
* Fallback capability to use only Twitter handle as name if Twitter API limit is reached

= 0.1 =
* Initial release