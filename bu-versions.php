<?php

/*
 Plugin Name: BU Versions
 Description: Make and review edits to published content.
 Version: 0.5
 Author: Boston University (IS&T)
*/

/**
Copyright 2012 by Boston University

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

**/

/**
 * @todo split into multiple files
 * @todo verify presence of alternate version when loading original to fix orphans.
 **/

class BU_Version_Workflow {

	public static $v_factory;
	public static $controller;
	public static $admin;

	static function init() {

		self::$v_factory = new BU_VPost_Factory();
		self::$v_factory->register_post_types();


		self::$controller = new BU_Version_Controller(self::$v_factory);

		add_action('transition_post_status', array(self::$controller, 'publish_version'), 10, 3);
		add_filter('the_preview', array(self::$controller, 'preview'), 12); // needs to come after the regular preview filter

		add_action('template_redirect', array(self::$controller, 'redirect_preview'));
		add_action('template_redirect', array(self::$controller, 'override_meta'), 1);

		if(version_compare($GLOBALS['wp_version'], '3.3.2', '>=')) {
			add_action('before_delete_post', array(self::$controller, 'delete_post_handler'));
			add_action( 'admin_bar_menu', array( self::$controller, 'admin_bar_menu' ), 81 ); // since we are replaying the menu the priority needs to vary
		} else {
			add_action('delete_post', array(self::$controller, 'delete_post_handler'));
			add_action( 'admin_bar_menu', array( self::$controller, 'admin_bar_menu' ), 31 );
		}

		add_action('map_meta_cap', array(self::$controller, 'map_meta_cap'), 20, 4);

		add_rewrite_tag('%version_id%', '[^&]+'); // bring the version id variable to life
		add_filter('get_edit_post_link', array(self::$controller, 'override_edit_post_link'), 10, 3);

		if(is_admin()) {
			self::$admin = new BU_Version_Admin( self::$v_factory );
			self::$admin->bind_hooks();
			add_action('load-admin_page_bu_create_version', array( self::$controller, 'load_create_version' ) );
		}

	}

}

add_action('init', array('BU_Version_Workflow', 'init'), 999);


class BU_Version_Admin {

	public $v_factory;

	function __construct($v_factory) {
		$this->v_factory = $v_factory;
	}

