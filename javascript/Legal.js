var Legal = {
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
	}	
};

