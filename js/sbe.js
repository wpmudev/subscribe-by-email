
	function SubscribeByEmailCreate() {
		var SubscriptionEmail = document.getElementById('subscription_email').value;
		var http = new XMLHttpRequest();
		if ( SubscriptionEmail != '' && SubscriptionEmail != sbe_localized.default_email ) {
			if ( !SubscriptionEmail.match( /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/ ) ) {
				jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong>'+sbe_localized.invalid_email+'</strong></ceneter></p></div>');
				return false;
			}
			var url = sbe_localized.site_url;
			var params = "action=sbe_create_subscription&email=" + SubscriptionEmail.replace("+","PLUS");
			http.open("POST", url, true);

			http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			http.setRequestHeader("Content-length", params.length);
			http.setRequestHeader("Connection", "close");
			http.onreadystatechange = function() {
				if(http.readyState == 4) {
					if (http.status == 200) {
						jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong>'+sbe_localized.subscription_created+'</strong></ceneter></p></div>');
						document.getElementById('subscription_email').value = '';
					} else {
						jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong>'+sbe_localized.already_subscribed+'!</strong></ceneter></p></div>');
					}
				}
			}
			http.send(params);
			return true;
		}
		return false;
	}
	function SubscribeByEmailCancel() {
		var SubscriptionEmail = document.getElementById('subscription_email').value;
		var http = new XMLHttpRequest();
		if ( SubscriptionEmail != '' && SubscriptionEmail != 'ex: john@hotmail.com' ) {
			var url = sbe_localized.site_url;
			var params = "action=sbe_cancel_subscription&email=" + SubscriptionEmail.replace("+","PLUS");
			http.open("POST", url, true);

			http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			http.setRequestHeader("Content-length", params.length);
			http.setRequestHeader("Connection", "close");

			http.onreadystatechange = function() {
				if(http.readyState == 4) {
					if (http.status == 200) {
						jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong>'+sbe_localized.subscription_cancelled+'</strong></ceneter></p></div>');
						document.getElementById('subscription_email').value = '';
					} else {
						jQuery('#subscribe-by-email-msg').html('<div style=\'font-size:20px; padding-bottom:20px;\'><p><center><strong>'+sbe_localized.failed_to_cancel_subscription+'</strong></ceneter></p></div>');
					}
				}
			}
			http.send(params);
		}
	}
	jQuery(document).ready(function() {
		jQuery("#subscribe-by-email-form").submit(function () {
			SubscribeByEmailCreate();
			return false;
		});
	});


