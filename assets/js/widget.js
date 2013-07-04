jQuery(document).ready(function($) {
	$( '.subscribe-by-email-loader' ).hide();
	var sbe_already_submitted = false;
	$( '#subscribe-by-email-subscribe-form' ).submit( function() {
		$( '.subscribe-by-email-loader' ).show();
		$( 'input[name="submit-subscribe-user"]').attr( 'disabled', true );

		if ( sbe_already_submitted ) {
			$( '.subscribe-by-email-loader' ).hide();
			return false;
		}
		
		var data = $(this).serialize();
		$.post( sbe_localized.ajaxurl, data, function(response) {
			if ( 'MAIL ERROR' === response ) {
				$( '.subscribe-by-email-updated' ).hide();
				$( '.subscribe-by-email-error' ).slideDown();
				$( 'input[name="submit-subscribe-user"]').attr( 'disabled', false );
			}
			else {
				$( '.subscribe-by-email-error' ).hide();
				$( '.subscribe-by-email-updated' ).slideDown();
				sbe_already_submitted = true;
			}
			$( '.subscribe-by-email-loader' ).hide();

		});

		

		return false;
	});
});