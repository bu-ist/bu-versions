<?php

/**
 * @group bu-versions
 */
class Test_BU_Versions_Meta extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		add_post_type_support('page', 'awesome_foo');
		add_filter('bu_alt_versions_feature_support', array($this, 'awesome_foo_alt_versions'), 10, 1);
	}

	function tearDown() {
		parent::tearDown();
		remove_post_type_support('page', 'awesome_foo');
		remove_filter('bu_alt_versions_feature_support', array($this, 'awesome_foo_alt_versions'), 10, 1);
		unset($_POST['awesome_foo_html']);
	}

	function test_clone() {
		$content = '<p>Hello, World!</p>';
		$_POST['awesome_foo_html'] = $content;
		$page_id = $this->factory->post->create(array('post_type' => 'page'));
		$original_meta = get_post_meta($page_id, '_awesome_foo_html', true);
		$this->assertEquals($content, $original_meta);
		$v_factory = BU_Version_Workflow::$v_factory;
		$v_page_manager = $v_factory->get_alt_manager('page');

		$version = $v_page_manager->create($page_id);
		$alt_meta = get_post_meta($version->post->ID, '_awesome_foo_html', true);
		$this->assertEquals($content, $alt_meta);
	}


	function awesome_foo_alt_versions($features) {
		$features['awesome_foo'] = array(
			'_awesome_foo_html'
		);
		return $features;
	}

	function save_post_handler() {
		if ($post->post_type == 'revision') {
			return;
		}

		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (defined('DOING_AJAX') && DOING_AJAX)) {
			return;
		}
		
		$html = trim($_POST['awesome_foo_html']);

		update_post_meta('_awesome_foo_html', $html);		
	}
}
