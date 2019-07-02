var Contacts = {
	init : function(){
		this.onShow();
	},
	onShow: function() {
		var that = this;
		if (User.data) {
			$('.menu_button_login').hide();
		} else {
			$('.menu_button_login').show();
		}
		
		if (User.data && !$('.send_email_button').length){
			window.location.reload(true);
		}
		
		$('.r_control').unbind('keyup').keyup(function() {
			that.control();
		}).unbind('mouseup').mouseup(function() {
			that.control();
		}).unbind('change').change(function() {
			that.control();
		});

		that.control();
		
	},
	control: function() {
		var that = this;
		if ($('.landing_email').val()) {
			B.click($('.send_email_button').css({
				opacity: 1,
				cursor: 'pointer'
			}), function(obj) {
				$('.landing_response').slideUp();
				B.post({
					r: 'user/main',
					Landing: 'send',
					email: $('.landing_email').val(),
					message: $('.landing_review').val(),
					ok: function(response) {
						if (response && response.ok) {
							$('.landing_response').removeClass('landing_error').removeClass('landing_success').addClass('landing_success').slideDown().html(response.ok);
							$('.landing_email').val('');
							$('.landing_review').val('');
						} else if (response) {
							if (response.User){
								window.location.assign('/login');
							}
						}
					},
					no: function(response) {
						$('.landing_response').removeClass('landing_error').removeClass('landing_success').addClass('landing_error').slideDown().html(response.message);
					}
				});
			});
		} else {
			$('.send_email_button').css({
				opacity: 0.5,
				cursor: 'default'
			}).unbind('click');
		}
	},
};