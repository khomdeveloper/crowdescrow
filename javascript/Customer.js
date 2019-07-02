var Customer = {
	//simple mode active do not delete comments for extended mode
	init : function(){
		A.w(['Deal'],function(){
			Deal.init();
		});		
	},
	onShow: function(){
		//Agreement.onShow('customer');
		A.w(['Deal'],function(){
			Deal.onShow();
		});
	}
};