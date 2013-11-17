<?php
/**
 * PHPUnit Bootstrap
 *
 * To run these tests:
 * 1. Install PHPUnit (http://www.phpunit.de)
 * 2. Install WordPress from the core development repository (http://develop.svn.wordpress.org/trunk)
 * 3. Configure wp-tests-config.php, install WordPress and create a clean DB
 * 4. Set the WP_TESTS_DIR environment variable to point at develop.svn.wordpress.org working copy
 *
 * $ cd wp-content/plugins/bu-versions
 * $ phpunit
 */

require_once getenv( 'WP_TESTS_DIR' ) . '/tests/phpunit/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../bu-versions.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require getenv( 'WP_TESTS_DIR' ) . '/tests/phpunit/includes/bootstrap.php';


