<?php

/**
 * @group bu-versions
 */
class Test_BU_Versions_Meta extends WP_UnitTestCase {

	public $meta_key = '_awesome_foo_html';

	function setUp() {
		add_post_type_support('page', 'awesome_foo');
		add_filter('bu_alt_versions_feature_support', array($this, 'awesome_foo_alt_versions'), 10, 1);
		add_action('save_post', array($this, 'save_post_handler'), 10, 2);
		BU_Version_Workflow::$v_factory->register_post_types(); //needed because support is added so late
		parent::setUp();
	}

	function tearDown() {
		remove_post_type_support('page', 'awesome_foo');
		remove_filter('bu_alt_versions_feature_support', array($this, 'awesome_foo_alt_versions'), 10, 1);
		remove_action('save_post', array($this, 'save_post_handler'), 10, 2);
		unset($_POST['awesome_foo_html']);
		remove_all_filters('get_post_metadata');
		parent::tearDown();
	}

	function test_clone() {
		$content = '<p>Hello, World!</p>';
		$_POST['awesome_foo_html'] = $content;
		$page_id = $this->factory->post->create(array('post_type' => 'page'));
		$original_meta = get_post_meta($page_id, $this->meta_key, true);
		$this->assertEquals($content, $original_meta);
		unset($_POST['awesome_foo_html']);
		$v_factory = BU_Version_Workflow::$v_factory;
		$v_page_manager = $v_factory->get_alt_manager('page');

		$version = $v_page_manager->create($page_id);
		$alt_meta = get_post_meta($version->post->ID, $this->meta_key, true);
		$this->assertEquals($content, $alt_meta);
	}


	function test_overwrite() {
		$content = '<p>Hello, World!</p>';
		$_POST['awesome_foo_html'] = $content;
		$page_id = $this->factory->post->create(array('post_type' => 'page'));
		$original_meta = get_post_meta($page_id, $this->meta_key, true);
		unset($_POST['awesome_foo_html']);

		$v_factory = BU_Version_Workflow::$v_factory;
		$v_page_manager = $v_factory->get_alt_manager('page');
		$version = $v_page_manager->create($page_id);
		
		$new_meta = '<p>Yep.</p>';
		$_POST['awesome_foo_html'] = $new_meta;
		$postdata = (array) $version->post;
		$postdata['post_content'] = "New post data";
		wp_update_post($postdata);
		unset($_POST['awesome_foo_html']);

		$v_page_manager->publish($version->post->ID);
		$alt_version_meta = get_post_meta($version->post->ID, $this->meta_key, true);
		$this->assertEmpty($alt_version_meta);
		$updated_meta = get_post_meta($page_id, $this->meta_key, true);
		$this->assertEquals($new_meta, $updated_meta);
	}

	function test_preview() {
		$content = '<p>Hello, World!</p>';
		$_POST['awesome_foo_html'] = $content;
		$page_id = $this->factory->post->create(array('post_type' => 'page'));
		$original_meta = get_post_meta($page_id, $this->meta_key, true);
		unset($_POST['awesome_foo_html']);

		$v_factory = BU_Version_Workflow::$v_factory;
		$v_page_manager = $v_factory->get_alt_manager('page');
		$version = $v_page_manager->create($page_id);
		
		$new_meta = '<p>Yep.</p>';
		$_POST['awesome_foo_html'] = $new_meta;
		$postdata = (array) $version->post;
		$postdata['post_content'] = "New post data";
		wp_update_post($postdata);
		unset($_POST['awesome_foo_html']);
		
		// funky set up for testing the overriding of meta that happens during a 
		// preview
		$_GET['version_id'] = $version->post->ID;	
		query_posts(array('pageid' => $page_id, 'preview' => true));
		BU_Version_Workflow::$controller->override_meta();
		
		$preview_meta = get_post_meta($page_id, $this->meta_key, true);

		$this->assertEquals($new_meta, $preview_meta);
	}


	function awesome_foo_alt_versions($features) {
		$features['awesome_foo'] = array(
			$this->meta_key
		);
		return $features;
	}

	function save_post_handler($post_id, $post) {
		if ($post->post_type == 'revision') {
			return;
		}

		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (defined('DOING_AJAX') && DOING_AJAX)) {
			return;
		}
		
		$html = trim($_POST['awesome_foo_html']);
		update_post_meta($post_id, $this->meta_key, $html);		
	}
}
