jQuery(document).ready(function($) {
	var sbe_settings = {
		init: function() {
			var freq_selector = $( '#frequency-select' );
			
			sbe_settings.toggle_fields( freq_selector.val() );

			freq_selector.change( sbe_settings.change_frequency );

		},
		change_frequency: function() {
			var value = $(this).val();
			sbe_settings.toggle_fields( value );
		},
		toggle_fields: function( value ) {
			var day_of_week_div = $( '#day-of-week-wrap' );
			var time_div = $( '#time-wrap' );

			switch ( value ) {
				case 'daily': {
					day_of_week_div.hide();
					time_div.fadeIn();
					break;
				}
				case 'weekly': {
					time_div.hide();
					day_of_week_div.fadeIn();
					break;
				}
				default: {
					day_of_week_div.hide();
					time_div.hide();
					break;
				}
			}
		}
	}

	sbe_settings.init();
});
