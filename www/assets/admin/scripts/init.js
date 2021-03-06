jQuery(document).ready(function () {
	Metronic.init(); // init metronic core componets
	Layout.init(); // init layout
	$.nette.init(); // https://github.com/vojtech-dobes/nette.ajax.js

	// Global components
	GlobalCustomInit.init();

	// components
	UIToastr.init();
	Fullscreen.init();
	AppContent.init();
	
	Buyout.init('[data-typeahead-url]');
	Newsletter.init();
});

$('.modal.ajax').on('loaded.bs.modal', function (e) {
	GlobalCustomInit.onReloadModalEvent();
});

$.nette.ext('netteAjax', {
	complete: function (payload) {
		for (i in payload.snippets) {
			switch (String(i)) {
				case 'snippet--flashMessages':
					break;
				default:
					GlobalCustomInit.onReloadGridoEvent();
					GlobalCustomInit.onReloadModalEvent();
					break;
			}
		}
	}
});

