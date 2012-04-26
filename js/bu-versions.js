jQuery(function($) {
	var notice = $('.notice').detach();
	$('.wrap h2').after(notice);
	notice.show();
	console.log(notice);
});