	function bind_hooks() {
		add_filter('parent_file', array($this, 'parent_file'));
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('admin_notices', array($this, 'admin_notices'));
		add_filter('admin_body_class', array($this, 'admin_body_class'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue'), 10, 1);
		add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 10, 2);
		add_action('save_post', array($this, 'save_page_template'), 10, 2);
	}

	function enqueue() {
		wp_enqueue_script('bu-versions', plugins_url('/js/bu-versions.js', __FILE__));
		wp_enqueue_style('bu-versions', plugins_url('/css/bu-versions.css', __FILE__));
	}

	function admin_menu() {
		$v_type_managers = $this->v_factory->managers();
		foreach( $v_type_managers as $type => $manager ) {
			$original_post_type = $manager->get_orig_post_type();
			if( $original_post_type === 'post' ) {
				add_submenu_page( 'edit.php', null, 'Alternate Versions', 'edit_pages', 'edit.php?post_type=' . $type);
			} else {
				add_submenu_page( 'edit.php?post_type=' . $original_post_type, null, 'Alternate Versions', 'edit_pages', 'edit.php?post_type=' . $type);
			}
			add_action('manage_' . $original_post_type . '_posts_columns', array($manager->admin, 'orig_columns'));
			add_action('manage_' . $original_post_type . '_posts_custom_column', array($manager->admin, 'orig_column'), 10, 2);

			add_filter('views_edit-' . $original_post_type, array($manager->admin, 'filter_status_buckets'));

		}

		add_submenu_page(null, null, null, 'edit_pages', 'bu_create_version', array('BU_Version_Controller', 'create_version_view'));

	}

	function admin_body_class($classes) {
		global $current_screen;

		$post_type = $current_screen->post_type;
		if($this->v_factory->is_alt($post_type)) {
			if(empty($classes)) {
				$classes = 'bu_alt_postedit';
			} else {
				$classes .= ' bu_alt_postedit';
			}
		}
		return $classes;
	}

	/**
	 * Display an admin notice on pages that have an alternate version in draft form.
	 *
	 * @global type $current_screen
	 * @global type $post_ID
	 */
	function admin_notices() {
		global $current_screen;
		global $post_ID;


		if($current_screen->base == 'post') {

			if($post_ID) {
				$post = get_post($post_ID);

				if($this->v_factory->is_alt($post->post_type)) {
					$type = $this->v_factory->get($post->post_type);
					$original = get_post_type_object($type->get_orig_post_type());
					$version = new BU_Version();
					$version->get($post_ID);
					if(function_exists('lcfirst')) {
						$label = lcfirst($original->labels->singular_name);
					} else {
						$label = $original->labels->singular_name;
						$label[0] = strtolower($label[0]);
					}
					printf('<div class="updated bu-version-notice"><p>This is a clone of an existing %s and will replace the <a href="%s" target="_blank">original %s</a> when published.</p></div>', $label, $version->get_original_edit_url(), $label);
				} else {
					$manager = $this->v_factory->get_alt_manager($post->post_type);
					if(isset($manager)) {
						$versions = $manager->get_versions($post_ID);
						if(is_array($versions) && !empty($versions)) {
							printf('<div class="updated bu-version-notice"><p>There is an alternate version for this page. <a href="%s" target="_blank">Edit</a></p></div>', $versions[0]->get_edit_url());
						}

						// post overwritten with alternate version

						$overwritten_post_id = get_option('_bu_version_post_overwritten');
						if(!empty($overwritten_post_id) && $post->ID == $overwritten_post_id) {
							printf('<div class="updated bu-version-notice"><p>The alternate version has replace the data of this post and been deleted.</p></div>');
							delete_option('_bu_version_post_overwritten');
						}
					}
				}

			}
		}
	}



	function parent_file($file) {
		if(strpos($file, 'edit.php') !== false) {
			$parts = parse_url($file);
			if(!isset($parts['query'])) return $file;
			$params = null;
			parse_str($parts['query'], $params);
			if(isset($params['post_type'])) {
				$v_manager = $this->v_factory->get($params['post_type']);
				if(!is_null($v_manager)) {
					$orig_post_type = $v_manager->get_orig_post_type();
					if( $orig_post_type === 'post') {
						$file = 'edit.php';
					} else {
						$file = add_query_arg(array('post_type' => $orig_post_type), $file);
					}

				}
			}
		}
		return $file;
	}


	function add_meta_boxes($post_type, $post) {
		if( $this->v_factory->is_alt( $post_type ) ) {
			$manager = $this->v_factory->get( $post_type );
			$original_post_type = $manager->get_orig_post_type();

			if( $original_post_type == 'page' &&  0 != count( get_page_templates() ) ) {
				add_meta_box('bu-page-template', 'Page Attributes', array($this,'page_template_meta_box'), $post_type, 'side', 'core');
			}
		}
	}

	function save_page_template($post_id, $post) {

		if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;

		$manager = $this->v_factory->get($post->post_type);

		if( ! $manager || $manager->get_orig_post_type() != 'page' ) return;

		$page_template = strip_tags( trim( $_POST['bu_page_template'] ) );
		$page_templates = get_page_templates();

		if ( 'default' == $page_template || in_array($page_template, $page_templates) ) {
			update_post_meta($post_id, '_wp_page_template',  $page_template);
		}
	}

	function page_template_meta_box($post, $box) {
		$page_template = get_post_meta($post->ID, '_wp_page_template', true);

		include dirname( __FILE__ ) . '/interface/page-template.php';
	}
}

class BU_VPost_Factory {
	protected $v_post_types;

