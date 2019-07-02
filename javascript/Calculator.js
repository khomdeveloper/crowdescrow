var Calculator = {
	comission: false,
	init: function() {
		this.initCalculator();
		this.onShow();
	},
	onShow: function() {
		A.w(['User'], function() {
			if (User.data) {
				$('.menu_button_login').hide();
			} else {
				$('.menu_button_login').show();
			}
		});
	},
	initCalculator: function() {
		var that = this;
		if (that.comission === false) {
			setTimeout(function() {
				that.initCalculator();
			}, 50);
		} else {
			that.calculate();
			$('.amount_calculate_comission').unbind('change').change(function() {
				that.calculate();
			}).unbind('keyup').keyup(function() {
				that.calculate();
			}).unbind('mouseup').mouseup(function() {
				that.calculate();
			});
		}
	},
	calculate: function() {
		var that = this;

		var fee = B.round(Math.max(that.minimum, $('.amount_calculate_comission').val() * that.comission), 0.01);

		B.forceToFloat($('.amount_calculate_comission'),0);

		$('.comission_indicator').html(fee);
		$('.iwannaget').html($('.amount_calculate_comission').val());

		if (fee == that.minimum) {
			$('.minimum_detector').show();
		} else {
			$('.minimum_detector').hide();
		}

		var n = B.round($('.amount_calculate_comission').val() / (1 - that.comission), 0.01);

		if ($('.amount_calculate_comission').val() * that.comission / (1 - that.comission) < that.minimum) {
			n = $('.amount_calculate_comission').val() * 1 + that.minimum;
		}

		$('.needtobuy').html(n);

	}
};