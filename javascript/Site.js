//Page switcher
var Site = {
	required: [
		'$', 'Base', 'T', 'Preloader'
	],
	history: {},
	currentPage: 0,
	getPageControllerName: function(page) {
		return page.charAt(0).toUpperCase() + page.slice(1);
	},
	switchWithError: function(input) {
		this.switchTo(input.page);
		D.show({
			title: input.message,
			css: D.getCss({type: 'no'}),
			message: false
		});
	},
	switchTo: function(page0, callback, noHistory) {

		var that = this;

		if (!page0) {
			var page0 = 'main';
		}

		if (typeof page0 === 'object') {
			var callback = page0.callback
					? page0.callback
					: false;
			var noHistory = page0.noHistory
					? page0.noHistory
					: false;
			var page = page0.page;
		} else {
			var page = page0;
		}

		//action on onHide
		if (that.current) {
			var OldPage = that.getPageControllerName(that.current);
			A.w(['B', OldPage], function() {
				if (window[OldPage] && window[OldPage].onHide) {
					window[OldPage].onHide();
				}
			});
		}


		if (page === 'login' && User.data) {
			var page = 'main';
		}

		if (page === 'main' && !User.data) {
			var page = 'login';
		}

		var $page = $('.' + page + '_page');
		var $preloader = $('.preloader_wrapper', $page);

		if ($preloader.length) { //preloader on the page

			$('.page').hide();
			$page.fadeIn();

			if (!$preloader.hasClass('preloader_' + page)) {
				$preloader.addClass('preloader_' + page);
				A.w(['Preloader'], function() {
					Preloader.show('preloader_' + page);
				});
			}

			B.getHTML({
				file: 'pages/' + page + '/' + page + '.php',
				once: 1,
				selector: page + '_page .page_content',
				host: $page,
				append: 1,
				callback: function(host) {
					$('.page_content', host).hide();
					A.w(['Preloader'], function() {
						Preloader.hide('preloader_' + page, function() {
							Preloader.remove('preloader_' + page);
							$('.page_content', host).fadeIn(function() {
								if (callback) {
									callback();
								}
							});
						});
					});
					//var Page = page.charAt(0).toUpperCase() + page.slice(1);
					var Page = that.getPageControllerName(page);
					A.w(['B', Page], function() {
						if (window[Page] && window[Page].init) {
							window[Page].init();
						}
					});
				}
			});
			//}, 2000);
		} else {
			$('.page').slideUp();
			$page.slideDown(function() {
				var Page = that.getPageControllerName(page);
				if (window[Page] && window[Page].onShow) {
					window[Page].onShow();
				}
				if (callback) {
					callback();
				}
			});
		}

		$('.menu_button_' + this.currentPage).removeClass('menu_current');

		Site.setCurrentPage(page);

		var obj = {};

		obj['_title:' + page] = {
			en: page,
			ru: page
		};

		//create temporary object
		if (!$('.temporary_translate').length) {
			$('body').append('<div class="temporary_translate"></div>');
		}
		var jq = $('.temporary_translate');

		T.translate(obj, jq, function() {
			$('title').text(jq.html());
		});

		//$('title').text(page);

		if (window.history && window.history.pushState) {

			if (!noHistory) {
				window.history.pushState(this.history, '', A.baseURL() + page);
			}

			$(window).unbind('popstate').bind('popstate', function(event) {
				var a = window.location.href.split('/');
				Site.switchTo(a[a.length - 1], false, true);
			});

		}

		var menuButton = $('.menu_button_' + page);
		if (!menuButton.hasClass('menu_current')) {
			menuButton.addClass('menu_current');
		}
	},
	setCurrentPage: function(newPage) {
		Site.currentPage = newPage;

		$('.background_image').attr({
			src: A.baseURL() + 'images/' + (Site.currentPage == 'landing'
					? 'main'
					: Site.currentPage) + '.jpg'
		}).unbind('error').error(function() {
			$('body').css({
				'background-image': 'url(' + A.baseURL() + 'images/main.jpg' + ')'
			});
		}).load(function() {
			$('body').css({
				'background-image': 'url(' + A.baseURL() + 'images/' + (Site.currentPage == 'landing'
						? 'main'
						: Site.currentPage) + '.jpg' + ')'
			});
		});
	},
	initMenu: function(current) {

		//console.log(current);

		var that = this;
		that.setCurrentPage(current);

		var menuButton = $('.menu_button_' + current);
		if (!menuButton.hasClass('menu_current')) {
			menuButton.addClass('menu_current');
		}

		//init start page
		var Page = that.getPageControllerName(current);
		A.w(['B', Page], function() {
			if (window[Page] && window[Page].init) {
				window[Page].init();
			}
		});

		A.w(['B'], function() {
			/*$('.menu_button').each(function() {
				var but = $(this);
				var page = B.getID(but, 'menu_button_');
*/
				$('.menu_button').unbind('click').click(function() {
					
					var page = B.getID($(this), 'menu_button_');
					
					if (page !== Site.currentPage) {

						if (!User.data && page == 'bug') {
							that.scrollTo($('.header_host'), -250);
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
						} else {
							that.switchTo(page, function() {
								that.scrollTo($('.page_content_' + page), -250);
							});
						}
					}
					return false;
				});

//			});
		});
	},
	getDialogCSS: function(add) {
		var css = {
			dialog_back: {
				background: 'rgba(0,0,0,0.8)'
			},
			message_back: {
				background: 'url(' + A.baseURL() + '/images/paper1.jpg)'
			},
			dialog_message: {
				'max-width': '400px',
				border: 'none'
			},
			title: {
				border: 'none',
				'padding-bottom': '0px',
				'padding-left': '20px'
			},
			buttons: {
				border: 'none',
				'vertical-align': 'top',
				'padding-top': '20px',
				width: '70px'
			},
			exit: {
				width: '30px',
				height: '30px'
			},
			exit_button_host: {
				width: '30px',
				height: '30px'
			},
			message: {
				'font-size': '1rem',
				color: 'navy',
				'text-align': 'left'
			}
		};

		if (add) {
			for (var i in add) {
				if (!css[i]) {
					css[i] = {};
				}
				for (var property in add[i]) {
					css[i][property] = add[i][property];
				}
			}
		}

		return css;

	},
	scrollTo: function(target, offset) {
		if (target.length) {
			var pos = target.offset();
			$("html, body").animate({scrollTop: pos.top + (offset
						? offset
						: 0)}, 300);
		}
	},
	click: function(obj, callback) {
		obj.css({
			opacity: 0.7
		}).unbind('mouseover').mouseover(function() {
			$(this).css({
				opacity: 1
			});
		}).unbind('mouseout').mouseout(function() {
			$(this).css({
				opacity: 0.7
			});
		});

		B.click(obj, callback);
	},
	/**	  
	 * TODO: specifie uploader
	 * 
	 * @param {type} data
	 * 
	 * {
	 *		obj : Class,
	 *		id : obj_id
	 * }
	 * 
	 * @returns {undefined}
	 */
	initFileUploader: function(data) {

		B.loadScript(A.vh2015url + 'startscript/Uploader.js', function() {

			var get = {
				r: 'user/main',
				id: data.id
			};

			if (data.get) {
				get = $.extend(get, data.get);
			}

			get[data.obj] = 'upload';

			if (B.isIE()) {
				$('.' + data.obj + '_uploader_host_' + data.id + '_new').css({
					left: '0px',
					position: 'relative',
					top: '-20px',
					opacity: 1
				});

				$('.upload_new').css({
					width: '150px',
					height: '35px'
				});

			}

			Uploaders.uploaders[data.obj + '_image_' + data.id + '_'] = new Uploader({
				host: $('.' + data.obj + '_uploader_host_' + data.id + '_new'),
				name: data.obj + '_image_' + data.id + '_',
				script: 'index.php',
				get: get,
				maxsize: 5 * 1048576,
				multi: true,
				title: '',
				onRemoveOnError: function(id) { //?WHATSIT
					if (id) {
						$('.remove_file_host_' + id.split('file_ready_')[1]).remove();
					}
				},
				error: function(errors) {
					if (!errors) {
						return;
					}
					console.log(errors);
				},
				change: false, //we submit on change
				previewer: false //make it true
			});

		});
	},
	reloadImage: function(input) {
		$('.message img').attr({
			src: input.image// + '?s=' + Math.random()
		});
	},
	savedParameters: {},
	saveFormParameters: function(objName) {
		var that = this;
		var host = $('.form_' + objName);

		if (host.length) {
			that.savedParameters = {};
			$('.input', host).each(function() {
				that.savedParameters[B.getID($(this), objName + '_')] = $(this).val();
			});
		}
	},
	restoreFormParameters: function(objName) {
		var that = this;

		if (typeof objName == 'object') {
			var objName = objName.object;
		}

		var host = $('.form_' + objName);

		for (var i in that.savedParameters) {
			$('.' + objName + '_' + i).val(that.savedParameters[i]);
		}

		that.savedParameters = {};
	},
	/**
	 * 
	 * @param {type} input
	 * 
	 * image - image_name
	 * obj - Class
	 * id - object id
	 * image_id - image id
	 * readonly - if present - only readonly
	 * 
	 * onDelete - function(id, image_id){
	 * 
	 * }
	 * 
	 * onExit - function(id){
	 * 
	 * }
	 * 
	 * @returns {undefined}
	 */
	showImage: function(input) {
		var that = this;

		//console.log(input);

		that.saveFormParameters(input.obj);

		//console.log(that.savedParameters);

		D.hide(function() {
			D.show({
				title: {
					uploaded_image_title: {
						en: 'UPLOADED IMAGE',
						ru: 'ЗАГРУЖЕННОЕ ИЗОБРАЖЕНИЕ'
					}
				},
				message: '<img src="' + input.image + '" style="width:100%; margin:auto;" alt /><div class="reload_image_host" style="width:200px;"></div>',
				onShow: function() {

					$('.add_but').css({
						'margin-right': '38px'
					});

					if (!input.readonly) {

						var get = {
							r: 'user/main',
							id: input.id,
							image: input.image_id
						};

						if (input.get) {//передача дополнительных данных через GET
							get = $.extend(get, input.get);
						}

						get[input.obj] = 'upload';

//was LoadScript withou cash
						B.loadRemote(A.vh2015url + 'startscript/Uploader.js', function() {

							Uploaders.uploaders[input.obj + '_image_' + input.id + '_' + input.image_id] = new Uploader({
								host: $('.reload_image_host'),
								name: input.obj + '_image_' + input.id + '_' + input.image_id,
								script: 'index.php',
								get: get,
								maxsize: 5 * 1048576,
								multi: true,
								title: '',
								onRemoveOnError: function(id) {
									if (id) {
										$('.remove_file_host_' + id.split('file_ready_')[1]).remove();
									}
								},
								onStart: function() {
									$('.message img').slideUp().unbind('load').load(function() {
										$('.message img').slideDown();
									});
								},
								error: function(errors) {
									if (!errors) {
										return;
									}
									console.log(errors);
								},
								change: false, //we submit on change
								previewer: false //make it true
							});

						});

					}

				},
				exit: function() { //here we reload data
					if (input.onExit) {
						input.onExit(input.id);
					}
				},
				buttons: {
					upload: input.readonly
							? false
							: {
								image: 'images/upload.png',
								action: function(name) {
									$('.file_input.id_' + input.obj + '_image_' + input.id + '_' + input.image_id).click();
								}
							},
					'delete': input.readonly
							? false
							: {
								image: 'images/del.png',
								action: function(name) {
									D.hide(function() {
										D.show({
											title: {
												are_you_sure_to_delete_image: {
													en: 'Are you sure you want to delete image?',
													ru: 'Вы уверены что хотите удалить изображение?'
												}
											},
											message: false,
											css: D.getCss({type: 'no'}),
											exit: function() {
												that.showImage(input);
											},
											buttons: {
												ok: {
													image: A.baseURL() + 'images/go.png',
													action: function() {
														D.hide(function() {
															if (input.onDelete) {
																input.onDelete(input.id, input.image_id);
															}

														}, true, false);
													}
												}
											}
										});
									});
								}
							}
				},
				/*css: $.extend(D.getCss({type: 'asphalt'}), {
					add_but: {
						'margin-right': '38px'
					},
					message: {
						color: 'white',
						'max-height': '80vh'
					}
				})*/
				cls: 'dialog-asphalt'
			});
		}, true);
	},
	/**
	 * 
	 * @param {type} input
	 * 
	 * {
	 *	readonly
	 *	onDelete
	 *	onExit
	 * 
	 * }
	 * 
	 * @returns {undefined}
	 */
	initImages: function(input) {
		var that = this;
		var readonly = input.readonly;

		if (input.onUpload) {
			that.onUpload = input.onUpload;
		} else {
			that.onUpload = function() {

			};
		}

		$('.image_preload').unbind('load').load(function() {
			$(this).parent().css({
				'background-image': 'url(' + $(this).attr('alt') + ')',
				'background-position': 'cover',
				'background-size': '100%',
				cursor: 'pointer'
			}).unbind('click').click(function() {

				if (input.onlyPreview) {
					return;
				}

				var image_id = B.getId($(this), 'imageId_');
				var obj_id = B.getId($(this), 'objId_');
				var class_name = B.getId($(this), 'className_');

				var alwaysReadonly = $(this).hasClass('readonlyImage');

				if (input.click) {
					input.click({
						image_id: image_id,
						class_name: class_name,
						obj_id: obj_id
					});
				} else {

					that.showImage({
						image: $('img', $(this)).attr('alt'),
						id: obj_id,
						obj: class_name,
						image_id: image_id,
						get: input.get
								? input.get
								: false,
						readonly: (readonly
								? 1
								: (alwaysReadonly
										? 1
										: 0)),
						onExit: input.onExit
								? input.onExit
								: false,
						onDelete: input.onDelete
								? input.onDelete
								: false
					});

				}
			});
		}).unbind('error').error(function() {
			$(this).parent().remove();
		});

	},
	contextHelpProcessor: function(host, host2) { //show/hide necessary help
		var that = this;

		if (!host.is(':visible')) {
			setTimeout(function() {
				console.log('context wait');
				that.contextHelpProcessor(host, host2);
			}, 200);
			return;
		}

		$('.context_help', host).each(function() {
			var id = B.getId($(this));

			var obj = $('.' + id, host2
					? host2
					: host);

			//console.log(obj.attr('class'));

			if (obj.length && obj.is(':visible')) {
				$('.context_help.id_' + id, host).show();
			} else {
				$('.context_help.id_' + id, host).hide();
			}
		});
	},
	/**
	 * input.obj - init object
	 * input.condition : function 
	 * input.button 
	 * input.action : function
	 * 
	 * @param {type} input
	 * @returns {undefined}
	 */
	control: function(input) {

		var that = this;

		if (input.obj) {

			var input2 = {};

			for (var i in input) {
				if (i != 'obj') {
					input2[i] = input[i];
				}
			}

			input.obj.unbind('mouseup').mouseup(function() {
				that.control(input2);
			}).unbind('keyup').keyup(function() {
				that.control(input2);
			}).unbind('change').change(function() {
				that.control(input2);
			});
		}

		if (input.during) {
			input.during();
		}

		if (input.condition()) {
			input.button.css({
				opacity: 1
			}).unbind('click').click(function() {
				input.action();
			});
		} else {
			input.button.css({
				opacity: 0.5
			}).unbind('click');
		}

	}
};