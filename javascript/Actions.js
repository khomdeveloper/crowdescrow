var Actions = {
	init : function(){
		this.onShow();
	},
	onShow: function() {
		if (User.data) {
			$('.menu_button_login').hide();
		} else {
			$('.menu_button_login').show();
		}
	},
}

