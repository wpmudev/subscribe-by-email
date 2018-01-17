var sbe_render_invisible_recaptcha = function() {

	for (var i = 0; i < document.forms.length; ++i) {

		var form = document.forms[i];
		var holder = form.querySelector('.sbe-recaptcha-holder');

		if (null === holder){
			continue;
		}

		(function(frm){

			var input = frm.elements['subscription-email'];
			var holderId = grecaptcha.render(holder,{
				'sitekey': sbe_invisible_recaptcha_i8.public_key,
				'size': 'invisible',
				'badge' : 'bottomright', // possible values: bottomright, bottomleft, inline
				'callback' : function (recaptchaToken) {
					//HTMLFormElement.prototype.submit.call(frm);
				}
			});

			input.addEventListener("focus", function(){
				let executed = this.getAttribute( 'recaptcha_executed' );
				if( executed == 'executed' ){
					return;
				}
				this.setAttribute( 'recaptcha_executed', 'executed' );
				grecaptcha.execute(holderId); 
			});

		})(form);
		
	}

};
