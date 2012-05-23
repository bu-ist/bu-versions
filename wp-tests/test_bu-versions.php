<?php

if(!class_exists(WPTestCase)) return;

class Test_BU_Versions extends WPTestCase {

	function setUp() {
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

	function create_version() {
		$this->_insert_quick_posts(1, 'page');
		$post_id = end($this->post_ids);
		$original_post = get_post($post_id);

		$v_factory = BU_Version_Workflow::$v_factory;

		$v_page_manager = $v_factory->get_alt_manager('page');

		$version = $v_page_manager->create($post_id);

		return array($original_post, $version->post);
	}

}

?>
