jQuery(document).ready(function($) {
	
	$('.image_attribution_tagger_upload').on('click', 'button.notice-dismiss', function(e) {
		$.post( ajaxurl, {
			action: "image_attribution_tagger_dismissible_notice",
			url: ajaxurl,
			nonce: image_attribution_tagger.nonce || ''
		});	
	});
	
});