	function __construct() {
		$this->v_post_types = array();
	}

	/**
	 * Registers an "alt" post type for each post_type that has show_ui enabled.
	 *
	 * Capabilities are inherited from the parent post_type.
	 */
	function register_post_types() {

		$labels = array(
			'name' => _x('Alternate Versions', 'post type general name'),
			'singular_name' => _x('Alternate Version', 'post type singular name'),
			'add_new' => _x('Add New', ''),
			'add_new_item' => __('Add New Version'),
			'edit_item' => __('Edit Alternate Version'),
			'new_item' => __('New'),
			'view_item' => __('View Alternate Version'),
			'search_items' => __('Search Alternate Versions'),
			'not_found' =>  __('No Alternate Versions found'),
			'not_found_in_trash' => __('No Alternate Versions found in Trash'),
			'parent_item_colon' => '',
			'menu_name' => 'Alternate Versions'
		);

		$default_args = array(
			'labels' => $labels,
			'description' => '',
			'publicly_queryable' => true,
			'exclude_from_search' => true,
			'map_meta_cap' => true,
			'hierarchical' => false,
			'rewrite' => false,
			'has_archive' => false,
			'query_var' => true,
			'supports' => array('editor', 'title', 'author', 'revisions' ), // copy support from the post_type
			'taxonomies' => array(),
			'show_ui' => true,
			'show_in_menu' => false,
			'menu_position' => null,
			'menu_icon' => null,
			'permalink_epmask' => EP_PERMALINK,
			'can_export' => true,
			'show_in_nav_menus' => false,
			'show_in_menu' => false,
			'public' => true
		);

		$post_types = get_post_types(array('show_ui' => true), 'objects');


		$alt_supported_features = array(
			'thumbnail' => array('_thumbnail_id'),
			'bu-content-banner' => array('_bu_banner'),
			'bu-post-details' => array(
				'_bu_thumbnail',
				'_bu_page_description',
				'_bu_meta_description',
				'_bu_meta_mirror_description',
				'_bu_meta_keywords',
				'_bu_meta_robots',
				'_bu_title'
			),
			'bu-disable-autop' => array('_bu_disable_autop')
		);

		// plugins/themes can add support for particular features by filtering
		// the array of supported features
		$alt_supported_features = apply_filters('bu_alt_versions_feature_support', $alt_supported_features);

		foreach($post_types as $type) {

			// allow plugins/themes to control whether a post type supports alternate versions
			// consider using post_type supports
			if(false === apply_filters('bu_alt_versions_for_type', true, $type)) {
				continue;
			}

			$args = $default_args;

			$args['capability_type'] = $type->capability_type;

			foreach(array_keys($alt_supported_features) as $feature) {
				if( post_type_supports($type->name, $feature) ) {
					$args['supports'][] = $feature;
				}
			}


			$args = apply_filters('bu_alt_version_args', $args, $type);

			$meta_keys = array();

			foreach( $args['supports'] as $feature ) {
				if( isset( $alt_supported_features[ $feature ] ) ) {
					$meta_keys = array_merge( $meta_keys, $alt_supported_features[ $feature ] );
				}
			}

			// special case to copy the page template
			if( $type->name == 'page' ) {
				$meta_keys[] = '_wp_page_template';
			}

			$v_post_type = $type->name . '_alt';

			$register = register_post_type($v_post_type, $args);
			if(!is_wp_error($register)) {
				$this->v_post_types[$v_post_type] = new BU_Version_Manager($type->name, $v_post_type, $args, $meta_keys);
			} else {
				error_log(sprintf('The alternate post type %s could not be registered. Error: %s', $v_post_type, $register->get_error_message()));
			}
		}
	}

	function managers() {
		return $this->v_post_types;
	}

	function get($post_type) {
		if( $this->is_alt( $post_type ) ) {
			return $this->v_post_types[$post_type];
		} else {
			return null;
		}
	}

