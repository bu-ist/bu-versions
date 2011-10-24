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


// $check = apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, $single );


class BU_Version_Workflow {

	static function init() {
		self::register_post_types();
		add_action('do_meta_boxes', array('BU_Version_Workflow', 'register_meta_boxes'), 10, 3);
		add_action('admin_menu', array('BU_Version_Workflow', 'admin_menu'));
		add_action('load-admin_page_bu_create_revision', array('BU_Revision_Controller', 'load_create_revision'));
		add_action('transition_post_status', array('BU_Revision_Controller', 'publish_revision'), 10, 3);
		add_filter('the_preview', array('BU_Version_Workflow', 'show_preview'), 12); // needs to come after the regular preview filter
		add_filter('template_redirect', array('BU_Version_Workflow', 'redirect_preview'));
		add_filter('page_row_actions', array('BU_Version_Workflow', 'page_row_actions'), 10, 2);
		add_filter('parent_file', array('BU_Version_Workflow', 'parent_file'));

		add_rewrite_tag('%revision%', '[^&]+'); // bring the revision id variable to life

		//add_filter('get_post_metadata', 'BU_Version_Workflow')

		add_filter('map_meta_cap', array('BU_Section_Editor', 'map_meta_cap'), 10, 4);

		add_action('save_post', array('BU_Version_Workflow', 'save_editors'), 10, 2);

		BU_Version_Roles::maybe_create();
	}


	static function admin_menu() {

		// need cap for creating revision
		add_submenu_page(null, null, null, 'edit_pages', 'bu_create_revision', array('BU_Revision_Controller', 'create_revision_view'));
		add_pages_page(null, 'Pending Edits', 'edit_pages', 'edit.php?post_type=page_revision');
	}

	static function register_post_types() {

		post_type_supports($post_type, $feature);

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
			'capability_type' => 'page_revision',
			'capabilities' => array(), // need to figure out the capabilities piece
			'map_meta_cap' => null,
			'hierarchical' => false,
			'rewrite' => false,
			'has_archive' => false,
			'query_var' => true,
			'supports' => array('editor', 'title', 'author', 'revisions' ), // copy support from the post_type
			'taxonomies' => array(), // leave taxonomies to last
			'show_ui' => true,
			'menu_position' => null,
			'menu_icon' => null,
			'permalink_epmask' => EP_PERMALINK,
			'can_export' => true,
			'show_in_nav_menus' => false,
			'show_in_menu' => false,
		);

