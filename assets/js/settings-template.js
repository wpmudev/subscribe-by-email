jQuery(document).ready(function($) {
	var sbe_settings = {
		init: function() {
			
			$( '#upload-logo' ).click( sbe_settings.upload_logo );

			if ( $( '#upload-logo-value' ).val() == '' )
				$( '#remove-logo-button' ).hide();

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

			// We'll save the value on an hidden input
			var input = $( '#upload-logo-value' );
 	
 			// Opening media uploader
	        tb_show( sbe_captions.title_text, 'media-upload.php?type=image&TB_iframe=true&post_id=0', false );
	 
	        window.send_to_editor = function( html ) {
	        	// Action triggered when sending the button
	            var src = $( 'img', html ).attr( 'src' );

	            input.attr( 'value', src );
	            $( '#sbe-logo-img' ).attr( 'src', src );
	            tb_remove();
	        }
	        return false;
		},
		toggle_colorpicker: function( e ) {
			var id = $(this).attr('id');
			$( '#' + id + '-picker' ).show();
		}
	}

	sbe_settings.init();
});
