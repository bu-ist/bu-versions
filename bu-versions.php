<?php

/*
 Plugin Name: BU Versions
 Description: Make and review edits to published content.
 Version: 0.1
 Author: Boston University (IS&T)
*/

// need to find a home for the revision listing (I think?)
// need to hijack the publishing of a new version and redirect back to the original page


// add action link to the page listing
// add a column that lists active
// add column to page revision listing that lists the parent.


class BU_Version_Workflow {

	static function init() {
		self::register_post_types();
		add_action('do_meta_boxes', array('BU_Version_Workflow', 'register_meta_boxes'), 10, 3);
		add_action('admin_menu', array('BU_Version_Workflow', 'admin_menu'));
		add_action('load-admin_page_bu_create_revision', array('BU_Revision_Controller', 'load_create_revision'));
		add_action('transition_post_status', array('BU_Revision_Controller', 'publish_revision'), 10, 3);
		add_filter('the_preview', array('BU_Version_Workflow', 'show_preview'), 12); // needs to come after the regular preview filter
		add_filter('template_redirect', array('BU_Version_Workflow', 'redirect_preview'));

		add_rewrite_tag('%revision%', '[^&]+'); // bring the revision id variable to life
	}


	static function admin_menu() {

		// need cap for creating revision
		add_submenu_page(null, null, null, 'edit_pages', 'bu_create_revision', array('BU_Revision_Controller', 'create_revision_view'));
	}

	static function register_post_types() {

		$labels = array(
			'name' => _x('Page Revisions', 'post type general name'),
			'singular_name' => _x('Page Revision', 'post type singular name'),
			'add_new' => _x('Add New', ''),
			'add_new_item' => __('Add New Page Revision'),
			'edit_item' => __('Edit Page Revision'),
			'new_item' => __('New Page Revisions'),
			'view_item' => __('View Page Revision'),
			'search_items' => __('Search Page Revisions'),
			'not_found' =>  __('No Page Revisions found'),
			'not_found_in_trash' => __('No Page Revisions found in Trash'),
			'parent_item_colon' => '',
			'menu_name' => 'Page Revisions'
		);

		$args = array(
			'labels' => $labels,
			'description' => '',
			'publicly_queryable' => true,
			'exclude_from_search' => false,
			'capability_type' => 'page',
			'capabilities' => array(), // need to figure out the capabilities piece
			'map_meta_cap' => null,
			'hierarchical' => false,
			'rewrite' => false,
			'has_archive' => false,
			'query_var' => true,
			'supports' => array(), // copy support from the post_type
			'taxonomies' => array(), // leave taxonomies to last
			'show_ui' => true,
			'menu_position' => null,
			'menu_icon' => null,
			'permalink_epmask' => EP_PERMALINK,
			'can_export' => true,
			'show_in_nav_menus' => false,
			'show_in_menu' => true,  // Change to false once we get stuff setup right.
		);

		register_post_type('page_revision', $args);
	}

	static function register_meta_boxes($post_type, $position, $post) {
		add_meta_box('bu_new_version', 'New Version', array('BU_Version_Workflow', 'new_version_meta_box'), 'page', 'side', 'high');

	}

	static function new_version_meta_box($post) {
		$original_post = $GLOBALS['post']; //need to be able to restore the global

		$url = BU_Revision_Controller::get_URL($post);
		$versions = new WP_Query(array('post_type' => 'page_revision', 'post_parent' => $post->ID, 'nopaging' => true));
		include('interface/page-edits.php');

		$GLOBALS['post'] = $original_post;
	}

	static function redirect_preview() {
		if(is_preview() && is_singular('page_revision')) {
			$request = strtolower(trim($_SERVER['REQUEST_URI']));
			$request = preg_replace('#\?.*$#', '', $request);
			$revision_id = (int) get_query_var('p');
			$revision = get_post($revision_id);
			$url = add_query_arg(array('revision' => $revision->ID, 'preview'=> 'true', 'p' => $revision->post_parent, 'post_type' => 'page'), $request);

			wp_redirect($url, 302);
			exit();
		}
	}


	static function show_preview($post) {
		if ( ! is_object($post) )
			return $post;

		$revision_id = (int) get_query_var('revision');

		$preview = wp_get_post_autosave($revision_id);
		if ( ! is_object($preview) ) {
			$preview = get_post($revision_id);
			if( !is_object($preview)) return $post;
		}

		$preview = sanitize_post($preview);

		$post->post_content = $preview->post_content;
		$post->post_title = $preview->post_title;
		$post->post_excerpt = $preview->post_excerpt;

		return $post;

	}
}

add_action('init', array('BU_Version_Workflow', 'init'));



class BU_Revision_Controller {

	static function get_URL($post) {
		$url = 'admin.php?page=bu_create_revision';
		$url = add_query_arg(array('post_type' => $post->post_type, 'post' => $post->ID), $url);
		return wp_nonce_url($url, 'create_revision');
	}

	static function publish_revision($new_status, $old_status, $post) {

		if($post->post_type != 'page_revision') return;

		if($new_status === 'publish' && $old_status !== 'publish') {
			$revision = new BU_Revision($post);
			$revision->publish();
			// Is this the appropriate spot for a redirect?
			wp_redirect($revision->get_original_edit_url());
			exit;
		}

	}


	// GET handler used to create a revision
	static function load_create_revision() {
		if(wp_verify_nonce($_GET['_wpnonce'], 'create_revision')) {
			$post_id = (int) $_GET['post'];

			$post = (array) get_post($post_id);

			// need to check against all post types that have published versions enabled.
			if($post['post_type'] != 'page') return;


			$new_version['post_type'] = 'page_revision';
			$new_version['post_parent'] = $post['ID'];
			$new_version['ID'] = null;
			$new_version['post_status'] = 'draft';
			$new_version['post_content'] = $post['post_content'];
			$new_version['post_name'] = $post['post_name'];
			$new_version['post_title'] = $post['post_title'];
			$new_version['post_excerpt'] = $post['post_excerpt'];
			$id = wp_insert_post($new_version);

			$redirect_url = add_query_arg(array('post' => $id, 'post_type' => 'page_revision', 'action' => 'edit'), 'post.php');
			wp_redirect($redirect_url);
			exit();
		}
	}



	static function create_revision_view() {
		// no-op
	}

}
// @see _set_preview() -- WordPress uses the autosave to generate previews. We can use
// the same approach when overriding the display of a page.

class BU_Revision {

	function __construct($post) {
		$this->new_version = $post;
		$this->original = get_post($this->new_version->post_parent);
	}

	function create() {

	}

	function publish() {
		$post = array();
		$post['ID'] = $this->original->ID;
		$post['post_title'] = $this->new_version->post_title;
		$post['post_content'] = $this->new_version->post_content;
		$post['post_excerpt'] = $this->new_version->post_excerpt;
		wp_update_post($post);
		wp_delete_post($this->new_version->ID);
	}

	function get_original_edit_url() {
		return get_edit_post_link($this->original->ID, 'redirect');
	}

}



class BU_Revision_Workflow_Admin {



}



?>