		register_post_type('page_revision', $args);
	}

	static function register_meta_boxes($post_type, $position, $post) {
		add_meta_box('bu_new_version', 'New Version', array('BU_Version_Workflow', 'new_version_meta_box'), 'page', 'side', 'high');
		add_meta_box('bu_editors', 'Section Editors', array('BU_Version_Workflow', 'editors_meta_box'), 'page', 'normal', 'high');
	}

	static function new_version_meta_box($post) {
		$original_post = $GLOBALS['post']; //need to be able to restore the global

		$url = BU_Revision_Controller::get_URL($post);
		$versions = new WP_Query(array('post_type' => 'page_revision', 'post_parent' => $post->ID, 'nopaging' => true));
		include('interface/page-edits.php');

		$GLOBALS['post'] = $original_post;
	}

	/**
	 * THIS IS TEMPORARY. MUST BE REPLACED.
	 */
	static function editors_meta_box($post) {
		$editors = get_post_meta($post->ID, '_bu_editors', true);
		if(!empty($editors)) {
			$editors_value = join(',', $editors);
		}

		printf('<input class="widefat" type="text" name="bu_editors" value="%s">', esc_attr($editors_value));

	}

	/**
	 * THIS IS TEMPORARY. MUST BE REPLACED.
	 */
	static function save_editors($post_id, $post) {
		if($post->post_type !== 'page') return;

		$editors = explode(',', $_POST['bu_editors']);

		if(empty($editors)) $editors = '';

		update_post_meta($post_id, '_bu_editors', $editors);
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

	static function page_row_actions($actions, $post) {
		if($post->post_status == 'publish') {
			$actions['new_edit'] = sprintf('<a href="%s">New Edit</a>', BU_Revision_Controller::get_URL($post));
		}
		return $actions;
	}

	static function parent_file($file) {
		if($file == 'edit.php?post_type=page_revision') {
			return 'edit.php?post_type=page';
		}
		return $file;
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

class BU_Version_Roles {

	// need to figure out the *best* way to create roles
	static public function maybe_create() {
		$role = get_role('administrator');

		$role->add_cap('edit_page_revision');
		$role->add_cap('edit_page_revisions');
		$role->add_cap('edit_others_page_revisions');
		$role->add_cap('edit_published_page_revisions');
		$role->add_cap('publish_page_revisions');
		$role->add_cap('edit_page_revision');
		$role->add_cap('delete_page_revisions');
		$role->add_cap('delete_others_page_revisions');
		$role->add_cap('delete_published_page_revisions');

		$role = get_role( 'lead_editor' );

		if(empty($role)) {
			add_role('lead_editor', 'Lead Editor');
		}

		$role = get_role('content_editor');
		$role->remove_cap('edit_published_pages');
		$role->add_cap('manage_training_manager');
		$role->add_cap('upload_files');
		$role->add_cap('edit_posts');
		$role->add_cap('read');
		$role->add_cap('delete_posts');
		$role->add_cap('read_private_posts');
		$role->add_cap('read_private_pages');
		$role->add_cap('unfiltered_html');


		/** Temporary **/
		$role = get_role('section_editor');
		if(empty($role)) {
			add_role('section_editor', 'Section Editor');
		}

		$role = get_role('section_editor');
		$role->add_cap('manage_training_manager');
		$role->add_cap('upload_files');

		// shouldn't be able to delete files
		$role->add_cap('read');
		$role->add_cap('edit_pages');
		$role->add_cap('edit_others_pages');
		$role->add_cap('edit_published_pages');
		$role->add_cap('publish_pages');
		$role->remove_cap('delete_pages');
		$role->remove_cap('delete_others_pages');
		$role->remove_cap('delete_published_pages');
		$role->remove_cap('delete_pages');
		$role->remove_cap('delete_others_pages');

		$role->add_cap('edit_private_posts');
		$role->add_cap('read_private_posts');
		$role->add_cap('edit_private_pages');
		$role->add_cap('read_private_pages');

		$role->add_cap('edit_page_revisions');

		$role->add_cap('edit_page_revision');
		$role->add_cap('delete_page_revisions');
		$role->add_cap('publish_page_revisions');

		$role->add_cap('unfiltered_html');
	}

}

class BU_Section_Editor {


	static function can_edit($post_id, $user_id)  {
		$user = get_userdata($user_id);
		if(in_array('section_editor', $user->roles)) {
			$post = get_post($post_id);
			$editors = (array) get_post_meta($post_id, '_bu_editors', true);
			if(in_array($user_id, $editors)) {
				return true;
			} else {
				return false;
			}
		}

		return true;
	}


	static function map_meta_cap($caps, $cap, $user_id, $args) {
		//var_dump($cap);

		$post_id = $args[0];
		if($cap == 'edit_page') {
			if($post_id && !BU_Section_Editor::can_edit($post_id, $user_id)) {
				$caps = array('do_not_allow');
			}
		}

		if($cap == 'delete_page') {
			if($post_id && !BU_Section_Editor::can_edit($post_id, $user_id)) {
				$caps = array('do_not_allow');
			}
		}

		if($cap == 'publish_pages') {
			if(!$post_id || !BU_Section_Editor::can_edit($post_id, $user_id)) {
				$caps = array('do_not_allow');
			}
		}

		if($cap == 'publish_page_revisions') {
			$revision = get_post($post_id);

			if(!$revision || !BU_Section_Editor::can_edit($revision->post_parent, $user_id)) {
				$caps = array('do_not_allow');
			}
		}
		if($cap == 'edit_page_revisioncd') {
			$revision = get_post($post_id);

			if(!$revision || ($revision->post_author != $user_id && !BU_Section_Editor::can_edit($revision->post_parent, $user_id))) {
				$caps = array('do_not_allow');
			}
		}

		return $caps;
	}
}


class BU_Content_Groups {

}


class BU_Revision_Workflow_Admin {



}



?>