	function get_alt_types() {
		return array_keys($this->v_post_types);
	}

	function get_alt_manager($post_type) {
		foreach($this->v_post_types as $manager) {
			if($manager->orig_post_type === $post_type) {
				return $manager;
			}

		}
	}

	function is_alt($post_type) {
		return is_array($this->v_post_types) && array_key_exists($post_type, $this->v_post_types);
	}

}

class BU_Version_Manager {


	/**
	 * Post type of the alternate version
	 * @var type
	 */
	public $post_type = null;

	public $meta_keys;

	/**
	 * Post type of the originals
	 *
	 * @var type
	 */

	public $orig_post_type = null;
	public $admin = null;

	function __construct($orig_post_type, $post_type, $args, $meta_keys) {

		$this->post_type = $post_type;
		$this->orig_post_type = $orig_post_type;
		$this->meta_keys = $meta_keys;

		if(is_admin()) {
			$this->admin = new BU_Version_Manager_Admin( $this->post_type );
		}

	}

	function create( $post_id ) {

		$version = new BU_Version();

		$result = $version->create( $post_id, $this->post_type, $this->meta_keys );

		if( is_wp_error( $result ) ) {
			return $result;
		} else {
			return $version;
		}
	}

	function publish($post_id) {
			$version = new BU_Version();
			$version->get($post_id);

			$result =  $version->publish( $this->meta_keys );
			if( $result ) {
				return $version;
			} else {
				return false;
			}
	}

	function get_orig_post_type() {
		return $this->orig_post_type;
	}


	function override_meta($val, $object_id, $key, $single) {
		if( in_array( $key, $this->meta_keys ) && isset( $_GET['version_id'] )  ) {
			$version_id = (int) trim( $_GET['version_id'] );
			$version = new BU_Version();
			$version->get($version_id);
			remove_filter('get_post_metadata', array($this, 'override_meta'), 10, 4);
			if($object_id == $version->original->ID) {
				$val = get_post_meta($version->post->ID, $key);
			}
			add_filter('get_post_metadata', array($this, 'override_meta'), 10, 4);
		}
		return $val;
	}

	function get_versions($orig_post_id) {
		$args = array(
			'post_parent' => (int) $orig_post_id,
			'post_type' => $this->post_type,
			'posts_per_page' => -1,
			'post_status' => 'any'
		);

		$query = new WP_Query($args);
		$posts = $query->get_posts();

		if(empty($posts)) {
			return null;
		}

		$versions = array();

		foreach($posts as $post) {
			$version = new BU_Version();
			$version->get($post->ID);
			$versions[] = $version;

		}
		return $versions;

	}

	function delete_versions($orig_post_id) {
		$versions = $this->get_versions($orig_post_id);
		if( ! isset( $versions ) ) return;
		foreach($versions as $version) {
			$version->delete_version();
		}
	}

}

class BU_Version_Manager_Admin {

	public $post_type;

	function __construct($post_type) {
		$this->post_type = $post_type;
	}

	function filter_status_buckets($views) {

		// need to handle counts
		$views['pending_edits'] = sprintf('<a href="edit.php?post_type=%s">Alternate Versions</a>', $this->post_type);
		return $views;
	}


	function orig_columns($columns) {
		$insertion_point = 3;
		$i = 1;
		$new_columns = array();

		foreach($columns as $key => $value) {
			if($i == $insertion_point) {
				$new_columns['alternate_versions'] = 'Alternate Versions';
			}
			$new_columns[$key] = $columns[$key];
			$i++;
		}

		return $new_columns;
	}

