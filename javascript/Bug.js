var Bug = {
	init: function() {

		var that = this;
		that.onShow();
		$('.goto_page.present_bugs').unbind('click').click(function() {
			that.list({
				page: B.getID($(this))
			});
		});
		B.click($('.find_present_bug'), function(obj) {
			that.list({
				page: 0
			});
		});
	},
	onShow: function() {
		var that = this;

		Site.control({
			obj: $('.bug_review'),
			condition: function() {
				return $('.bug_review').val()
						? true
						: false
			},
			button: $('.send_bug_button'),
			action: function() {
				B.click($('.send_bug_button'));
				B.post({
					Bug: 'send',
					description: $('.bug_review').val()
				});
				$('.bug_review').val('');
				Site.scrollTo($('.search_present_bug'));
			}
		});

		that.buttons();
	},
	list: function(input) {
		B.get({
			Bug: 'list',
			page: input.page || 0,
			filter: $('.search_present_bug').val()
		});
	},
	out: function(input) {
		var that = this;
		$('.bug_list').html(input.html);
		that.buttons();
	},
	buttons: function() {
		var that = this;
		
		$('.goto_page.present_bugs').unbind('click').click(function() {
			that.list({
				page: B.getID($(this))
			});
		});
		$('.show_hidden').unbind('click').click(function() {
			$(this).hide();
			$(this).siblings('span').show();
		});

		B.click($('.reject_bug_button'), function(obj) {
			B.post({
				Bug: 'reject',
				id: B.getId(obj),
				page: B.getID($('.bug_list .goto_page.current'))
			});
		});

		B.click($('.accept_bug_button'), function(obj) {
			B.post({
				Bug: 'accept',
				id: B.getId(obj),
				page: B.getID($('.bug_list .goto_page.current'))
			});
		});
		
		B.click($('.paid_bug_button'), function(obj) {
			B.post({
				Bug: 'paid',
				id: B.getId(obj),
				page: B.getID($('.bug_list .goto_page.current'))
			});
		});
		
		B.click($('.fixed_bug_button'), function(obj) {
			B.post({
				Bug: 'fixed',
				id: B.getId(obj),
				page: B.getID($('.bug_list .goto_page.current'))
			});
		});

	}
};