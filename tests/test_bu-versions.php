<?php

/**
 * @group bu-versions
 */
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

	function test_publish_version() {

		list($original_post, $alt_version) = $this->create_version();
		$version_edit_url = get_edit_post_link( $alt_version, null );
		$original_edit_url = get_edit_post_link( $original_post, null );

		$new_content = 'new content';
		$alt_version->post_content = $new_content;
		$alt_version->post_status = 'publish';
		wp_update_post((array) $alt_version);

		// Original content was overwritten
		$new_original = get_post($alt_version->post_parent);
		$this->assertEquals($new_original->post_content, $new_content);

		// Alternate version was deleted
		$old_version = get_post($alt_version->ID);
		$this->assertNull($old_version);

		// Redirect to original edit URL instead of alternate versions
		// @see redirect_post()
		$redirect_location = apply_filters( 'redirect_post_location', $version_edit_url, $alt_version->ID );
		$this->assertEquals($original_edit_url, $redirect_location);

	}


	function test_delete_original() {
		list($original_post, $alt_version) = $this->create_version();
		wp_delete_post($original_post->ID, true);
		$alt_post = get_post($alt_version->ID);

		$this->assertNull($alt_post);

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
