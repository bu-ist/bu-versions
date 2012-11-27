jQuery(function($) {

	var overridePublishButton = function() {
		var $button = $('.bu_alt_postedit #publish');
		if($button.val() == 'Publish') {
			$button.val('Replace Original');
		}
	}

	var notice = $('.bu-version-notice').remove();
	$('#wpbody-content > .wrap > h2').after(notice);
	$('.bu-version-notice').show();
	overridePublishButton();

	if ( $('.bu_alt_postedit').length > 0 && typeof postL10n !== 'undefined' ) {
		// override the localization so that our text for the "Publish" button
		// is always used.
		postL10n['publish'] = 'Replace Original';
	}
});
