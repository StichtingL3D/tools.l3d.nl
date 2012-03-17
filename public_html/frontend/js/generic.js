$(document).ready(function(){
	// reveal
	function revealElements() {
		$('.revealElement').each(function(){
			var revealElementLinkTitle = $(this).attr('title');
			var revealElementLink = $('<p><a class="revealElementLink" href="#show">'+ revealElementLinkTitle +'</a></p>');
			$(this).before(revealElementLink);
			$(this).hide();
			$(this).prev().click(function() {
				$(this).next().toggle(100);
			});
		});
	}
	revealElements();
});
