=== BU Versions ===
Contributors: gcorne, mgburns
Tags: content editing, workflow, version, merge, boston university, bu
Requires at least: 3.1
Tested up to: 3.5
Stable tag: 0.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Make and save edits to published posts/pages in WordPress without immediately replacing the public content.

== Description ==

BU Versions is a WordPress plugin that adds new workflows to WordPress that make it possible to create, review, and schedule changes to published content with ease.

With BU Versions, you can clone any published post in WordPress (page, post, or custom post type) to edit and save an alternate version of the post without replacing the public content. You can make multiple edits over any timeframe, all done “behind the scenes” without disruptive or unreviewed changes to the live content. This enables a publishing workflow for edits to content that need to be reviewed and approved before being re-published. Once ready, publishing your version will replace the original post.

The plugin was written by [Boston University IS&T](http://www.bu.edu/tech) staff with design and UX support from the [Interactive Design](http://www.bu.edu/id) group in Marketing & Communications.

= Features =

* An alternate version can be created for each post or page.
* Revision history is accessible for the alternate version.
* Supports custom post types automatically.
* Supports the cloning of post meta data.

For more information check out [http://developer.bu.edu/bu-versions/](http://developer.bu.edu/bu-versions/).

= Developers =

For developer documentation, feature roadmaps and more visit the [plugin repository on Github](https://github.com/bu-ist/bu-versions/).

== Installation ==

This plugin can be installed automatically through the WordPress admin interface, or by clicking the downlaod link on this page and installing manually.

= Manual Installation =

1. Upload the `bu-versions` plugin folder to the `/wp-content/plugins/` directory
2. Activate 'BU Versions' through the 'Plugins' menu in WordPress

To complete the advanced permissions workflow, install the [BU Section Editing Plugin](http://wordpress.org/extend/plugins/bu-section-editing "BU Section Editing Plugin").

== Frequently Asked Questions ==

= I’m a plugin developer and my plugin utilizes custom post meta boxes. How do I get them to show up while editing alternate versions? =

The plugin provides a filter which allows you to register support for your custom features for the alternate version post types.

Please see our Github wiki page to learn [how to register alternate version support](https://github.com/bu-ist/bu-versions/wiki/Adding-Post-Meta-Support-for-Alternate-Versions) for your plugin metaboxes.

== Screenshots ==

1. Create alternate versions from the posts table using the “create clone” button
2. Easily see which posts have alternate versions in progress from the posts table
3. Admin notices help you keep track of which version of a post you are currently editing
4. One click replacement of original post with alternate version content
5. Admin bar integration makes it easy to edit alternate versions in addition to original posts from the front-end

== Changlog ==

= 0.7 =

* Initial WP.org release
* Added localization support

= 0.6 =

* Fix issue with custom columns when using Quick Edit / Bulk Edit to modify posts.
* Fix issue with bulk publishing / quick editing alternate versions and changing their status to "Published".
* Include post type name in labels so that post type lists such as in the export work properly.

= 0.5 =

* Initial Boston University release
* Replaced the "Edit <post_type>" admin bar menu with a "Edit" dropdown.

= 0.4 =

* Added support for the cloning / overwriting of meta data.