	function orig_column($column_name, $post_id) {
		if($column_name != 'alternate_versions') return;
		$version_id = get_post_meta($post_id, '_bu_version', true);
		if(!empty($version_id)) {
			$version = new BU_Version();
			$version->get($version_id);
			printf('<a class="bu_version_edit" href="%s" title="%s">edit version</a>', $version->get_edit_url('display'), esc_attr(__( 'Edit this item')));
		} else {
			$post = get_post($post_id);
			if($post->post_status == 'publish') {
				printf('<a class="bu_version_clone" href="%s">create clone</a>', BU_Version_Controller::get_URL($post));
			}
		}
	}
}

class BU_Version_Controller {
	public $v_factory;

	function __construct($v_factory) {
		$this->v_factory = $v_factory;
	}

	function map_meta_cap($caps, $cap, $user_id, $args) {

		if(isset($_GET['version_id'])) {
			$version_id = (int) trim($_GET['version_id']);
			$version = new BU_Version();
			$version->get($version_id);
			if(is_object($version->original)) {
				$post_type = get_post_type_object($version->original->post_type);
				$caps = array($post_type->cap->edit_posts);
			}

		}

		return $caps;
	}

	function get_URL($post) {
		$url = 'admin.php?page=bu_create_version';
		$url = add_query_arg(array('post_type' => $post->post_type, 'post' => $post->ID), $url);
		return wp_nonce_url($url, 'create_version');
	}

	function publish_version($new_status, $old_status, $post) {

		if($new_status === 'publish' && $old_status !== 'publish' && $this->v_factory->is_alt($post->post_type)) {

			$manager = $this->v_factory->get($post->post_type);
			$version = $manager->publish( $post->ID );
			if( $version ) {
				wp_redirect($version->get_original_edit_url());
				exit;
			} else {
				wp_die(sprintf('The alternate version id: %s could not be published.', $post->ID));
			}
		}
	}

	/**
	 * Add filters to override post meta data
	 **/

	function override_meta() {
		if(is_preview() && isset($_GET['version_id'])) {
			$version_id = (int) trim($_GET['version_id']);
			$version = new BU_Version();
			$version->get($version_id);
			if(isset($version->post->post_type)) {
				$manager = $this->v_factory->get($version->post->post_type);
				add_filter('get_post_metadata', array($manager, 'override_meta'), 10, 4);
			}
		}
	}


	/**
	 * Redirect page_version previews to the orginal page, but with a specific
	 * parameter included that triggers the content to be replaced with the data
	 * from the new version.
	 */
	function redirect_preview() {
		$alt_versions = $this->v_factory->get_alt_types();
		if(is_preview() && is_singular($alt_versions)) {
			$request = strtolower(trim($_SERVER['REQUEST_URI']));
			$request = preg_replace('#\?.*$#', '', $request);
			$version_id = (int) get_query_var('p');
			$version = new BU_version();
			$version->get($version_id);
			$url = $version->get_preview_URL();
			if( isset( $url ) && $url != $_SERVER['REQUEST_URI'] ) {
				wp_redirect($url, 302);
				exit();
			}
		}
	}


	function preview($post) {
		if ( ! is_object($post) )
			return $post;

		$version_id = (int) get_query_var('version_id');
		$preview = wp_get_post_autosave($version_id);
		if ( ! is_object($preview) ) {
			$preview = get_post($version_id);
			if( !is_object($preview)) return $post;
		}

		$preview = sanitize_post($preview);

		$post->post_content = $preview->post_content;
		$post->post_title = $preview->post_title;
		$post->post_excerpt = $preview->post_excerpt;

		return $post;

	}
	// GET handler used to create a version
	function load_create_version() {
		if(wp_verify_nonce($_GET['_wpnonce'], 'create_version')) {
			$post_id = (int) $_GET['post'];

			$post = get_post($post_id);
			if( ! $post ) {
				wp_die("The post to be cloned could not be found.");
			}

			$v_manager = $this->v_factory->get_alt_manager($post->post_type);

			$result = $v_manager->create( $post_id );
			if( ! is_wp_error( $result ) ) {
				$redirect_url = add_query_arg(array('post' => $result->get_id(), 'action' => 'edit'), 'post.php');
				wp_redirect($redirect_url);
				exit();
			} else {
				wp_die("The alternate version could not be created. " . $result->get_error_message() );
			}
		}
	}


