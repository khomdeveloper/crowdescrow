var User = {
	data: false,
	updateBalance: function() {
		B.get({
			r: 'user/main',
			User: 'update_balance'
		});
	},
	init: function() {
		var that = this;
		B.get({
			r: 'user/main',
			User: 'profile'
		});
	},
	onLogout: function(){
		U.onLogout();
	},
	profileInit: function(input) {
		var that = this;
		$('.profile_email').val(input.email);
		$('.profile_name').val(input.name);

		User.data.name = input.name;
		$('.username').html(User.data.name);

		$('.image_preview_host', $('.user_profile_page .uploaded_files_host')).remove();

		$('.user_profile_page .uploaded_files_host').append(input.files);

		that.initImages();

		A.w(['Site'], function() {
			Site.initFileUploader({
				obj: 'User',
				id: input.id
			});
		});

		that.profileControl();

		$('.profile_control').unbind('keyup').keyup(function() {
			that.profileControl();
		}).unbind('mouseup').mouseup(function() {
			that.profileControl();
		}).unbind('change').change(function() {
			that.profileControl();
		});

		$('.user_not_confirmed_indicator').unbind('click').click(function(){
			that.setConfirmed({
				sent_to_email : $('.profile_email').val()
			});
		});

		$('.user_profile_page .user_avatar').unbind('click').click(function() {
			B.get({
				r: 'user/main',
				User: 'choose_avatar'
			});
		});
	},
	markEmailAsConfirmed: function() {
		var that = this;
		
		if ($('.profile_email').length && $('.profile_email').is(':visible')) {
			$('.user_confirmed_indicator').show();
			$('.user_not_confirmed_indicator').hide();			
			$('.profile_set_email').css({
				opacity: 0.5
			}).unbind('click');
		} else {
			setTimeout(function() {
				that.markEmailAsConfirmed();
			}, 200);
		}
	},
	cleanNewPass: function(){
		$('.profile_pass').val('');
		$('.profile_repeat').val('');
		this.profileControl();
	},
	markEmailAsNotConfirmed: function() {
		var that = this;

		if ($('.profile_email').is(':visible')) {
			$('.user_confirmed_indicator').hide();
			$('.user_not_confirmed_indicator').show();
		} else {
			setTimeout(function() {
				that.markEmailAsNotConfirmed();
			}, 200);
		}
	},
	profileControl: function() {
		var that = this;
		$('.profile_control').each(function() {
			var id = B.getId($(this), 'profile_');
			if ($(this).val() && $(this).val() != $('.profile_' + id + '.default').val() &&
					(id != 'pass' || (id == 'pass' && $('.profile_pass').val() == $('.profile_repeat').val()))) {

				B.click($('.profile_set_' + id).css({
					opacity: 1,
					cursor: 'pointer'
				}), function(obj) {

					var post = {
						r: 'user/main',
						User: 'set'
					};

					post[id] = $('.profile_' + id + '.profile_control').val();

					if (id === 'pass' || id === 'email') {

						D.show({
							title: id === 'pass' ? {
								'sure_to_cahnge_the_pass': {
									en: 'Are you sure to change the password?',
									ru: 'Вы уверены что хотите сменить пароль?'
								}
							} : {
								'sure_to_cahnge_the_email': {
									en: 'Are you sure to change email for notifications?',
									ru: 'Вы уверены что хотите сменить email для уведомлений?'
								}
							},
							message: false,
							css: D.getCSS({type: 'norm'}),
							buttons: {
								ok: {
									image: A.baseURL() + 'images/go.png',
									action: function() {
										D.hide();
										B.post(post);
									}
								}
							}
						});
						
					} else {	
						B.post(post);
					}
				});
			} else {
				$('.profile_set_' + id).css({
					opacity: 0.5,
					cursor: 'deafult'
				}).unbind('click');
			}
		});
	},
	onShow: function() {

	},
	showBalance: function(response) {
		User.data.money = response.balance;
		$('.user_balance_detector').html(User.data.money + '$');
	},
	setConfirmedControl: function(input) {
		var that = this;
		if ($('.confirmation_code').val()) {
			B.click($('.confirm_email').css({
				opacity: 1,
				cursor: 'pointer'
			}), function(obj) {
				B.post({
					User: 'confirm_email',
					code: $('.confirmation_code').val(),
					error: 1
				});
				D.hide();
			});
		} else {
			$('.confirm_email').css({
				opacity: 0.3,
				cursor: 'default'
			}).unbind('click');
		}

		if ($('.noemail_to_set').val()) {
			B.click($('.set_email').css({
				opacity: 1,
				cursor: 'pointer'
			}), function(obj) {
				var email = $('.email_to_set').val();
				D.hide();
				$('.profile_email').val(email);
				$('.profile_email.default').val(email);
				B.post({
					r: 'user/main',
					User: 'set_email',
					email: email
				});
			});
		} else {
			$('.set_email').css({
				opacity: 0.3,
				cursor: 'default'
			}).unbind('click');
		}

		if ($('.email_to_set').val()) {
			B.click($('.set_new_email').css({
				opacity: 1,
				cursor: 'pointer'
			}), function(obj) {
				var email = $('.email_to_set').val();
				D.hide(function() {
					D.show({
						title: {
							sure_to_set_another_email_for_notifications: {
								en: 'Are you sure to set another email for notifications?',
								ru: 'Вы уверены что хотите задать другой email для уведомлений?'
							}
						},
						message: false,
						css: D.getCss({type: 'norm'}),
						exit: function() {
							D.hide(function() {
								that.setConfirmed(input);
							}, false, 'slide');
						},
						buttons: {
							ok: {
								image: A.baseURL() + 'images/go.png',
								action: function() {
									D.hide();
									$('.profile_email').val(email);
									$('.profile_email.default').val(email);
									B.post({
										r: 'user/main',
										User: 'set_email',
										email: email
									});
								}
							}
						}
					});
				}, false, 'slide');

			});
		} else {
			$('.set_new_email').css({
				opacity: 0.3,
				cursor: 'default'
			}).unbind('click');
		}
	},
	dialog: function(input) {
		U.dialog(input);
	},
	login: function(input) {
		U.login(input);
	},
	setConfirmed: function(input) {
		var that = this;

		if (input.confirmed_email) {
			User.data.confirmed_email = input.confirmed_email;
		} else if (input.sent_to_email) {

			if ($('.dialog').is(':visible')) {
				setTimeout(function() {
					that.setConfirmed(input);
				}, 500);
			} else {

				//remove indicator that confirmed
				that.markEmailAsNotConfirmed();

				D.show({
					title: {
						confirmation_email_has_sent2: {
							en: 'Confirmation code has been sent to <span class="sent_to_email">{{email}}</span> follow the received link or enter the code below:',
							ru: 'Код подтверждения отправлен на <span class="sent_to_email">{{email}}</span> перейдите по полученной ссылке или введите код ниже:',
							_include: {
								email: input.sent_to_email
							}
						}
					},
					message: 'pages/user/confirmation.php?email=' + input.sent_to_email,
					onShow: function() {
						that.setConfirmedControl(input);
						$('.confirmation_control').unbind('keyup').keyup(function() {
							that.setConfirmedControl(input);
						}).unbind('change').change(function() {
							that.setConfirmedControl(input);
						}).unbind('mouseup').mouseup(function() {
							that.setConfirmedControl(input);
						});
					},
					css: D.getCss({type: 'norm'})
				});

			}

		} else { //no email at all
			D.hide(function() {
				D.show({
					title: {
						no_email_detected: {
							en: 'Please enter email address for notifications',
							ru: 'Пожалуйста введите email адрес для уведомлений'
						}
					},
					message: 'pages/user/noemail.php',
					onShow: function() {
						that.setConfirmedControl(input);
						$('.confirmation_control').unbind('keyup').keyup(function() {
							that.setConfirmedControl(input);
						}).unbind('change').change(function() {
							that.setConfirmedControl(input);
						}).unbind('mouseup').mouseup(function() {
							that.setConfirmedControl(input);
						});
					},
					css: D.getCss({type: 'no'})
				});
			}, false, 'slide');
		}
	},
	success: function(response) {

		var that = this;

		User.data = response;

		$('.user_balance_detector').html(response.money + '$').show();

		B.getHTML({
			file: 'protected/views/site/pages/user/logout.php',  //A.vh2015 + 'templates/logout.php',
			host: $('.user_profile_host'),
			cash: true,
			append: true,
			selector: '.user_profile',
			once: true,
			method: 'include',
			callback: function() {
				$('.username').html(User.data.name).unbind('click').click(function() {
					Site.switchTo('user');
				});
				
				
				$('.header_host .user_avatar').css({
					'background-image' : "url('" + (User.data.photo.length
							? User.data.photo
							: 'images/user_logo.png') + "')"
				}).unbind('click').click(function() {
					Site.switchTo('user');
				});
				
				
				$('.user_profile_host').show();
				$('.welcome_header_host').hide();
				$('.logged').show(); //show menu items
			}
		});


		$('#ulogin_receiver_container').remove();
		$('.ulogin-dropdown').remove();

		$('.menu_button_login').hide();

		if (that.onSuccess) {
			that.onSuccess();
		} else {

			//console.log('there');

			A.w(['Site'], function(on) {
				if (Site.currentPage == 'login') {
					Site.switchTo('main');
				}
			});

		}

		//start online checker
		O.start({
			r: 'user/main',
			User: 'isOnline'
		});

		//run email checker
		setTimeout(function() {
			B.get({
				r: 'user/main',
				User: 'check_confirmed'
			});
		}, 500); //delay because of dealog move

	},
	create: function(data) {
		console.log(data);
		this.data = data;
	},
	selectPartner: function(list) {
		D.show({
			title: '',
			css: D.getCss({type: 'norm'}),
			message: 'pages/dialogs/opponents.php',
			onShow: function() {

				//TODO: choose if landing page or not

				$('.title', $('.dialog_message')).html($('.opponents_list').attr('title'));
			}
		});
	},
	outputInviteList: function(list) {
		if (!list) {
			return;
		}
		var template = $('.list_element_template').html();
		var host = $('tbody.oppenents_list');
		for (var i in list) {
			host.append(template);
			$('.list_template_raw', host).removeClass('list_template_raw').addClass('list_line_' + list[i].id);
			var line = $('.list_line_' + list[i].id);
			for (var k in list[i]) {

			}
		}
	},
	setDialogCSS: function() {
		A.w(['D'], function() {
			D.defaultCSS.login.dialog_back = 'none';
			D.defaultCSS.login.message_back = {
				background: 'none' //'rgba(43,49,56,0.9)'
			};

			D.defaultCSS.norm.message_back = {
				background: 'rgba(65,105,225,0.8)'
			};

		});
		/*D.defaultCSS.login.add_but = {
		 'margin-right': '0px'
		 };*/
	},
	reload: function() {
		window.location.reload(true);
	},
	error: function(input) {
		D.show({
			title: input.message,
			message: false,
			css: D.getCss({type: 'no'}),
			exit: function() {
				D.hide(function() {
					if (input.callback) {
						for (var method in input.callback) {
							if (User[method]) {
								User[method](input.callback[method]);
								break;
							}
						}
					}
				}, false, 'slide');
			}
		});
	},
	initImages: function(readonly) {
		var that = this;

		Site.initImages({
			readonly: readonly
					? 1
					: 0,
			onExit: readonly
					? that.imageActions.onExitReadonly
					: that.imageActions.onExit,
			onDelete: that.imageActions.onDelete
		});

	},
	changeAvatar: function(input) {
		User.data.photo = input.image;
		
		$('img.user_avatar').attr({
			src: input.image
		});
		
		$('div.user_avatar').css({
			'background-image' : "url('" + input.image + "')"
		});
		
		$('.userpic').attr({
			src: input.image
		});
	},
	chooseAvatar: function(input) {
		var that = this;
		D.show({
			title: {
				select_picture_avatar: {
					en: 'Select image to use as avatar',
					ru: 'Выберите изображение для аватара'
				}
			},
			message: 'pages/user/select_avatar.php',
			onShow: function() {
				$('.for_avatar.uploaded_files_host').html(input.files);
				Site.initImages({
					click: function(data) {
						D.hide();
						B.post({
							r: 'user/main',
							User: 'set_avatar',
							image_id: data.image_id * 1
						});
					},
					onExit: function() {
						that.chooseAvatar();
					}
				});
			},
			css: D.getCss({type: 'norm'}),
			buttons: {
				'delete': {
					image: A.baseURL() + 'images/del.png',
					action: function() {
						D.hide();
						B.post({
							r: 'user/main',
							User: 'no_avatar'
						});
					}
				}
			}
		});
	},
	showUserProfile: function(input) {
		var that = this;
		D.show({
			title: input.name,
			css: D.getCSS({type: 'norm'}),
			message: input.html,
			onShow: function() {
				Site.initImages({
					readonly: 1,
					onExit: function() {
						that.showUserProfile(input);
					}
				});
			}
		});
	},
	showImage: function(input) {
		var that = this;

		if (!input.onExit) {
			input.onExit = input.readonly
					? that.imageActions.onExitReadonly
					: that.imageActions.onExit;
		}

		if (!input.onDelete) {
			input.onDelete = that.imageActions.onDelete;
		}

		Site.showImage(input);
	},
	outputUploadedFiles: function(input) {
		//TODO: use it in profile
		var that = this;
		var host = $('.uploaded_files_host');
		$('.image_preview_host', host).remove();
		host.append(input.html);
		that.initImages();
	},
	onDelete: function(id) {
		//console.log('we are here');
		D.hide();
		B.get({
			r: 'user/main',
			User: 'profile'
		});
	},
	imageActions: {
		onDelete: function(id, image_id) {
			B.post({
				r: 'user/main',
				User: 'delete_uploaded',
				image_id: image_id * 1
			});
		},
		onExit: function(id) {
			D.hide();
			B.get({
				r: 'user/main',
				User: 'profile'
			});
		},
		onExitReadonly: function(id) {
			//where to return after exit from readonly
		}
	}
};