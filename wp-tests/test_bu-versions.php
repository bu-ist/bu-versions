<?php


class Test_BU_Versions extends WP_UnitTestCase {

	function setUp() {
		$user = get_user_by('login', 'admin');
		wp_set_current_user($user->ID);
		parent::setUp();
	}

	function tearDown() {
		parent::tearDown();
	}

	function test_create_version() {
		list($original_post, $version_post) = $this->create_version();

		$this->assertEquals($version_post->post_type, 'page_alt');
		$this->assertEquals($version_post->post_author, $original_post->post_author);
		$this->assertEquals($version_post->post_parent, $original_post->ID);
		$this->assertEquals($version_post->post_title, $original_post->post_title);
		$this->assertEquals($version_post->post_content, $original_post->post_content);

	}

	function test_update_version() {

	}

	function test_publish_version() {

		list($original_post, $alt_version) = $this->create_version();

		$new_content = 'new content';
		$alt_version->post_content = $new_content;
		wp_update_post((array) $alt_version);

		$version =  new BU_Version();
		$version->get($alt_version->ID);
		$version->publish();

		$new_original = get_post($alt_version->post_parent);

		$this->assertEquals($new_original->post_content, $new_content);

		$old_version = get_post($alt_version->ID);

		$this->assertNull($old_version);

	}


	function test_delete_original() {
		list($original_post, $alt_version) = $this->create_version();
		wp_delete_post($original_post->ID, true);
		$alt_post = get_post($alt_version->ID);

		$this->assertNull($alt_post);

	}

	function test_contributor_permissions() {

	}

	function create_version() {
		$post_id = $this->insert_post('page');

		$original_post = get_post($post_id);

		$v_factory = BU_Version_Workflow::$v_factory;

		$v_page_manager = $v_factory->get_alt_manager('page');

		$version = $v_page_manager->create($post_id);

		return array($original_post, $version->post);
	}

	function insert_post($type = 'page', $author = 'admin') {

			$user = get_user_by('login', $author);

			$post = array(
				'post_author' => $user->ID,
				'post_status' => 'publish',
				'post_title' => "{$type} title",
				'post_content' => "{$type} content",
				'post_excerpt' => "{$type} excerpt",
				'post_type' => $type
			);
			return wp_insert_post($post);

	}

}

?>
