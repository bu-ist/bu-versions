jQuery(function($) {
	var notice = $('.notice').detach();
	$('.wrap h2').after(notice);
	notice.show();

	var $button = $('.bu_alt_postedit #publish');
	if($button.val() == 'Publish') {
		$button.val('Replace Original');
	}

});



