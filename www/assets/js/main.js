jQuery(document).ready(function () {

	// renderer settings
	LiveForm.options.errorMessageClass = 'help-block help-block-error';
	LiveForm.options.errorMessagePrefix = '';

        jQuery('#frm-products-filterForm').removeClass('in');
});

jQuery(document).on('click', '.alert-auto-dismiss', function() {
        jQuery(this).remove();
});

(function (d, s, id) {
	var js, fjs = d.getElementsByTagName(s)[0];
	if (d.getElementById(id))
		return;
	js = d.createElement(s);
	js.id = id;
	js.src = "//connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v2.4&appId=525298140851597";
	fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));


