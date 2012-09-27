jQuery(function($) {
	var confirmed = false;
	
	var notice = $('.bu-version-notice').remove();
	$('#wpbody-content > .wrap > h2').after(notice);
	$('.bu-version-notice').show();

	var $button = $('.bu_alt_postedit #publish');
	if($button.val() == 'Publish') {
		$button.val('Replace Original');
	}

	$('.bu_alt_postedit #post').submit(function(e) {
		if( confirmed == true ) return;

		// need to add check to see if this is an attempt to "Save Draft"
		var $dialog = $('#bu-versions-confirm').dialog({
			title: 'Replace Original: Confirmation',
			resizable: false,
			height: 300,
			modal: true,
			autoOpen: false,
			buttons: {
				"Yes": function() {
					$(this).dialog("close");
					confirmed = true;
					// since the form is being submitted via the javascript
					// call, we need to add an input to communicate to WordPress
					// that this is a publish action
					$('.bu_alt_postedit #post').append('<input type="hidden" name="publish" value="Publish" />');
					$('.bu_alt_postedit #post').submit();
				},
				"No": function() {
					$(this).dialog("close");
				}
			}

		});
		var post_id = $(this).find('[name="post_ID"]').val();
		$.get(ajaxurl, {'action': 'bu_versions_has_changed', 'post_id': post_id}, function(data) {
			if( ! data.changed) {
				$dialog.find('.changed').hide();
			}
			$dialog.dialog('open');
		});
		e.preventDefault();
	});
});
