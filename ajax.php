<?php


function bu_versions_ajax_has_changed() {
	$post_id = (int) $_POST['post_id'];

	$version = new BU_Version();

	$version->get( $post_id );

	$changed = $version->has_changed();

	$json = new StdClass;

	$json->changed = $changed;
	header('Content-type', 'application/json');
	echo json_encode( $json );
	exit;
}

