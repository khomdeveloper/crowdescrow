var Seller = {
	
	init: function() {
		A.w(['Deal'], function() {
			Deal.init();
		});
	},
	onShow: function() {
		A.w(['Deal'], function() {
			Deal.onShow();
		});
	}
};