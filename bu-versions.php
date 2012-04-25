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


		add_filter('parent_file', array('BU_Version_Admin', 'parent_file'));

		add_action('admin_init', array('BU_Version_Workflow', 'admin_init'));

		add_action('load-admin_page_bu_create_revision', array('BU_Version_Controller', 'load_create_revision'));


		add_action('transition_post_status', array('BU_Version_Controller', 'publish_revision'), 10, 3);
		add_filter('the_preview', array('BU_Version_Controller', 'preview'), 12); // needs to come after the regular preview filter
		add_filter('template_redirect', array('BU_Version_Controller', 'redirect_preview'));

		add_rewrite_tag('%revision%', '[^&]+'); // bring the revision id variable to life

		///add_filter('map_meta_cap', array('BU_Section_Editor', 'map_meta_cap'), 10, 4);

	}


	static function admin_init() {
		self::$admin = new BU_Version_Admin_UI(self::$v_factory);
		add_action('admin_menu', array(self::$admin, 'admin_menu'));
		add_action('admin_notices', array(self::$admin, 'admin_notices'));
	}



}

add_action('init', array('BU_Version_Workflow', 'init'));

class BU_Version_Admin_UI {

	public $v_factory;

	function __construct($v_factory) {
		$this->$v_factory;
	}

	function admin_menu() {

		foreach(self::$v_factory as $type) {
			$orginal_post_type = $type->get_orig_post_type();

			add_submenu_page( 'edit.php?post_type=' . $orginal_post_type, null, 'Alternate Versions', 'edit_pages', 'edit.php?post_type' . $type->post_type);

			add_action('manage_' . $original_post_type . '_posts_columns', array($type->admin, 'orig_posts_columns'));
			add_action('manage_' . $original_post_type . '_posts_custom_column', array($this->admin, 'orig_post_column'));

			add_filter('manage_' . $type->post_type . '_posts_columns', array($type->admin, 'posts_columns'));
			add_action('manage_' . $type->post_type . '_posts_custom_column', array($type->admin, 'post_column'), 10, 2);

			add_filter('views_edit-' . $original_post_type, array($type->admin, 'filter_status_buckets'));

		}

		add_submenu_page(null, null, null, 'edit_pages', 'bu_create_revision', array('BU_Version_Controller', 'create_revision_view'));

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
					$version = new BU_Version($post);
					printf('<div class="notice"><h3>This is a pending edit to an <a href="%s">existing %s</a>.</h3></div>', $original->singular_name, $version->get_original_edit_url());
				} else {
					$versions = BU_Version_Controller::get_versions($post_id);
					if(is_array($versions) && !empty($versions)) {
						printf('<div class="notice"><h3>There is an alternate version for this page. <a href="%s">Edit</a></h3></div>', get_edit_post_link($versions[0]->ID));
					}
				}

			}
		}
	}

	function show_preview($post) {
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
			'capability_type' => array('page_revision', 'page_revisions'),
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

	function get($post_type) {
		if(is_array($this->v_post_types)  && array_key_exists($post_type, $this->v_post_types)) {
			return $this->v_post_types[$post_type];
		} else {
			return null;
		}
	}

	function get_alt_versions() {
		return array_keys($this->v_post_types);
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
		$this->post_type = register_post_type($post_type, $args);
		$this->orig_post_type = get_post_type_object($orig_post_type);

		if(is_admin()) {
			$this->admin = new BU_Version_Manager_Admin($this->post_type);
		}

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
			$versions[] = new BU_Version($orig_post_id);
		}

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

	function posts_columns($columns) {

		$insertion_point = 3;
		$i = 1;

		foreach($columns as $key => $value) {
			if($i == $insertion_point) {
				$new_columns['alternate_versions'] = 'Alternate Versions';
			}
			$new_columns[$key] = $columns[$key];
			$i++;
		}

		return $new_columns;
	}

	function post_column($column_name, $post_id) {
		if($column_name != 'pending_edit') return;
		$revision_id = get_post_meta($post_id, '_bu_revision', true);
		if(!empty($revision_id)) {
			$revision = new BU_Version($revisions_id);
			printf('<a href="%s" title="%s">edit</a>', get_edit_post_link($revision_id, true), esc_attr(__( 'Edit this item')));
			print(" | ");
			printf('<a href="%s" title="%s">view</a>', $revision->get_preview_URL($revision_id), esc_attr(__('Preview this edit')));
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
		$url = 'admin.php?page=bu_create_revision';
		$url = add_query_arg(array('post_type' => $post->post_type, 'post' => $post->ID), $url);
		return wp_nonce_url($url, 'create_revision');
	}

	function publish_revision($new_status, $old_status, $post) {

		if($new_status === 'publish' && $old_status !== 'publish') {
			$version = new BU_Version();
			$version->get($post->ID);
			$version->publish();
			// Is this the appropriate spot for a redirect?
			wp_redirect($version->get_original_edit_url());
			exit;
		}
	}
	/**
	 * Redirect page_revision previews to the orginal page, but with a specific
	 * parameter included that triggers the content to be replaced with the data
	 * from the new version.
	 */
	static function redirect_preview() {
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

	// GET handler used to create a revision
	function load_create_revision() {
		if(wp_verify_nonce($_GET['_wpnonce'], 'create_version')) {
			$post_id = (int) $_GET['post'];

			$post = (array) get_post($post_id);


			// need to check against all post types that have published versions enabled.
			if($post['post_type'] != 'page') return;


			// need to figure out how to fail best.
			//if(!current_user_can('')) return;


			$version = new BU_Version();

			$new_version['post_type'] = 'page_revision';
			$new_version['post_parent'] = $post['ID'];
			$new_version['ID'] = null;
			$new_version['post_status'] = 'draft';
			$new_version['post_content'] = $post['post_content'];
			$new_version['post_name'] = $post['post_name'];
			$new_version['post_title'] = $post['post_title'];
			$new_version['post_excerpt'] = $post['post_excerpt'];
			$id = wp_insert_post($new_version);

			update_post_meta($post['ID'], '_bu_revision', $id);

			$redirect_url = add_query_arg(array('post' => $id, 'post_type' => 'page_revision', 'action' => 'edit'), 'post.php');
			wp_redirect($redirect_url);
			exit();
		}
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


	function get(version_$id) {
		$this->post = get_post($id);
		$this->original = get_post($this->post->post_parent);
	}

	function create($post, $alt_post_type) {
		$this->original = $post;
		$new_version['post_type'] = $alt_post_type;
		$new_version['post_parent'] = $this->original['ID'];
		$new_version['ID'] = null;
		$new_version['post_status'] = 'draft';
		$new_version['post_content'] = $this->original['post_content'];
		$new_version['post_name'] = $this->original['post_name'];
		$new_version['post_title'] = $this->original['post_title'];
		$new_version['post_excerpt'] = $this->original['post_excerpt'];
		$id = wp_insert_post($new_version);

		update_post_meta($post['ID'], '_bu_version', $id);

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

	function get_original_edit_url() {
		return get_edit_post_link($this->original->ID, 'redirect');
	}

	function get_preview_URL() {
		if(!isset($this->original) || !isset($this->post)) return null;

		$permalink = get_permalink($this->post);
		$url = add_query_arg(array('version_id' => $this->post->ID, 'preview'=> 'true', 'p' => $this->post->post_parent, 'post_type' => $this->original->post_type), $permalink);
		return $url;
	}
}


?>
