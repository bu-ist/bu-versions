<?php

/**
 * WP Importer creates new IDs for imported content. The saved version post IDs
 * in parent post's _bu_version meta becomes incorrect. This class rewrites the
 * meta after the WP Importer finishes.
 *
 * @todo Support partial imports in WP Importer v2.
 */

class BU_Version_Import {

	public $v_factory;
	protected $post_ids = array();

	function __construct( $v_factory ) {
		$this->v_factory = $v_factory;
	}

	function bind_hooks() {
		add_action( 'wp_import_insert_post', array( $this, 'log_post_ids' ), 10, 4 );
		add_action( 'import_end', array( $this, 'fix_version_post_ids' ), 10, 4 );
	}

	function log_post_ids( $post_id, $original_id, $postdata, $data ) {
		$this->post_ids[ $original_id ] = $post_id;
	}

	/**
	 * At the import end, rewrite old post IDs in postmeta to point at newer post IDs.
	 * @return null
	 */
	function fix_version_post_ids() {
		global $wpdb;

		$version_query = $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", BU_Version_Workflow::version_meta );
		$version_data = $wpdb->get_results( $version_query, ARRAY_A );

		foreach ( $version_data as $data ) {
			if ( ! empty( $this->post_ids[ $data['meta_value'] ] ) ) {

				$new_post_id = $this->post_ids[ $data['meta_value'] ];
				update_post_meta( $data['post_id'], BU_Version_Workflow::version_meta, $new_post_id );

				error_log( sprintf( '%s: Updating post id %s with version meta from %s to %s', __METHOD__, $data['post_id'], $data['meta_value'], $new_post_id ) );

			}
		}
	}
}
