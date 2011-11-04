<?php

class BU_Edit_Groups {

	public $option_name = 'section_groups';
	public $groups = array();


	public function add(BU_Edit_Group $group) {
		if(!isset($group->id)) {
			$id = array_push($arrayarray, $var);
			$group->set_id($id);
		}
	}

	public function get($id) {
		if(isset($groups[$id])) {
			return $groups[$id];
		}
	}


	// add a function to call method on the group?


	/**
	 * should load at init?
	 */
	public function load() {
		$groups = get_option($this->option_name);

		// load groups
	}

	public function update() {
		update_option($this->option_name, $groups);
	}

	public function delete() {
		delete_option($this->option_name);
	}

}

// class for listing groups (designed to be extended)


class BU_Groups_List {

	function __contruct(BU_Edit_Groups $groups) {

	}

	function have_groups() {

	}

	function the_group() {
		
	}

}


class BU_Edit_Group {
	protected $id;
	public $users;
	public $description;
	public $name;

	function __construct(BU_Edit_Groups $groups) {

	}

	function has_user($user_id) {
		return in_array($user_id, $users);
	}

	function add_user($user_id) {
		if(!$this->has_user($user_id) && is_user_member_of_blog($user_id)) {
			array_push($this->users, $user_id);
		}
	}

	function remove_user($user_id) {
		if($this->have_user($user_id)) {
			unset($this->users[array_search($user_id, $this->users)]);
		}
	}

	function create($name, $description = '') {
		$this->name = $name;
		$this->description = $description;
	}

	function set_id($id) {
		$this->id = $id;
	}
}

?>
