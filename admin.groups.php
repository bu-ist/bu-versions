<?php


class BU_Groups_Admin {

	static $edit_groups = null;


	static function load_add_group() {

		if($_POST['action'] == 'add') {
			$groups = self::get_groups();

			$args = array(
				'name' => strip_tags(trim($_POST['name'])),
				'description' => strip_tags(trim($_POST['description'])),
				'users' => $_POST['users']
			);

			if(empty($args['name']) || empty($args['users'])) {
				// redirect back to add screen with an error.
				return;
			}
			// maybe use exceptions?
			$groups->add_group($args);
			$groups->update();
			// redirect to the edit group screen
		}

	}

	static function get_groups() {
		if(is_null(self::$edit_groups)) {
			self::$edit_groups = new BU_Edit_Groups();
			self::$edit_groups->load();
		}

		return self::$edit_groups;
	}

	static function add_group_screen() {
		//if(isset());
		include('interface/group-add.php');
	}

	static function load_manage_groups() {
		if($_POST['action'] == 'update') {

			$groups = self::get_groups();

			$args = array(
				'name' => strip_tags(trim($_POST['name'])),
				'description' => strip_tags(trim($_POST['description'])),
				'users' => $_POST['users']
			);

			if(empty($args['name']) || empty($args['users'])) {
				// redirect back to add screen with an error.
				return;
			}

			// maybe use exceptions?
			$groups->update_group((int) $_GET['id'], $args);
			$groups->update();

			// redirect to the edit group screen
		}


	}

	static function manage_groups_screen() {
		$groups = self::get_groups();
		if($_GET['action'] == 'edit' || $_POST['action'] == 'edit') {
			$group = $groups->get((int) $_GET['id']);
			include('interface/group-edit.php');

		} else {
			$group_list = new BU_Groups_List(self::$edit_groups);
			include('interface/groups.php');
		}
	}

	static function group_edit_url($id) {
		$url = 'users.php?page=manage_groups';

		$url = add_query_arg(array('action' => 'edit', 'id' => $id));

		return $url;

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

		if(empty($editors[0])) {
			delete_post_meta($post_id, '_bu_editors');
		} else {
			update_post_meta($post_id, '_bu_editors', $editors);
		}
	}


	static function user_checkboxes($current = array()) {

		$html = '';

		$users = get_users();
		foreach($users as $user) {
			$checked = '';
			if(in_array($user->ID, $current)) {
				$checked = ' checked="checked"';
			}

			$html .= sprintf('<label><input name="users[]" type="checkbox" value="%s"%s> %s</label>', $user->ID, $checked, $user->user_login);
		}
		echo $html;
	}
}


?>
