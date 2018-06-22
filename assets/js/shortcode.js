jQuery(document).ready(function($) {
	'use strict';

	var sbe_shortcode = {
		init: function() {
			if ( $( '.sbe-shortcode-error' ).length ) {
				$('html,body').animate({
			        scrollTop: $("#sbe-shortcode-subscribe-form").offset().top - 20
			    }, 'slow' );
				setTimeout(function() {
					$('.sbe-shortcode-error').attr('aria-live', 'assertive');
					$('.sbe-shortcode-single-error').first().attr('tabindex', '-1').focus();
				}, 500);
			}

			var updated = $( '#sbe-shortcode-updated' );
			if ( updated.length ) {
				$('html,body').animate({
			        scrollTop: updated.first().offset().top - updated.first().outerHeight()
			    }, 'slow' );
				setTimeout(function() {
					$(updated).find('p[tabindex="-1"]').first().attr('aria-live', 'assertive').focus();
				}, 500);
			}
		}
	};

	sbe_shortcode.init();
});
