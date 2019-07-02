var Faq = {
	top5init: function() {

		$('.goto_help').unbind('click').click(function() {
			var id = B.getId($(this));
			A.w(['Site', 'Help'], function() {
				Site.switchTo('help', function() {
					//Site.scrollTo($('.spoiler_head.id_' + id));
					Help.open(id);
				});
			});
		});

	},
	current: false
};


var Help = {
	goto: function(id) {
		D.hide();
		A.w(['Site', 'Help'], function() {
			Site.switchTo('help', function() {
				//Site.scrollTo($('.spoiler_head.id_' + id));
				Help.open(id);
			});
		});
	},
	open: function(id, callback) {
		var old = Faq.current;

		$('.spoiler_body.id_' + old).slideUp(function() {
			$('.spoiler_head.id_' + old).removeClass('opened');
		});
		Faq.current = id;

		$('.spoiler_head.id_' + id).addClass('opened');
		$('.spoiler_body.id_' + id).slideDown(function(){
			Site.scrollTo($('.spoiler_head.id_' + id));
		});
		
		this.onShow();
		
	},
	onShow: function() {
		if (User.data) {
			$('.menu_button_login').hide();
		} else {
			$('.menu_button_login').show();
		}
	},
	init: function() {
		
		this.onShow();
		
		var that = this;
		A.w(['B'], function() {
			B.click($('.spoiler_head'), function(obj) {
				var id = B.getID(obj);
				if (id === Faq.current && $('.spoiler_body.id_' + id).is(':visible')) {
					$('.spoiler_body.id_' + id).slideUp(function() {
						$('.spoiler_head').removeClass('opened');
					});
				} else {
					that.open(id);
				}
			});
		});
		
		$('.goto_help').unbind('click').click(function(){
			Help.open(B.getID($(this)));
		});
	
	}
};
