=== pqInternet's NextGEN Database Analysis and Clean-up Tool ===
Contributors: FWBlack
Donate link: https://www.pqinternet.com/donate/
Tags: nextgen, ngg
Requires at least: 4.6
Tested up to: 4.9.4
Stable tag: 4.6
Requires PHP: 5.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

NextGEN Gallery can leave orphaned records in the database.  This plugin checks for orphaned records and give you the option to delete them.

== Description ==

NextGEN Gallery is one of the best, if not THE best image gallery plugins for WordPress.  I've used it for years on many sites.  However I've noticed a problem, a bug if you will, that leaves "orphaned" records in the WordPress database.  I've reported this to Imagely and they're looking into it.

Using NextGEN Gallery can leave orphaned records in the posts and posts meta database tables, especially if you add and delete galleries often.  It appears to me that the orphaned records are created during upload, so if you add images often they will build up over time.  I noticed this on a site where I upload hundreds of images a week and started noticing the database backups were extremely large. I started looking for what was causing the huge size of some of the tables and I found the issue. 

I created this plugin to clean up the orphaned records.  It analyzes the WordPress tables for orphaned records related to NextGEN and in the NextGEN specific tables as well.  It then shows you the number of orphans in each specific table. The plugin will clean up these orphaned records (delete), if you click "Clean".

WARNING - READ THIS!
I assume NO responsibility for any loss of data from using this plugin.
YOU SHOULD MAKE A FULL BACKUP BEFORE USING THE CLEAN FUNCTION, TO ALLOW RECOVERY IF THERE IS A PROBLEM.

It is beyond the scope of this plugin to cover all the information you may need to backup and successfully restore database tables. However I recommend reading my blog post 27 Essential WordPress Plugins: https://www.pqinternet.com/technology/27-essential-wordpress-plugins, and get whatever backup plugin I currently recommend. At the time of writing this sentence, I recommend BackWPup (link in the blog post along with some notes). You can select to backup just the tables this plugin cleans: wp_ngg_album, wp_ngg_gallery, wp_ngg_pictures, wp_posts, wp_postmeta (note that the wp_ prefix may be different on your system). There is also a link in my post to a utility to help import large sql dumps (backups) if they fail.

Read my blog post: Handling Huge MySQL Database Table Exports/Imports/Backups https://www.pqinternet.com/web-site-design-html-css/handling-huge-mysql-database-table-exports-imports-backups

NOTE: The "Analyze" and "Clean" functions can take A LONG TIME to execute, be patient, do not reload the page! The "Clean" function can take a VERY LONG TIME depending on how large and bloated the tables are!

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Tools->NextGEN Gallery DB Clean screen to Analyze and then Clean your database.
4. That's it... but MAKE A BACKUP BEFORE CLEANING!


== Frequently Asked Questions ==

= Is NextGEN going to fix this problem? =

Hopefully.  I've made them aware of what I found and gave them access to my test site.


== Screenshots ==

1. Analysis screen showing orphaned record count.
2. Results of cleaning process.
3. Analysis after cleaning showing no more orphaned records.

== Changelog ==
= 0.02 =
* Added summary section at end of analysis.

= 0.01 =
* Initial version


== Upgrade Notice ==

= 0.02 =
* Added summary section at end of analysis.