	function delete_post_handler($post_id) {
		$post = get_post($post_id);

		if( $this->is_alt( $post->post_type ) ) {
			$version = new BU_Version();
			$version->get($post_id);
			$version->delete_parent_meta();
		} elseif($post->post_type != 'revision') {
			$manager = $this->v_factory->get_alt_manager($post->post_type);
			if($manager) {
				$manager->delete_versions($post->ID);
			}

		}
	}

	function override_edit_post_link($url, $post_id, $context) {

		$version_id = get_query_var('version_id');
		if(! empty( $version_id ) && $post_id != $version_id) {
			$version = new BU_Version;
			$version->get($version_id);
			$url = $version->get_edit_url();
		}
		return $url;
	}

	function admin_bar_menu() {
		global $wp_admin_bar;

		if(is_singular() && is_object( $wp_admin_bar ) ) {
			if( is_preview() && isset( $_GET['version_id'] ) ) {
				$post_id = (int) $_GET['version_id'];
				$current_object = get_post( $post_id );
			} else {
				$current_object = get_queried_object();
			}

			$current_post_type = get_post_type_object( $current_object->post_type );

			if( ! isset( $current_object ) ) return;

			$version = new BU_Version();
			if( $this->is_alt( $current_object->post_type ) ) {
				$version->get( $current_object->ID );
			} else {
				$version->get_version( $current_object->ID );
			}

			$original_post_type = get_post_type_object( $version->original->post_type );

			if( $version->has_version() ) {
				$alternate_post_type = get_post_type_object( $version->post->post_type );
			}


			$wp_admin_bar->remove_menu('edit');

			// in this case we don't want to override the edit links
			remove_filter('get_edit_post_link', array($this, 'override_edit_post_link'), 10, 3);

			if( current_user_can( $current_post_type->cap->edit_post, $current_object->ID ) ) {
				$wp_admin_bar->add_menu( array( 'id' => 'bu-edit', 'title' => _x( 'Edit', 'admin bar menu group label' ), 'href' => get_edit_post_link( $current_object->ID ) ) );

				if( current_user_can( $original_post_type->cap->edit_post, $version->original->ID ) ) {

					$wp_admin_bar->add_menu( array( 'parent' => 'bu-edit', 'id' => 'bu-edit-original', 'title' => 'Edit Original', 'href' => $version->get_original_edit_url() ) );

					if( $version->has_version() && current_user_can( $alternate_post_type->cap->edit_post, $version->post->ID ) ) {
						$wp_admin_bar->add_menu( array( 'parent' => 'bu-edit', 'id' => 'bu-edit-alt', 'title' => 'Edit Alternate Version', 'href' => $version->get_edit_url() ) );
					}
				}

			} elseif ( $version->has_version() && current_user_can( $alternate_post_type->cap->edit_post, $version->post->ID ) ) {
					$wp_admin_bar->add_menu( array( 'id' => 'bu-edit-alt', 'title' => 'Edit Alternate Version', 'href' => $version->get_edit_url() ) );
			}
			add_filter('get_edit_post_link', array($this, 'override_edit_post_link'), 10, 3);
		}
	}

	function is_alt( $post_type ) {
		return $this->v_factory->is_alt( $post_type );
	}

	static function create_version_view() {
		   // no-op
	}
}

class BU_Version {

	public $original = null;
	public $post = null;

	function get_version($post_id) {
		if( ! isset( $this->original ) ) {
			$original = get_post( $post_id );
			if( $original ) {
				$this->original = $original;

				$version_id = get_post_meta( $this->original->ID, '_bu_version', true );

				if( ! empty( $version_id ) ) {

					$version = get_post( $version_id );

					if( $version ) {
						$this->post = $version;
					}
				}
			}
		}
		return $this->post;
	}

