var Landing = {
	init: function() {
		var that = this;
		that.onShow();
	},
	onShow: function() {
		var that = this;

		that.calculateRemainTime();

		//$('.menu_buttons').hide();
		
		B.click($('.menu_button_noticate'),function(obj){
			A.w(['Site'], function(){
				Site.scrollTo($('.notification_button_target'),0);
			});
		});

		A.w(['B', 'Calculator'], function() {
			Calculator.init();
		});

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
			B.click($('.landing_email_button').css({
				opacity: 1,
				cursor: 'pointer'
			}), function(obj) {
				$('.landing_response').slideUp();
				B.post({
					r: 'user/main',
					Landing: 'create',
					email: $('.landing_email').val(),
					message: $('.landing_review').val(),
					ok: function(response) {
						if (response && response.ok) {
							$('.landing_response').removeClass('landing_error').removeClass('landing_success').addClass('landing_success').slideDown().html(response.ok);
						}
					},
					no: function(response) {
						$('.landing_response').removeClass('landing_error').removeClass('landing_success').addClass('landing_error').slideDown().html(response.message);
					}
				});
			});
		} else {
			$('.landing_email_button').css({
				opacity: 0.5,
				cursor: 'default'
			}).unbind('click');
		}
	},
	onHide: function() {
		$('.menu_buttons').show();
		//$('.time_left_host').hide();
		$('.landing_dialog_host').html('');
	},
	when: false,
	calculateRemainTime: function() {

		var that = this;
		if (that.when === false) { //not initialized
			$('.time_left_host').hide();
			setTimeout(function() {
				that.calculateRemainTime();
			}, 100);
		} else {
			A.w(['T'], function() {
				var left = B.timeDifference(new Date(that.when * 1000), new Date());

				if (left.sign < 0) {
					location.href = A.baseURL();
					return;
				}

				if (left.days > 0) {
					var str = left.days + ' ' + (T.locale == 'ru'
							? 'дней'
							: 'days') + ' ';
				} else {
					var str = '';
				}

				str += ' ' + (left.hours < 10
						? '0'
						: '') + left.hours + ':' + (left.minutes < 10
						? '0'
						: '') + left.minutes + ':' + (left.seconds < 10
						? '0'
						: '') + left.seconds;

				$('.time_left').html(str);

				$('.time_left_host').show();

				setTimeout(function() {
					that.calculateRemainTime();
				}, 1000);
			});
		}
	}
};

