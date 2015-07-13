jQuery(document).ready(function($) {
	var sbe_settings = {
		init: function() {
			$('.post-type-checkbox').change(this.check_box);
			$('#allow-categories').change(this.submit_form);
			$('.categorydiv input[type=checkbox]').change(this.set_category_box_attrs);
			this.init_categories_boxes();
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
		},
		submit_form: function(e) {
			$('input[type=submit]').trigger('click');
		},
		set_category_box_attrs: function(e) {
			var element = $(this);
			var the_list = element.closest('ul');

			if ( element.attr('checked') && element.attr('value') == 'all' ) {
				the_list.find('input').attr('checked',false);
				the_list.find('input').attr('disabled',true);
				element.attr('checked',true);
				element.attr('disabled',false);
			}
			else if ( ! element.attr('checked') && element.attr('value') == 'all' ) {
				the_list.find('input').attr('disabled',false);
			}

		},
		init_categories_boxes: function() {
			var boxes = $('.postbox').each( function( i, item ) {
				var the_box = $(item);
				var all_selector = the_box.find('input[value=all]');
				if ( all_selector.attr('checked') ) {
					the_box.find('input').attr('checked',false);
					the_box.find('input').attr('disabled',true);
					all_selector.attr('checked',true);
					all_selector.attr('disabled',false);
				}
			});
		}
	}

	sbe_settings.init();
});