	function get($version_id) {
		$this->post = get_post($version_id);
		$this->original = get_post($this->post->post_parent);
	}

	/**
	 * create
	 *
	 * @param mixed $post
	 * @param mixed $alt_post_type
	 * @access public
	 * @return int|WP_Error
	 */
	function create($post_id, $alt_post_type, $meta_keys = null) {
		$this->get_version( $post_id );
		if( $this->has_version() ) {
			return new WP_Error( 'alternate_already_exists', 'An alternate version already exists for this post.' );
		}

		$this->original = get_post( $post_id );
		if( ! isset( $this->original ) ) {
			return new WP_Error( 'alternate_no_original', 'The post ID: ' . $post_id . ' could not be found.' );
		}

		$new_version['post_type'] = $alt_post_type;
		$new_version['post_parent'] = $this->original->ID;
		$new_version['ID'] = null;
		$new_version['post_status'] = 'draft';
		$new_version['post_content'] = $this->original->post_content;
		$new_version['post_name'] = $this->original->post_name;
		$new_version['post_title'] = $this->original->post_title;
		$new_version['post_excerpt'] = $this->original->post_excerpt;

		$result = wp_insert_post($new_version);

		if( ! is_wp_error($result) ) {
			$this->post = get_post($result);
			$this->copy_original_meta($meta_keys);
			update_post_meta($this->original->ID, '_bu_version', $this->post->ID);
		}

		return $result;

	}

	/**
	 * Because of sanization and serialization, it may be better to use SQL, but for now we are using the API
	 **/
	private function copy_original_meta($meta_keys) {
		foreach( $meta_keys as $key ) {
			$values = get_post_meta( $this->original->ID, $key );

			foreach( $values as $v ) {
				update_post_meta( $this->post->ID, $key, $v );
			}
		}
		update_post_meta( $this->post->ID, '_bu_version_copied_keys', $meta_keys);
	}

	function publish($meta_keys = null) {
		if(!isset($this->original) || !isset($this->post)) return false;

		$post = (array) $this->original;
		$post['post_title'] = $this->post->post_title;
		$post['post_content'] = $this->post->post_content;
		$post['post_excerpt'] = $this->post->post_excerpt;
		$result = wp_update_post($post);
		if( ! is_wp_error( $result ) ) {
			add_option('_bu_version_post_overwritten', $result);
			if(isset($meta_keys)) {
				$this->overwrite_original_meta( $meta_keys );
			}
			$this->delete_version();
		}
		return $result;
	}

	private function overwrite_original_meta($meta_keys) {
		$copied_keys = get_post_meta( $this->post->ID, '_bu_version_copied_keys', true );
		foreach( $meta_keys as $key ) {
			// we only delete keys that we are sure were copied
			if( is_array( $copied_keys ) && in_array( $key, $copied_keys ) ) {
				delete_post_meta( $this->original->ID, $key );
			}
			$values = get_post_meta( $this->post->ID, $key );
			foreach( $values as $v ) {
				update_post_meta( $this->original->ID, $key, $v );
			}
		}
	}

	function delete_version() {
		wp_delete_post($this->post->ID);
		$this->delete_parent_meta();
	}

	function delete_parent_meta() {
		delete_post_meta($this->original->ID, '_bu_version');
	}

	function get_id() {
		return $this->post->ID;
	}

	function has_version() {
		return isset( $this->post );
	}

	function get_original_edit_url($context = null) {
		return get_edit_post_link($this->original->ID, $context);
	}

	function get_edit_url($context = 'display') {
		return get_edit_post_link($this->post->ID, $context);
	}

	function get_preview_URL() {
		if( ! isset( $this->original ) || ! isset( $this->post ) || $this->post->ID == $this->original->ID ) return null;

		$permalink = get_permalink($this->original);
		$url = add_query_arg(array('version_id' => $this->post->ID, 'preview'=> 'true'), $permalink);
		return $url;
	}
}


?>
