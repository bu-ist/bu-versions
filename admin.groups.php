<?php


class BU_Groups_Admin {



	static function load_add_group() {

		if($_POST['action'] == 'add') {
			$groups = BU_Edit_Groups::get_instance();

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


	static function add_group_screen() {
		//if(isset());
		include('interface/group-add.php');
	}

	static function load_manage_groups() {
		if($_POST['action'] == 'update') {

			$groups = BU_Edit_Groups::get_instance();

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

		$groups = BU_Edit_Groups::get_instance();
		if($_GET['action'] == 'edit' || $_POST['action'] == 'edit') {
			$group = $groups->get((int) $_GET['id']);
			include('interface/group-edit.php');

		} else {
			$group_list = new BU_Groups_List();
			include('interface/groups.php');
		}
	}

	static function group_edit_url($id) {
		$url = 'users.php?page=manage_groups';

		$url = add_query_arg(array('action' => 'edit', 'id' => $id));

		return $url;

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
