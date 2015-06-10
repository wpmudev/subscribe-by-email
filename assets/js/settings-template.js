jQuery(document).ready(function($) {
	var frame;
	var sbe_settings = {
		init: function() {
			
			$( '#upload-logo' ).on( 'click', sbe_settings.upload_logo );

			if ( $( '#upload-logo-value' ).val() == '' )
				$( '#remove-logo-button' ).hide();
return;
			$('#header-color-picker').farbtastic('#header-color')
			$('#header-color').click( sbe_settings.toggle_colorpicker );

			$('#header-text-color-picker').farbtastic('#header-text-color')
			$('#header-text-color').click( sbe_settings.toggle_colorpicker );


			$(document).mouseup(function (e) {
			    var container = $(".colorpicker");

			    if (container.has(e.target).length === 0) {
			        container.hide();
			    }
			});
		},
		upload_logo: function( e ) {
			e.preventDefault();

			var element = $(this);			

			if ( frame ) {
		    	frame.open();
		      	return;
		    }

		    frame = wp.media.frames.sbeTemplateLogo = wp.media({
				title: element.data( 'frame-title' ),
				library: {
					type: 'image'
				},
				button: {
					text: element.data( 'frame-update' ),
					close: false
				}
			});

			frame.on( 'select', function() {
				// Grab the selected attachment.
				var attachment = frame.state().get('selection').first();
				$( '#sbe-logo-img' )
					.attr( 'src', attachment.attributes.url )
					.css( 'display', 'inline-block' );

				$( '#upload-logo-value' ).attr( 'value', attachment.attributes.url );
				frame.close();
			});

			frame.open();
		},
		toggle_colorpicker: function( e ) {
			var id = $(this).attr('id');
			$( '#' + id + '-picker' ).show();
		}
	}

	sbe_settings.init();
});
