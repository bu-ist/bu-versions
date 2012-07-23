BU Versions is a WordPress plugin that adds new workflows to WordPress that make it possible to create, review, and schedule changes to published content with ease.

The plugin was written by [Boston University IS&T](http://www.bu.edu/tech) staff with design and UX support from the [Interactive Design](http://www.bu.edu/id)  group in Marketing & Communications.

Features:

* An alternate version can be created for each post or page.
* Revision history is accessible for the alternate version.
* Supports custom post types automatically.
* Supports the cloning of post meta data.

Roadmap:

* Add support for page templates to the Alternate Version for pages.
* Add an "Edit Version" link to the frontend admin bar.


To report an issue, file an issue on [Github](https://github.com/bu-ist/bu-versions/issue).

For Plugin Developers:

To add postmeta support for the BU Versions plugin, must use the following filter
to register the feature and the postmeta keys for the feature. During a clone,
the data associated with the meta key will be copied. When the alternate version
is "published" the data will overwrite the data stored in each meta key of the
original.

```php

function foo_register_alt_version_features($features) {
	$features['feature_name'] = array(
		'_foo_meta_key1',
		'_foo_meta_key2'
	);
	return $features;
}


add_filter('bu_alt_versions_feature_support', 'foo_register_alt_version_features');

```
