// iPad/iPhone/iPodTouch fix for labels
// label[for] give focus to their input[id] on mobile safari
// https://gist.github.com/545079
if (navigator.userAgent.match(/iPhone/i) || navigator.userAgent.match(/iPod/i) || navigator.userAgent.match(/iPad/i)) {
	$(document).ready(function () {
		$('label[for]').click(function () {
			var el = $(this).attr('for');
			if ($('#' + el + '[type=radio], #' + el + '[type=checkbox]').attr('selected', !$('#' + el).attr('selected'))) {
				return;
			} else {
				$('#' + el)[0].focus();
			}
		});
	});
}
