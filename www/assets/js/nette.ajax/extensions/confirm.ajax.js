(function ($, undefined) {

// $form->addSubmit('save', 'Save')->getControlPrototype()->addAttributes(['data-confirm' => 'Realy save?']);
// <a n:href="delete!" class="ajax" data-confirm="Realy delete?">Delete</a>

	$.nette.ext({
		before: function (xhr, settings) {
			var question = settings.nette.el.data('confirm');
			if (question) {
				return confirm(question);
			}
		}
	});

})(jQuery);
