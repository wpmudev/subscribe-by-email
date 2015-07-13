
var sbe_follow_button = {
	element: null,
	sbe_opened: false,
	follow_box: null,
	position: 'bottom',
	follow_box_dimension: null,
	correction:0,
	init:function( element, follow_wrap_element, position ) {
		sbe_follow_button.element = element;
		sbe_follow_button.position = position;

		if ( sbe_follow_button.element.hasClass('sbe-follow-opened') )
			sbe_follow_button.sbe_opened = true;

		sbe_follow_button.follow_box = follow_wrap_element;
		sbe_follow_button.follow_box_dimension = sbe_follow_button.get_dimensions();
		sbe_follow_button.correction = sbe_follow_button.get_correction();

		if ( position == 'left' ) {
			sbe_follow_button.element
				.find('.sbe-follow-link')
				.animateRotate(90,0);
		}

		if ( position == 'right' ) {
			sbe_follow_button.element
				.find('.sbe-follow-link')
				.animateRotate(-90,0);
		}

		if ( ! sbe_follow_button.sbe_opened ) {
			sbe_follow_button.animate( '-' + sbe_follow_button.follow_box_dimension + 'px', 2000 );
		}
		else {
			sbe_follow_button.animate( 0 + sbe_follow_button.correction, 200 );
		}
		jQuery('.sbe-follow-link').click(sbe_follow_button.onclick);
	},
	animate: function( position, speed ) {
		if ( sbe_follow_button.position == 'bottom' ) {
			var args = {bottom:position};
		}
		else if ( sbe_follow_button.position == 'left' ) {
			var args = {left:position};
		}
		else if ( sbe_follow_button.position == 'right' ) {
			var args = {right:position};
		}
		sbe_follow_button.element.animate(args,speed);
	},
	onclick: function (e) {
		e.preventDefault();		
		var _this = jQuery(this);

		if ( sbe_follow_button.sbe_opened ) {
			_this.find('span').removeClass('sbe-follow-opened');
			sbe_follow_button.animate('-' + sbe_follow_button.follow_box_dimension + 'px', 200);
			sbe_follow_button.sbe_opened = false;
		}
		else {
			_this.find('span').addClass('sbe-follow-opened');
			sbe_follow_button.animate(0 + sbe_follow_button.correction, 200);
			sbe_follow_button.sbe_opened = true;
		}
	},
	get_dimensions: function() {
		if ( sbe_follow_button.position == 'bottom' )
			return sbe_follow_button.follow_box.outerHeight() + 37;
		else
			return sbe_follow_button.follow_box.outerWidth()
	},
	get_correction: function() {
		if ( sbe_follow_button.position == 'bottom' )
			return parseInt( sbe_follow_button.follow_box.css('top'), 10 );
		else
			return 0;
	}
}

// Got from: http://stackoverflow.com/questions/15191058/css-rotation-cross-browser-with-jquery-animate
jQuery.fn.animateRotate = function(angle, duration, easing, complete) {
    var args = jQuery.speed(duration, easing, complete);
    var step = args.step;
    return this.each(function(i, e) {
        args.step = function(now) {
            jQuery.style(e, 'transform', 'rotate(' + now + 'deg)');
            if (step) return step.apply(this, arguments);
        };

        jQuery({deg: 0}).animate({deg: angle}, args);
    });
};
	
