$(document).ready(function(){
	// reveal
	$('#upload-open a').click(function(){
		$('#upload-container').toggle(100);
		$(this).hide();
	});
	$('#upload-close a').click(function(){
		$('#upload-container').toggle(100);
		$('#upload-open a').show();
	});
});
