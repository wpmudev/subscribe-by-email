jQuery(document).ready(function($) {
	var sbe_settings = {
		orig_send_attachment: wp.media.editor.send.attachment,

		init: function() {

			$( '#upload-logo' ).on( 'click', $.proxy( sbe_settings.upload_logo, this ) );


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

			var button = e.target;
 			
 			wp.media.editor.send.attachment = function(props, attachment){
				$( '#upload-logo-value' ).val(attachment.url);
				if ( attachment.url )
					$('#sbe-logo-img').attr('src',attachment.url);
		    }

		    wp.media.editor.open($(button));
		    $('.media-frame-menu').hide();
		    return false;

		},
		toggle_colorpicker: function( e ) {
			var id = $(this).attr('id');
			$( '#' + id + '-picker' ).show();
		}
	}

	sbe_settings.init();
});
