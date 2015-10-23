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

	function test_long_post_type(){
		register_post_type( 'suuuuperlongpostype', array( 'show_ui' => true ) );
		$this->assertTrue( post_type_exists( 'suuuuperlongpo_alt' ) );
	}

	function test_conflicting_long_post_types(){
		register_post_type( 'suuuuperlongpostype', array( 'show_ui' => true ) );
		register_post_type( 'suuuuperlongpo' );
		$this->assertTrue( post_type_exists( 'suuuuperlongpo1_alt' ) );

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
		// $alt_version->post_status = 'publish'; // this would exercise logic in transition_post_status hook
		wp_update_post((array) $alt_version);

		// TODO: This code succesfully publishes version, but does not trigger
		// version deletion (handled in transition_post_status callback)
		$version =  new BU_Version();
		$version->get($alt_version->ID);
		$version->publish();

		$new_original = get_post($alt_version->post_parent);
		$this->assertEquals($new_original->post_content, $new_content);

		// TODO: versions are marked for deletion in transition_post_status hook, but not actually
		// deleted until the shutdown handler so this test will always fail
		// $old_version = get_post($alt_version->ID);
		// $this->assertNull($old_version);

	}


	function test_delete_original() {
		list($original_post, $alt_version) = $this->create_version();
		wp_delete_post($original_post->ID, true);
		$alt_post = get_post($alt_version->ID);

		$this->assertNull($alt_post);

	}

	/**
	 * Demonstrates a bug that showed up in 4.0 due to a logic
	 * check added to `redirect_canonical`.
	 *
	 * The end result was that the preview link for alternate versions
	 * of the front page was breaking.
	 */
	function test_preview_version() {
		list($original_post, $alt_version) = $this->create_version();
		$version = new BU_Version;
		$version->get( $alt_version->ID );

		update_option('show_on_front', 'page');
		update_option('page_on_front', $original_post->ID);

		// Hack to ensure our custom query variable persists the cleanup
		// implicit in `$this->go_to()`
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'version_id';
			return $vars;
		} );

		$this->go_to( $version->get_preview_URL() );

		$redirect_url = @redirect_canonical( $version->get_preview_URL(), false );

		if ( version_compare( $GLOBALS['wp_version'], '4.0', '>=' ) ) {
			$this->assertFalse( $redirect_url );
		} else {
			$this->assertNull( $redirect_url );
		}
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
