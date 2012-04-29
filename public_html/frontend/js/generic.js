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
	
	// reveal custom
	$('[data-reveal]').each(function(){
		var elementToReveal = $(this).attr('data-reveal');
		$('#' + elementToReveal).hide();
		$(this).click(function(event){
			event.preventDefault();
			$('#' + elementToReveal).toggle();
		});
		$('#' + elementToReveal + ' .box-close').click(function(event){
			event.preventDefault();
			$('#' + elementToReveal).hide();
		});
	});
});
