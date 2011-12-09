<?php

/*
 Plugin Name: BU Versions
 Description: Make and review edits to published content.
 Version: 0.1
 Author: Boston University (IS&T)
*/

// add action link to the page listing
// add a column that lists active
// add column to page revision listing that lists the parent.

/**
 * consider removing the ability to create multiple versions. focus one version to add value.
 * add box for controlling which page the page replaces.
 *
 * need three screens:
 *	1) New Group
 *  2) Groups
 *  3) Edit Group
 *
 * --- Groups ---
 *
 * 1) Users added / removed / role changed
 * 2) Role removed
 * 3) Groups created, updated, deleted
 * 4) Group needs unique ID
 *
 *
 * Each group is a post in a custom post type..
 *
 * Get groups. (WP_Query)
 *
 * Build page tree with groups attached.
 *
 * Groups get attached to pages via postmeta.
 * Two meta_keys _bu_edit_group
 *
 * What is the best approach for checking ACL for all ancestors?
 *
 * New Page Revision has to be dealt with. (perhaps with a css+js hack)
 * or/ can the page listing + revision listing combined into a single view.
 * -- there could be a way to attach a draft to a page
 *
 *
 * Perhaps alternate version should be captured as postmeta, too?
 *
 * Try adding a column for the new version.
 *
 */

/// $views = apply_filters( 'views_' . $screen->id, $views );  // can be used to filter the views (All | Drafts | etc...


// $check = apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, $single );


require_once('classes.groups.php');
require_once('admin.groups.php');


class BU_Version_Workflow {

	static function init() {
		global $bu_edit_groups;

		self::register_post_types();

		add_action('do_meta_boxes', array('BU_Version_Workflow', 'register_meta_boxes'), 10, 3);
		add_action('admin_menu', array('BU_Version_Workflow', 'admin_menu'));
		add_action('load-admin_page_bu_create_revision', array('BU_Revision_Controller', 'load_create_revision'));
		add_action('transition_post_status', array('BU_Revision_Controller', 'publish_revision'), 10, 3);
		add_filter('the_preview', array('BU_Version_Workflow', 'show_preview'), 12); // needs to come after the regular preview filter
		add_filter('template_redirect', array('BU_Version_Workflow', 'redirect_preview'));
		add_filter('parent_file', array('BU_Version_Workflow', 'parent_file'));

		add_rewrite_tag('%revision%', '[^&]+'); // bring the revision id variable to life

		add_filter('map_meta_cap', array('BU_Section_Editor', 'map_meta_cap'), 10, 4);

		BU_Version_Roles::maybe_create();


	}


	static function admin_menu() {

		// need cap for creating revision
		add_submenu_page(null, null, null, 'edit_pages', 'bu_create_revision', array('BU_Revision_Controller', 'create_revision_view'));
		add_pages_page(null, 'Pending Edits', 'edit_pages', 'edit.php?post_type=page_revision');
		$hook = add_users_page('Edit Groups', 'Edit Groups', 'promote_users', 'manage_groups', array('BU_Groups_Admin', 'manage_groups_screen'));
		add_action('load-' . $hook, array('BU_Groups_Admin', 'load_manage_groups'), 1);

		$hook = add_users_page('Add New Group', 'Add New Group', 'promote_users', 'add_group', array('BU_Groups_Admin', 'add_group_screen'));
		add_action('load-' . $hook, array('BU_Groups_Admin', 'load_add_group'), 1);

		add_filter('manage_page_posts_columns', array('BU_Version_Workflow', 'page_posts_columns'));
		add_action('manage_page_posts_custom_column', array('BU_Version_Workflow', 'page_column'), 10, 2);
		add_filter('manage_page_revision_posts_columns', array('BU_Version_Workflow', 'page_revision_columns'));
		add_action('manage_page_revision_posts_custom_column', array('BU_Version_Workflow', 'page_revision_column'), 10, 2);
		add_filter('views_edit-page', array('BU_Version_Workflow', 'filter_page_status_buckets'));

	}

	static function register_post_types() {

		post_type_supports($post_type, $feature);

		$labels = array(
			'name' => _x('Page Edits', 'post type general name'),
			'singular_name' => _x('Page Edit', 'post type singular name'),
			'add_new' => _x('Add New', ''),
			'add_new_item' => __('Add New Edit'),
			'edit_item' => __('Pending Edit'),
			'new_item' => __('New'),
			'view_item' => __('View Page Edit'),
			'search_items' => __('Search Page Edits'),
			'not_found' =>  __('No Page Edits found'),
			'not_found_in_trash' => __('No Page Edits found in Trash'),
			'parent_item_colon' => '',
			'menu_name' => 'Page Edits'
		);

		$args = array(
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
			'taxonomies' => array(), // leave taxonomies to last
			'show_ui' => true,
			'show_in_menu' => false,
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
		add_meta_box('bu_new_version', 'Other Versions', array('BU_Version_Workflow', 'new_version_meta_box'), 'page', 'side', 'high');
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
			$url = BU_Revision_Controller::get_preview_URL($revision_id);
			wp_redirect($url, 302);
			exit();
		}
	}


