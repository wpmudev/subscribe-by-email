jQuery(window).load(function() {

	var follow_box_main = jQuery('#sbe-follow');

	var sbe_opened = false;

	if ( follow_box_main.hasClass('sbe-opened') )
		sbe_opened = true;	

	
	var follow_box = jQuery('#sbe-follow-wrap');

	var follow_box_height = follow_box.outerHeight();
	if ( ! sbe_opened ) {
		follow_box_main.animate({
			bottom: '-' + follow_box_height + 'px'
		},2000);
	}

	jQuery('.sbe-follow-link').click(function(e) {
		e.preventDefault();
		var _this = jQuery(this);

		if ( sbe_opened ) {
			_this.find('span').removeClass('sbe-opened');
			follow_box_main.animate({
				bottom: '-' + follow_box_height + 'px'
			});
			sbe_opened = false;
		}
		else {
			_this.find('span').addClass('sbe-opened');
			follow_box_main.animate({
				bottom: 0
			});
			sbe_opened = true;
		}

		
	})
});