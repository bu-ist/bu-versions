jQuery(function($) {
	var notice = $('.bu-version-notice').remove();
	$('#wpbody-content > .wrap > h2').after(notice);
	$('.bu-version-notice').show();

	var $button = $('.bu_alt_postedit #publish');
	if($button.val() == 'Publish') {
		$button.val('Replace Original');
	}
});
