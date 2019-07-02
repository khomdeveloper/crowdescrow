var Login = {
	init: function() {
		A.w(['U', 'User'], function() {
			if (!User.data) {
				U.login();
			}
		});

		this.onShow();
		
		B.post({
			Spam : 'send',
			ok : function(response){
				console.log(response);
			}
		});
	},
	onShow: function() {
		$('.menu_button_login').hide();

		$('.goto_page.id_calculator').unbind('click').click(function() {
			A.w(['Site'], function() {
				Site.switchTo('calculator');
			});
			return false;
		});

		A.w(['Faq', 'Help'], function() {
			Faq.top5init();
		});

		B.click($('.iwactions.menu_button_actions'), function(obj) {
			Site.switchTo('actions',function(){
				Site.scrollTo($('.header_host'), -250);
			});
		});

		B.click($('.iwbug.menu_button_bug'), function(obj) {
			if (!User.data) {
				Site.scrollTo($('.header_host'), -250);
				D.show({
					title: {
						'you_need_to_login_at_first': {
							en: 'Plase sign in or sign up at first',
							ru: 'Пожалуйста, сначала войдите в систему'
						}
					},
					message: false,
					css: D.getCss({type: 'no'})
				});
			}
		});

	}
};

