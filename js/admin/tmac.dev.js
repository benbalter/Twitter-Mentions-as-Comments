jQuery(document).ready(function($){

	if ( pagenow && pagenow == 'settings_page_tmac_options' ) {

		//cron details
		$('#cron-details').siblings('input').click(function(){ 
 			if ( $(this).val() == 1)
   				$('#cron-details').slideDown();
   			else 
   				$('#cron-details').slideUp();
		});	
		
		if ( twitter_mentions_as_comments.hide_manual_cron_details ) {
    		$('#cron-details').hide();
    	}
		
		//donate button
		$('#hide-donate').click( function(event){
			$.ajax({
				url: 'admin-ajax.php?action=tmac_hide_donate&_ajax_nonce-tmac-hide-donate=' + $('#_ajax_nonce-tmac-hide-donate').val(),
				success: function() {
					$('#donate').fadeOut();
				}
			});
			event.preventDefault();
			return false;
		});
	}
	
});

