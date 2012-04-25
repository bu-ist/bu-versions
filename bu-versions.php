<?php

/*
 Plugin Name: BU Versions
 Description: Make and review edits to published content.
 Version: 0.2
 Author: Boston University (IS&T)
*/

/**
 *
 *
 *
 * New Page Revision has to be dealt with. (perhaps with a css+js hack)
 * or/ can the page listing + revision listing combined into a single view.
 * -- there could be a way to attach a draft to a page
 *
 *
 * Perhaps alternate version should be captured as postmeta, too?
 *
 */

/// $views = apply_filters( 'views_' . $screen->id, $views );  // can be used to filter the views (All | Drafts | etc...


// $check = apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, $single );



class BU_Version_Workflow {

	public static $v_factory;
	public static $controller;
	public static $admin;

	static function init() {

		self::$v_factory = new BU_VPost_Factory();
		self::$v_factory->register_post_types();


		self::$controller = new BU_Version_Controller(self::$v_factory);
		// forgo the meta boxes for now...
		//add_action('do_meta_boxes', array('BU_Version_Workflow', 'register_meta_boxes'), 10, 3);


		add_action('transition_post_status', array(self::$controller, 'publish_version'), 10, 3);
		add_filter('the_preview', array(self::$controller, 'preview'), 12); // needs to come after the regular preview filter
		add_filter('template_redirect', array(self::$controller, 'redirect_preview'));

		add_rewrite_tag('%version_id%', '[^&]+'); // bring the revision id variable to life

		if(is_admin()) {
			self::$admin = new BU_Version_Admin_UI(self::$v_factory);
			add_filter('parent_file', array(self::$admin, 'parent_file'));
			add_action('admin_menu', array(self::$admin, 'admin_menu'));
			add_action('admin_notices', array(self::$admin, 'admin_notices'));

			add_action('load-admin_page_bu_create_version', array(self::$controller, 'load_create_version'));

		}

	}

}

add_action('init', array('BU_Version_Workflow', 'init'));


class BU_Version_Admin_UI {

	public $v_factory;

	function __construct($v_factory) {
		$this->v_factory = $v_factory;
	}

	function admin_menu() {
		$v_type_managers = $this->v_factory->managers();
		foreach($v_type_managers as $type => $manager) {
			$original_post_type = $manager->get_orig_post_type();
			if($original_post_type === 'post') {
				add_submenu_page( 'edit.php', null, 'Alternate Versions', 'edit_pages', 'edit.php?post_type=' . $type);
			} else {
				add_submenu_page( 'edit.php?post_type=' . $original_post_type, null, 'Alternate Versions', 'edit_pages', 'edit.php?post_type=' . $type);
			}
			add_action('manage_' . $original_post_type . '_posts_columns', array($manager->admin, 'orig_columns'));
			add_action('manage_' . $original_post_type . '_posts_custom_column', array($manager->admin, 'orig_column'), 10, 2);

			add_filter('manage_' . $type . '_posts_columns', array($manager->admin, 'alt_version_columns'));
			add_action('manage_' . $type . '_posts_custom_column', array($manager->admin, 'alt_version_column'), 10, 2);

			add_filter('views_edit-' . $original_post_type, array($manager->admin, 'filter_status_buckets'));

		}

		add_submenu_page(null, null, null, 'edit_pages', 'bu_create_version', array('BU_Version_Controller', 'create_version_view'));

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

			$post_id = $post_ID;
			if($post_id) {
				$post = get_post($post_id);

				if($this->v_factory->is_alt($post->post_type)) {
					$type = $this->v_factory->get($post->post_type);
					$original = get_post_type_object($type->get_orig_post_type());
					$version = new BU_Version();
					$version->get($post_id);
					printf('<div class="notice"><h3>This is a pending edit to an <a href="%s">existing %s</a>.</h3></div>', $version->get_original_edit_url(), lcfirst($original->labels->singular_name));
				} else {
					$manager = $this->v_factory->get_alt_manager($post->post_type);
					if(isset($manager)) {
						$versions = $manager->get_versions($post_id);
						if(is_array($versions) && !empty($versions)) {
							printf('<div class="notice"><h3>There is an alternate version for this page. <a href="%s">Edit</a></h3></div>', $versions[0]->get_edit_url());
						}
					}
				}

			}
		}
	}



	function parent_file($file) {
		if(strpos($file, 'edit.php') !== false) {
			$parts = parse_url($file);
			$params = null;
			parse_str($parts['query'], $params);
			if(isset($params['post_type'])) {
				$v_manager = $this->v_factory->get($params['post_type']);
				if(!is_null($v_manager)) {
					$file = add_query_arg($file, $v_manager->get_orig_post_type());
				}
			}
		}
		return $file;
	}
}

class BU_VPost_Factory {
	protected $v_post_types;

	function __construct() {
		$this->v_post_types = array();
	}

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
			'exclude_from_search' => false,
			'capability_type' => array('edit_pages'),
			//'capabilities' => array(), // need to figure out the capabilities piece
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
		);


		$post_types = get_post_types(array('show_ui' => true));

		foreach($post_types as $type) {

			// allow plugins/themes to control whether a post type supports alternate versions
			// consider using post_type supports
			if(false === apply_filters('bu_alt_versions_for_type', true, $type)) {
				continue;
			}

			$args = apply_filters('bu_alt_version_args', $default_args, $type);
			//$args['hierarchical'] = $type;
			$v_post_type = $type . '_alt_version';
			$this->v_post_types[$v_post_type] = new BU_Version_Manager($type, $v_post_type, $args);
		}
	}

	function managers() {
		return $this->v_post_types;
	}

	function get($post_type) {
		if(is_array($this->v_post_types)  && array_key_exists($post_type, $this->v_post_types)) {
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
		return array_key_exists($post_type, $this->v_post_types);
	}

}

