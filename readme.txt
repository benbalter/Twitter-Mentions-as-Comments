=== Plugin Name ===
Contributors: benbalter
Donate link: http://ben.balter.com/
Tags: comments, twitter
Requires at least: 3.0
Tested up to: 3.1
Stable tag: 0.1

Twitter Mentions as Comments scours Twitter for people talking about your blog posts & silently inserts their Tweets alongside your existing comments.

== Description ==

Twitter Mentions as Comments does exactly what it promises to do - scours Twitter for people talking about your blog posts and silently inserts their Tweets alongside your existing comments. The plugin leverages the power of WordPress's built-in commenting system - notification, comment moderation, author white/black listing - making Twitter an extension of your blog.

= Features = 
* Searches for Tweets linking to your blog posts, regardless of the URL shortener used (using the free BackTweets API)
* Pushes Tweets into WordPress's existing comment workflow - notifications, comment moderation, and author whitelists/blacklists work just like any other comment
* Fetches user's real name and profile picture from the Twitter API and links directly to the original Tweet
* Checks automatically - no need to do a thing
* Option to automatically exclude ReTweets
* Smart Caching of Tweets and user data - retrieves only what it needs to save on API calls and server load

= Planned Features = 
* Better error handling
* Better API call throttling and management
* Dynamic resizing of Twitter profile images to fit WordPress theme

You can see it in action on the [WP Resume Plugin page](http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/).

It should be noted that neither I nor the plugin are in any way affiliated with BackTweets. Also, **please note that this is an initial release** and is not intended for use in all environments. It works for me, but that's about all I can guarantee right now. Any feedback you can provide in the comments below to improve the plugin is greatly appreciated.

== Installation ==

1. Download and install the plugin
2. Activate
3. Navigate to the Twitter->Comments settings page and enter your BackTweets API Key (there's a link there to sign up if you need it!)

== Frequently Asked Questions ==

= Wouldn't it be great if..., I keep getting this error message, etc. =

It probably would be. Please let me know on the [Twitter Mentions as Comments Plugin Page](http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/). 

== Changelog ==

= 0.1 =
* Initial release