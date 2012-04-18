<?php

if(!defined('TEST_WP')) return;

class Test_BU_Versions extends WPTestCase {

	function setUp() {
		parent::setUp();
	}

	function tearDown() {
		parent::tearDown();
	}

	function test_create_version() {
		list($original_post, $version_post) = $this->create_version();

		$this->assertEquals($version_post->post_type, 'page_revision');
		$this->assertEquals($version_post->post_author, $original_post->post_author);
		$this->assertEquals($version_post->post_parent, $original_post->post_parent);
		$this->assertEquals($version_post->post_title, $original_post->post_title);
		$this->assertEquals($version_post->post_content, $original_post->post_content);

	}

	function test_publish_version() {

		list($original_post, $version_post) = $this->create_version();

		$new_content = 'new content';
		$version_post->post_content = $new_content;
		wp_update_post((array) $version_post);

		$version =  new BU_Revision($version_post->ID);

		$version->publish();

		$new_original = get_post($version_post->post_parent);

		$this->assertEquals($new_original->post_content, $new_content);

		$old_version = get_post($version_post->ID);

		$this->assertFalse($old_version);

	}

	function create_version() {
		$this->_insert_quick_posts(1, 'page');
		$post_id = end($this->post_ids);

		$original_post = get_post($post_id);

		$version = new BU_Revision($original_post);

		$id = $version->create();

		$version_post = get_post($id);

		return array($original_post, $version_post);
	}

}

?>
