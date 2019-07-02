var Main = {
	init: function() {
		this.onShow();
	},
	onShow: function() {

		B.click($('.main_b_host .menu_button'), function(obj) {
			A.w(['Site', 'B'], function() {
				var page = B.getID(obj, 'menu_button_');
				Site.switchTo(page, function() {
					Site.scrollTo($('.page_content_' + page), -250);
				});
			});
		});
		
		B.click($('.iwactions.menu_button_actions'), function(obj) {
			Site.switchTo('actions',function(){
				Site.scrollTo($('.header_host'), -250);
			});
		});
		
		B.click($('.iwbug.menu_button_bug'), function(obj) {
			A.w(['Site', 'B'], function() {
				var page = B.getID(obj, 'menu_button_');
				Site.switchTo(page, function() {
					Site.scrollTo($('.page_content_' + page), -250);
				});
			});
		});

		$('.goto_page.id_calculator').unbind('click').click(function() {
			A.w(['Site'], function() {
				Site.switchTo('calculator');
			});
			return false;
		});

		A.w(['Faq', 'Help'], function() {
			Faq.top5init();
		});

	}

};