	static function filter_page_status_buckets($views) {

		// need to handle counts
		$views['pending_edits'] = '<a href="edit.php?post_type=page_revision">Pending Edits</a>';
		return $views;
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

	static function page_revision_columns($columns) {

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

	static function page_revision_column($column_name, $post_id) {
		if($column_name != 'original_edit') return;
		$post = get_post($post_id);
		echo '<a href="' . get_edit_post_link( $post->post_parent, true ) . '" title="' . esc_attr( __( 'Edit this item' ) ) . '">' . __( 'edit' ) . '</a>';
	}
	static function page_posts_columns($columns) {

		$insertion_point = 3;
		$i = 1;

		foreach($columns as $key => $value) {
			if($i == $insertion_point) {
				$new_columns['pending_edit'] = 'Pending Edits';
			}
			$new_columns[$key] = $columns[$key];
			$i++;
		}

		return $new_columns;
	}

	static function page_column($column_name, $post_id) {
		if($column_name != 'pending_edit') return;
		$revision_id = get_post_meta($post_id, '_bu_revision', true);
		if(!empty($revision_id)) {
			printf('<a href="%s" title="%s">edit</a>', get_edit_post_link($revision_id, true), esc_attr(__( 'Edit this item')));
			print(" | ");
			printf('<a href="%s" title="%s">view</a>', BU_Revision_Controller::get_preview_URL($revision_id), esc_attr(__('Preview this edit')));
		} else {
			$post = get_post($post_id);
			if($post->post_status == 'publish') {
				printf('<a href="%s">create</a>', BU_Revision_Controller::get_URL($post));
			}
		}
	}


	static function parent_file($file) {
		if($file == 'edit.php?post_type=page_revision') {
			return 'edit.php?post_type=page';
		}
		return $file;
	}
}

add_action('init', array('BU_Version_Workflow', 'init'));


// add class that can be used for each post_type


class BU_Revision_Controller {

	static function get_URL($post) {
		$url = 'admin.php?page=bu_create_revision';
		$url = add_query_arg(array('post_type' => $post->post_type, 'post' => $post->ID), $url);
		return wp_nonce_url($url, 'create_revision');
	}

	static function get_preview_URL($revision_id) {
		$revision = get_post($revision_id);
		$permalink = get_permalink($revision);
		$url = add_query_arg(array('revision' => $revision->ID, 'preview'=> 'true', 'p' => $revision->post_parent, 'post_type' => 'page'), $permalink);
		return $url;
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

			update_post_meta($post['ID'], '_bu_revision', $id);

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

	/**
	 * @todo finish
	 **/
	function create() {
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

	}

	function publish() {
		$post = array();
		$post['ID'] = $this->original->ID;
		$post['post_title'] = $this->new_version->post_title;
		$post['post_content'] = $this->new_version->post_content;
		$post['post_excerpt'] = $this->new_version->post_excerpt;
		wp_update_post($post);
		wp_delete_post($this->new_version->ID);
		delete_post_meta($this->original->ID, '_bu_revision');
	}

	function get_original_edit_url() {
		return get_edit_post_link($this->original->ID, 'redirect');
	}

}

class BU_Version_Roles {

	// need to figure out the *best* way to create roles
	static public function maybe_create() {

		$role = get_role('administrator');

		if(empty($role)) {
			add_role('administrator', 'Administrator');
			include( ABSPATH . '/wp-admin/includes/schema.php');// hack to add all roles if they were deleted.
			populate_roles();
		}

		$role = get_role('administrator');

		$role->add_cap('read_page_revisions');
		$role->add_cap('edit_page_revisions');
		$role->add_cap('edit_others_page_revisions');
		$role->add_cap('edit_published_page_revisions');
		$role->add_cap('publish_page_revisions');
		$role->add_cap('delete_page_revisions');
		$role->add_cap('delete_others_page_revisions');
		$role->add_cap('delete_published_page_revisions');

		$role = get_role( 'lead_editor' );

		if(empty($role)) {
			add_role('lead_editor', 'Lead Editor');
		}

		$role = get_role('lead_editor');
		$role->add_cap('manage_training_manager');
		$role->add_cap('upload_files');
		$role->add_cap('edit_posts');
		$role->add_cap('read');
		$role->add_cap('delete_posts');

		$role->add_cap('moderate_comments');
		$role->add_cap('manage_categories');
		$role->add_cap('manage_links');
		$role->add_cap('upload_files');
		$role->add_cap('import');
		$role->add_cap('unfiltered_html');
		$role->add_cap('edit_posts');
		$role->add_cap('edit_others_posts');
		$role->add_cap('edit_published_posts');
		$role->add_cap('publish_posts');
		$role->add_cap('edit_pages');
		$role->add_cap('read');
		$role->add_cap('level_10');
		$role->add_cap('level_9');
		$role->add_cap('level_8');
		$role->add_cap('level_7');
		$role->add_cap('level_6');
		$role->add_cap('level_5');
		$role->add_cap('level_4');
		$role->add_cap('level_3');
		$role->add_cap('level_2');
		$role->add_cap('level_1');
		$role->add_cap('level_0');

		$role->add_cap('edit_others_pages');
		$role->add_cap('edit_published_pages');
		$role->add_cap('publish_pages');
		$role->add_cap('delete_pages');
		$role->add_cap('delete_others_pages');
		$role->add_cap('delete_published_pages');
		$role->add_cap('delete_posts');
		$role->add_cap('delete_others_posts');
		$role->add_cap('delete_published_posts');
		$role->add_cap('delete_private_posts');
		$role->add_cap('edit_private_posts');
		$role->add_cap('read_private_posts');
		$role->add_cap('delete_private_pages');
		$role->add_cap('edit_private_pages');
		$role->add_cap('read_private_pages');

		$role->add_cap('read_page_revisions');
		$role->add_cap('edit_page_revisions');
		$role->add_cap('edit_others_page_revisions');
		$role->add_cap('edit_published_page_revisions');
		$role->add_cap('publish_page_revisions');
		$role->add_cap('delete_page_revisions');
		$role->add_cap('delete_others_page_revisions');
		$role->add_cap('delete_published_page_revisions');


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

		$role->add_cap('read');
		$role->add_cap('edit_pages');
		$role->add_cap('edit_others_pages');

		// the following roles are overriden by the section editor functionality
		$role->add_cap('edit_published_pages');
		$role->add_cap('publish_pages');

		$role->add_cap('moderate_comments');
		$role->add_cap('manage_categories');
		$role->add_cap('manage_links');
		$role->add_cap('upload_files');
		$role->add_cap('edit_posts');
		$role->add_cap('read');
		$role->add_cap('level_7');
		$role->add_cap('level_6');
		$role->add_cap('level_5');
		$role->add_cap('level_4');
		$role->add_cap('level_3');
		$role->add_cap('level_2');
		$role->add_cap('level_1');
		$role->add_cap('level_0');
		$role->add_cap('edit_private_posts');
		$role->add_cap('read_private_posts');
		$role->add_cap('edit_private_pages');
		$role->add_cap('read_private_pages');

		$role->add_cap('read_page_revisions');
		$role->add_cap('edit_page_revisions');
		$role->add_cap('edit_others_page_revisions');
		$role->add_cap('edit_published_page_revisions');
		$role->add_cap('publish_page_revisions');
		$role->add_cap('delete_page_revisions');
		$role->add_cap('delete_others_page_revisions');
		$role->add_cap('delete_published_page_revisions');

		$role->add_cap('unfiltered_html');

				/** Temporary **/
		$role = get_role('contributor');
		if(empty($role)) {
			add_role('contributor', 'Contributor');
		}

		$role = get_role('contributor');
		$role->add_cap('manage_training_manager');
		$role->add_cap('upload_files');

		$role->add_cap('read');
		$role->add_cap('edit_pages');

		$role->add_cap('read_page_revisions');
		$role->add_cap('edit_page_revisions');
		$role->add_cap('edit_others_page_revisions');
		$role->add_cap('edit_published_page_revisions');
		$role->add_cap('delete_page_revisions');

		$role->add_cap('unfiltered_html');
	}

}

class BU_Section_Editor {


	static function can_edit($post_id, $user_id)  {

		if($user_id == 0) return false;

		$user = get_userdata($user_id);

		if($user && in_array('section_editor', $user->roles)) {
			$post = get_post($post_id, OBJECT, null);
			$groups = get_post_meta($post_id, 'bu_group');
			$edit_groups_o = BU_Edit_Groups::get_instance();
			if($edit_groups_o->has_user($groups, $user_id)) {
				return true;
			} else {
				$ancestors = get_post_ancestors($post);
				// iterate through ancestors; needs to be optimized
				foreach(array_reverse($ancestors) as $ancestor_id) {
					$groups = get_post_meta($ancestor_id, 'bu_group');
					if($edit_groups_o->has_user($groups, $user_id)) {
						return true;
					}
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Filter that modifies the caps based on the current state.
	 *
	 * @param type $caps
	 * @param type $cap
	 * @param type $user_id
	 * @param type $args
	 * @return string
	 */
	static function map_meta_cap($caps, $cap, $user_id, $args) {

		$post_id = $args[0];
		if($cap == 'edit_page') {
			$post = get_post($post_id);

			if($post_id && $post->post_status == 'publish' && !BU_Section_Editor::can_edit($post_id, $user_id)) {
				$caps = array('do_not_allow');
			}
		}

		if($cap == 'delete_page') {
			if($post_id && !BU_Section_Editor::can_edit($post_id, $user_id)) {
				$caps = array('do_not_allow');
			}
		}

		if($cap == 'publish_pages') {
			global $post_ID;

			$post_id = $post_ID;

			if($post_id && !BU_Section_Editor::can_edit($post_id, $user_id)) {
				$caps = array('do_not_allow');
			}
		}

		if($cap == 'publish_page_revisions') {
			global $post_ID;

			$post_id = $post_ID;

			$revision = get_post($post_id);

			if(!$revision || !BU_Section_Editor::can_edit($revision->post_parent, $user_id)) {
				$caps = array('do_not_allow');
			}
		}

		return $caps;
	}
}


?>
