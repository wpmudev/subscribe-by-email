jQuery(document).ready(function($) {
	'use strict';

	var sbe_shortcode = {
		init: function() {
			if ( $( '.sbe-shortcode-error' ).length ) {
				$('html,body').animate({
			        scrollTop: $("#sbe-shortcode-subscribe-form").offset().top - 20
			    }, 'slow' );
			}

			var updated = $( '#sbe-shortcode-updated' );
			if ( updated.length ) {
				$('html,body').animate({
			        scrollTop: updated.first().offset().top - updated.first().outerHeight()
			    }, 'slow' );
			}
		}
	};

	sbe_shortcode.init();
});
