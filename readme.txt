=== Plugin Name ===
Contributors: benbalter
Donate link: http://ben.balter.com/
Tags: comments, twitter
Requires at least: 3.0
Tested up to: 3.2
Stable tag: 0.4.2

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

== Installation ==

1. Download and install the plugin
2. Activate

== Frequently Asked Questions ==

= Wouldn't it be great if..., I keep getting this error message, etc. =

It probably would be. Please let me know on the [Twitter Mentions as Comments Plugin Page](http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/).
 
= It keeps finding my own Tweets. Is there an easy way to blacklist a Twitter user? =

Yes. Because Tweets go through WordPress’s built-in comment moderation system, if you navigate to Settings -> Discussions and add “[your Twitter username]@twitter.com” to the blacklist, your Tweets should not appear (or anyone else’s you want for that matter).

== Changelog ==

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