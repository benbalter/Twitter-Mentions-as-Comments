# Twitter Mentions As Comments #

Contributors: benbalter  
Donate link: http://ben.balter.com/donate/?utm_source=wp&utm_medium=org_plugin_page&utm_campaign=tmac  
Tags: comments, twitter, mentions, social, social media  
Requires at least: 3.3  
Tested up to: 3.5  
Stable tag: 1.5.4
License: GPLv3 or Later

Twitter Mentions as Comments scours Twitter for people talking about your site & silently inserts their Tweets alongside your existing comments.

## Description ##

Twitter Mentions as Comments does exactly what it promises to do - scours Twitter for people talking about your blog posts and silently inserts their Tweets alongside your existing comments. The plugin leverages the power of WordPress's built-in commenting system - notification, comment moderation, author white/black listing - making Twitter an extension of your blog.

### Features ###
* Searches for Tweets linking to your blog posts, regardless of the URL shortener used (using Twitter's Search API)
* Pushes Tweets into WordPress's existing comment workflow - notifications, comment moderation, and author whitelists/blacklists work just like any other comment
* Fetches user's real name and profile picture and links directly to the original Tweet
* Checks automatically - no need to do a thing
* Option to automatically exclude ReTweets
* Option to store tweets as trackbacks/pingbacks
* Option to specify which posts to check (e.g., 10 most recent posts, all posts, front page only)
* Smart Caching of Tweets and user data - retrieves only what it needs to save on API calls and server load

### Planned Features ###
* Dynamic resizing of Twitter profile images to fit WordPress theme
* Prioritization of newer posts
* Oauth Authentication to raise API limit (currently unlimited Tweets, but limited to 150 *new* comment authors per hour)
* Smarter API throttling

You can see it in action on the [WP Resume Plugin page](http://ben.balter.com/2010/09/12/wordpress-resume-plugin/#comment-168).

**Questions? Comments? Feature Requests? Have a great idea?** Please see [where to get support or report an issue](https://github.com/benbalter/Twitter-Mentions-as-Comments/wiki/Where-to-get-Support-or-Report-an-Issue) and [how to contribute](https://github.com/benbalter/Twitter-Mentions-as-Comments/wiki/How-to-Contribute).

**Developers,** have a killer feature you'd love to see included? Feel free to [fork the project on GitHub](https://github.com/benbalter/Twitter-Mentions-as-Comments/) and submit your contributions via pull request.

*Enjoy using Twitter Mentions as Comments? Feel free to [make a small donation](http://ben.balter.com/donate/) to support the software's continued development.*

[Photo via [joshsemans](http://www.flickr.com/photos/joshsemans/3414271359/)]

## Installation ##

### Automatic Install ###
1. Login to your WordPress site as an Administrator, or if you haven't already, complete the famous [WordPress Five Minute Install](http://codex.wordpress.org/Installing_WordPress)
2. Navigate to Plugins->Add New from the menu on the left
3. Search for Twitter Mentions as Comments
4. Click "Install"
5. Click "Activate Now"

### Manual Install ###
1. Download the plugin from the link in the top left corner
2. Unzip the file, and upload the resulting "wp-document-revisions" folder to your "/wp-content/plugins directory" as "/wp-content/plugins/twitter-mentions-as-comments
3. Log into your WordPress install as an administrator, and navigate to the plugins screen from the left-hand menu
4. Activate Twitter Mentions as Comments

## Frequently Asked Questions ##

Please see (and feel free to contribute to) the [Frequently Asked Questions Wiki](https://github.com/benbalter/Twitter-Mentions-as-Comments/wiki/Frequently-Asked-Questions).

## Changelog ##

### 1.5.4 ###
* PHP 5.4 Compatibility, props @ceyson
* Updated external libraries (Plugin Boilerplate, TLC Transients)
* Minimum WordPress version supported bumped to 3.3 (2 legacy versions)

### 1.5.3 ###
* Plugin documentation now maintained in a [collaboratively edited wiki](https://github.com/benbalter/Twitter-Mentions-as-Comments/wiki/). Feel free to contribute!
* Created [listserv to provide a discussion forum](https://groups.google.com/forum/#!forum/Twitter-Mentions-as-Comments) for users and contributors, as well as general annoucements. Feel free to join!
* Fixed bug in deploy script which failed to included required library (TLC-Transients) resulting in fatal error upon upgrade or activation

### 1.5.2 ###
* Significant performance improvements to front end. Twitter avatars are now retrieved asynchronously (using TLC-Transients), and will update if the commenter's avatar changes
* Plugin no longer looks for short URLs (e.g., domain.com/?p=100) to prevent issue where Twitter API would not return results in some instances
* Fix for hourly cron not properly registering after upgrade or deactivation and preventing hourly checks from firing
* Fix for manual cron instructions appearing on options page when hourly cron was selected

### 1.5.1 ###
* Added Spanish translation support, special thanks to [Eduardo Larequi](http://www.labitacoradeltigre.com/).
* Better translation support.
* Fix for `invalid argument supplied foreach()` error on some installs.
* Fix for hourly cron not always firing on some installs.

### 1.5 ###
* Codebase completely rewritten with performance, stability, customizability, and documentation improvements
* Additional API hooks added for developers to further customize plugin's functionality
* Plugin now hosted [on GitHub](https://github.com/benbalter/Twitter-Mentions-as-Comments/) if developers would like to fork and contribute.
* Better internationalization support
* GPL license now distributed with plugin

### 1.0.4 ###
*** **NOTE:** you must manually reactivate the plugin after upgrading to this version**  
* Fix for bug where the name of authors with previous tweets would display the twitter username multiple times
* Changed name of primary plugin file to conform to traditional plugin format

### 1.0.3 ###
* Fix for including RTs option toggling opposite behavior of what should be expected (Special thanks [Joel Knight](http://www.packetmischief.ca/) for the patch)

### 1.0.2 ###
* Corrected bug that would prevent scheduled checks from properly firing.

### 1.0.1 ###
* Improved scheduling of automatic checks

### 1.0 ###
* Codebase completely re-written from the ground up
* Significant performance improvements by integrating with native WordPress caching class
* Extensive API hooks added for plugin developers to expand and customize functionality
* Debug information removed for performance considerations
* Added support for translations
* Added hideable donate button to the options page
* Moved javascript to a standalone file

### 0.4.3 ###
* Fixed bug where TMAC debug info would appear on ALL duplicate comment errors

### 0.4.2 ###
* Added support to check ?p=### formats for some shortened URLs

### 0.4.1 ###
* Fixed bug where update check would fail if plugin had been upgraded from pre-0.4 version

### 0.4 ###
* Fixed duplicate comment bug

### 0.3 ###
* Fixed bug where Tweet ID was not being properly stored
* Added ability to run check manually based on cron job
* Better API throttling with feedback when limit reached

### 0.2 ###
* Now relies solely on the public Twitter API, no API keys needed
* Ability to specify type of comment (Pingback/Trackback/Comment)
* Ability to specify which posts to check (e.g., 10 most recent posts, all posts, front page)
* Better caching of Twitter user's real names
* Better caching of last checked ID (when no mentions were found)
* More descriptive feedback on options page for manual refreshes (# of Mentions found)
* API call throttling
* Fallback capability to use only Twitter handle as name if Twitter API limit is reached

### 0.1 ###
* Initial release

## Donate ##

Enjoy using Twitter Mentions as Comments? Please consider [making a small donation](http://ben.balter.com/donate/?utm_source=wiki&utm_medium=donate&utm_campaign=tmac) to support the project's continued development.


## Frequently Asked Questions ##

Please see (and feel free to contribute to) the [Frequently Asked Questions Wiki](https://github.com/benbalter/Twitter-Mentions-as-Comments/wiki/Frequently-Asked-Questions).

## How To Contribute ##

Twitter Mentions as Comments is an open source project and is supported by the efforts of an entire community. We'd love for you to get involved. Whatever your level of skill or however much time you can give, your contribution is greatly appreciated.

* **Everyone** - help expand the projects [documentation wiki](https://github.com/benbalter/Twitter-Mentions-as-Comments/wiki) to make it easier for other users to get started
* **Users** - download the latest [development version](https://github.com/benbalter/Twitter-Mentions-as-Comments/tree/develop) of the plugin, and [submit bug/feature requests](https://github.com/benbalter/Twitter-Mentions-as-Comments/issues).
* **Non-English Speaking Users** - [Contribute a translation](http://translations.benbalter.com/projects/Twitter-Mentions-as-Comments/) using the GlotPress web interface - no technical knowledge required ([how to](http://translations.benbalter.com/projects/how-to-translate)).
* **Developers** - [Fork the development version](https://github.com/benbalter/Twitter-Mentions-as-Comments/tree/develop) and submit a pull request, especially for any [known issues](https://github.com/benbalter/Twitter-Mentions-as-Comments/issues?direction=desc&sort=created&state=open)

## Where To Get Support Or Report An Issue ##

*There are various resources available, depending on the type of help you're looking for:*

* For getting started and general documentation, please browse, and feel free to contribute to [the project wiki](https://github.com/benbalter/Twitter-Mentions-as-Comments/wiki).
 
* For support questions ("How do I", "I can't seem to", etc.) please search and if not already answered, open a thread in the [Support Forums](http://wordpress.org/support/plugin/Twitter-Mentions-as-Comments).

* For technical issues (e.g., to submit a bug or feature request) please search and if not already filed, [open an issue on GitHub](https://github.com/benbalter/Twitter-Mentions-as-Comments/issues).

* For implementation, and all general questions ("Is it possible to..", "Has anyone..."), please search, and if not already answered, post a topic to the [general discussion list serve](https://groups.google.com/forum/#!forum/Twitter-Mentions-as-Comments)