jQuery(function($) {
	var notice = $('.notice').detach();
	$('.wrap h2').after(notice);
	notice.show();

	$('.bu_alt_postedit #publish').val('Replace Original');

});