class BU_Version_Manager {


	/**
	 * Post type of the alternate version
	 * @var type
	 */
	public $post_type = null;

	/**
	 * Post type of the originals
	 *
	 * @var type
	 */

	public $orig_post_type = null;
	public $admin = null;

	function __construct($orig_post_type, $post_type, $args) {
		register_post_type($post_type, $args);
		$this->post_type = $post_type;
		$this->orig_post_type = $orig_post_type;

		if(is_admin()) {
			$this->admin = new BU_Version_Manager_Admin($this->post_type);
		}

	}

	function create($post_id) {
		$post = get_post($post_id);
		$version = new BU_Version();
		$version->create($post, $this->post_type);
		return $version;
	}

	function get_orig_post_type() {
		return $this->orig_post_type;
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

	function add_caps() {
		// get_roles

		// foreach roles as role

		// if ! has_cap then add cap
		// need to have filtering to allow a plugin/theme to control whether caps are added automatically
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

	function alt_version_columns($columns) {

		$insertion_point = 3;
		$i = 1;
		$new_columns = array();

		foreach($columns as $key => $value) {
			if($i == $insertion_point) {
				$new_columns['original_edit'] = 'Original';
			}
			$new_columns[$key] = $columns[$key];
			$i++;
		}

		return $new_columns;
	}

	function alt_version_column($column_name, $post_id) {
		if($column_name != 'original_edit') return;
		$post = get_post($post_id);
		echo '<a href="' . get_edit_post_link( $post->post_parent, true ) . '" title="' . esc_attr( __( 'Edit this item' ) ) . '">' . __( 'edit' ) . '</a>';
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
			$version = new BU_Version($version_id);
			printf('<a href="%s" title="%s">edit</a>', get_edit_post_link($version_id, true), esc_attr(__( 'Edit this item')));
		} else {
			$post = get_post($post_id);
			if($post->post_status == 'publish') {
				printf('<a class="bu_version_clone" href="%s">create clone</a>', BU_Version_Controller::get_URL($post));
			}
		}
	}

}

// add class that can be used for each post_type

class BU_Version_Controller {
	public $v_factory;

	function __construct($v_factory) {
		$this->v_factory = $v_factory;
	}

	function get_URL($post) {
		$url = 'admin.php?page=bu_create_version';
		$url = add_query_arg(array('post_type' => $post->post_type, 'post' => $post->ID), $url);
		return wp_nonce_url($url, 'create_version');
	}

	function publish_version($new_status, $old_status, $post) {

		if($new_status === 'publish' && $old_status !== 'publish' && $this->v_factory->is_alt($post->post_type)) {
			$version = new BU_Version();
			$version->get($post->ID);
			$version->publish();
			// Is this the appropriate spot for a redirect?
			wp_redirect($version->get_original_edit_url());
			exit;
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
			wp_redirect($url, 302);
			exit();
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

			$v_manager = $this->v_factory->get_alt_manager($post->post_type);

			$version = $v_manager->create($post_id);

			$redirect_url = add_query_arg(array('post' => $version->get_id(), 'action' => 'edit'), 'post.php');
			wp_redirect($redirect_url);
			exit();
		}
	}

	static function create_version_view() {
		   // no-op
	}

}
// @see _set_preview() -- WordPress uses the autosave to generate previews. We can use
// the same approach when overriding the display of a page.


// should be composite of the original and its versions
class BU_Version {

	public $original;
	public $post;

	function __construct() {
	}


	function get($version_id) {
		$this->post = get_post($version_id);
		$this->original = get_post($this->post->post_parent);
	}

	function create($post, $alt_post_type) {
		$this->original = $post;
		$new_version['post_type'] = $alt_post_type;
		$new_version['post_parent'] = $this->original->ID;
		$new_version['ID'] = null;
		$new_version['post_status'] = 'draft';
		$new_version['post_content'] = $this->original->post_content;
		$new_version['post_name'] = $this->original->post_name;
		$new_version['post_title'] = $this->original->post_title;
		$new_version['post_excerpt'] = $this->original->post_excerpt;
		$id = wp_insert_post($new_version);
		$this->post = get_post($id);
		update_post_meta($post->ID, '_bu_version', $id);

		return $id;

	}

	function publish() {
		if(!isset($this->original) || !isset($this->post)) return false;

		$post = array();
		$post['ID'] = $this->original->ID;
		$post['post_title'] = $this->post->post_title;
		$post['post_content'] = $this->post->post_content;
		$post['post_excerpt'] = $this->post->post_excerpt;
		wp_update_post($post);
		wp_delete_post($this->post->ID);
		delete_post_meta($this->original->ID, '_bu_version');

		return true;

	}

	function get_id() {
		return $this->post->ID;
	}

	function get_original_edit_url() {
		return get_edit_post_link($this->original->ID, 'redirect');
	}

	function get_edit_url() {
		return get_edit_post_link($this->post->ID);
	}

	function get_preview_URL() {
		if(!isset($this->original) || !isset($this->post)) return null;

		$permalink = get_permalink($this->post);
		$url = add_query_arg(array('version_id' => $this->post->ID, 'preview'=> 'true', 'p' => $this->post->post_parent, 'post_type' => $this->original->post_type), $permalink);
		return $url;
	}
}


?>
