jQuery(document).ready(function($) {
	var sbe_settings = {
		init: function() {
			$('.post-type-checkbox').change(this.check_box)

		},
		check_box: function() {
			var checkbox = $(this);
			var is_checked = checkbox.attr('checked');
			var post_type_slug = checkbox.data('post-slug');

			if ( is_checked ) {
				$('.' + post_type_slug + '-checkbox').attr('disabled',false);
			}
			else {
				$('.' + post_type_slug + '-checkbox').attr('disabled',true);
			}

		}
	}

	sbe_settings.init();
});