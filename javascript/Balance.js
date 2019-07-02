var Withdraw = {
	init: function() {
		Balance.init();
	}
};

var Balance = {
	onShow: function() {
		this.updateWithdraw();
	},
	updateWithdraw: function(input) {

		if ($('.withdraw_page').is(':visible')) {
			B.get({
				r: 'user/main',
				Offer: 'my_withdrawal_requests'
			});
			$('.indicator_withdraw').hide();
		} else if (input && input.notification == 'seller') {
			$('.indicator_withdraw').fadeOut(function() {
				$('.indicator_withdraw').show();
			});
		}

		if ($('.balance_page').is(':visible')) {
			B.get({
				r: 'user/main',
				Offer: 'get_accepted'
			});
			$('.indicator_fillup').hide();
		} else if (input && input.notification == 'customer') {
			$('.indicator_fillup').fadeOut(function() {
				$('.indicator_fillup').show();
			});
		}
	},
	init: function() {
		var that = this;
		$('.fill_balance_amount').unbind('change').change(function() {
			that.control();
		}).unbind('keyup').keyup(function() {
			that.control();
		}).unbind('mouseup').mouseup(function() {
			that.control();
		});

		that.control();

		this.getWithdrawalMethods();
		this.onShow();
	},
	outputWithdrawalMethods: function(input) {
		var that = this;

		$('.withdrawal_methods_host').html(input.html);

		$('.withdraw_amount').unbind('change').change(function() {
			that.control();
		}).unbind('keyup').keyup(function() {
			that.control();
		}).unbind('mouseup').mouseup(function() {
			that.control();
		});

		that.control();

	},
	loadUserPaymentMethod: function(input) {

		$('.user_payment_method_id').val(input.id);

		//$('.secret_key_for_autopayment').html(input.secret_key);

		$('.payment_type_details').val(input.mode == 'automatic'
				? ''
				: input.description);
		$('.payment_type_default').val(input.description);
		$('.payment_page_url').val(input.mode == 'automatic'
				? input.description
				: '');

		if (input && input.mode == 'automatic') {
			$('.manual_payment_host').hide();
			$('.automatic_payment_host').show();
			$('.automatic_payment').prop({
				checked: true
			});
		} else { //by default manual
			$('.manual_payment_host').show();
			$('.automatic_payment_host').hide();
			$('.manual_payment').prop({
				checked: true
			});
		}

		B.click($('.remove_payment_method'), function() {
			B.post({
				UserPaymentMethod: 'delete',
				id: $('.user_payment_method_id').val()
			});
		});

	},
	editWithdrowalMethod: function(input) {

		//console.log(input);

		var that = this;
		D.show({
			title: input.title,
			message: 'pages/dialogs/withdrawal.php?payment_id=' + input.payment.payment_id,
			css: D.getCss({type: 'norm'}),
			buttons: {
				save: {
					image: A.baseURL() + 'images/go.png',
					action: function() {

					}
				}
			},
			onShow: function() {

				$('.payment_method_id').val(input.payment.payment_id);
				$('.withdraw_price').val('');
				$('.withdraw_amount_correction').val($('.withdraw_amount').val());

				that.loadUserPaymentMethod(input.userMethod);

				//console.log(input);

				if (!input.payment.currency) {
					$('.withdraw_nominal').hide();
					console.error('Need to set up currency');
				}

				$('.auto_manual').unbind('click').click(function() {
					if ($('.manual_payment').is(':checked')) {
						$('.manual_payment_host').show();
						$('.automatic_payment_host').hide();
					} else {
						$('.manual_payment_host').hide();
						$('.automatic_payment_host').show();
					}
				});

				//create switcher

				//console.log(input.userMethodsIDS);

				if (input.userMethodsIDS) {

					var h = [];
					var count = 0;
					for (var i in input.userMethodsIDS) {
						h.push('<input type="radio" class="load_userpayment" name="all_methods" style="width:20px;" value="' + (input.userMethodsIDS[i]) + '" ' +
								(input.userMethodsIDS[i] == input.userMethod.id
										? 'checked="checked"'
										: '') + '/>');
						count += 1;
					}

					if (count > 1) {
						$('.available_methods_host').html(h.join(''));
						$('.available_methods_header').show();
						$('.load_userpayment').unbind('change').change(function() {
							if ($(this).is(':checked')) {
								B.get({
									UserPaymentMethod: 'load',
									id: $(this).val()
								});
							}
						});
					} else {
						$('.available_methods_header').hide();
					}

				} else {
					$('.available_methods_header').hide();
				}

				that.setPaymentControl($('.payment_control'));



				//help setup

				$('.goto_help').unbind('click').click(function() {
					var id = B.getID($(this))
					A.w(['Help'], function() {
						Help.goto(id);
					});
				});

			}
		});
	},
	disablePaymentMethod: function(input) {
		if (input.action == 'disable') {
			$('.payment_method_host.withdraw.id_' + input.id).removeClass('available_method').css({
				opacity: $('.withdraw_amount').val() * 1
						? 0.6
						: 0.6
			});
		} else {
			$('.payment_method_host.withdraw.id_' + input.id).removeClass('available_method').addClass('available_method').css({
				opacity: $('.withdraw_amount').val() * 1
						? 1
						: 0.6
			});
		}
	},
	changeRequestedAmount: function(amount) {
		$('.fill_balance_amount').val(amount.amount);
		this.control();
	},
	setPaymentControl: function(init) {
		var that = this;

		if (init) {
			init.unbind('change').change(function() {
				that.setPaymentControl();
			}).unbind('keyup').keyup(function() {
				that.setPaymentControl();
			}).unbind('mouseup').mouseup(function() {
				that.setPaymentControl();
			});
		}

		B.forceToInt($('.withdraw_wait'), 0, $('.withdraw_wait').attr('max') * 1);
		B.forceToFloat($('.withdraw_amount_correction'), 0, User.data.money * 1);
		B.forceToFloat($('.withdraw_price'), 0);

		if ($('.manual_payment').is(':checked')) {

			if ($('.withdraw_wait').val() < $('.withdraw_wait').attr('min') * 1) {
				$('.withdraw_wait').css({
					color: 'red'
				});
			} else {
				$('.withdraw_wait').css({
					color: 'black'
				});
			}

			$('.wt_76490').html(Math.max($('.withdraw_wait').attr('min'), $('.withdraw_wait').val()));

			if ($('.withdraw_nominal').val()) {
				$('.need_currency_name_reminder').hide();
			} else {
				$('.need_currency_name_reminder').show();
			}

			if ($('.read_and_understand').is(':checked') &&
					$('.withdraw_amount_correction').val() * 1 > 0 &&
					$('.withdraw_price').val() * 1 > 0 &&
					$('.withdraw_wait').val() * 1 > 0 &&
					$('.withdraw_nominal').val() &&
					$('.payment_type_details').val()) {

				B.click($('.button_save').css({
					opacity: 1,
					cursor: 'pointer'
				}), function(obj) {
					D.hide();
					B.post({
						r: 'user/main',
						UserPaymentMethod: 'set',
						wait: $('.withdraw_wait').val() * 1,
						changed: $('.payment_type_details').val() != $('.payment_type_default').val(),
						withdraw: $('.withdraw_amount_correction').val() * 1,
						price: $('.withdraw_price').val() * 1,
						payment_id: $('.payment_method_id').val(),
						currency: $('.withdraw_nominal').val(),
						description: $('.payment_type_details').val(),
						mode: 'manual'
					});
				});

			} else {
				$('.button_save').css({
					opacity: 0.5,
					cursor: 'default'
				}).unbind('click');
			}

		} else { //automatic mode

			if ($('.withdraw_nominal').val()) {
				$('.need_currency_name_reminder').hide();
			} else {
				$('.need_currency_name_reminder').show();
			}


			if ($('.withdraw_amount_correction').val() * 1 > 0 &&
					$('.withdraw_price').val() * 1 > 0 &&
					$('.withdraw_nominal').val() &&
					$('.payment_page_url').val() /*&&
					 $('.confirmation_page_url').val()*/
					) {

				B.click($('.button_save').css({
					opacity: 1,
					cursor: 'pointer'
				}), function(obj) {
					D.hide();
					B.post({
						r: 'user/main',
						UserPaymentMethod: 'set',
						withdraw: $('.withdraw_amount_correction').val() * 1,
						price: $('.withdraw_price').val() * 1,
						payment_id: $('.payment_method_id').val(),
						currency: $('.withdraw_nominal').val(),
						description: $('.payment_page_url').val(),
						mode: 'automatic'
					});
				});

			} else {
				$('.button_save').css({
					opacity: 0.5,
					cursor: 'default'
				}).unbind('click');
			}

		}
	},
	getWithdrawalMethods: function() {
		var that = this;
		A.w(['User'], function() {
			if (User.data) {
				B.get({
					r: 'user/main',
					UserPaymentMethod: 'get'
				});
			} else {
				setTimeout(function() {
					that.getWithdrawalMethods();
				}, 100)
			}
		});
	},
	//вывод заявок на вывод
	outputOffersList: function(list) {
		var that = this;

		if (list && list.waiting) {
			$('.my_withdraw_request').show();
			$('.offers_list', $('.my_withdraw_request')).html(list.waiting);

			B.click($('.cancel_offer', $('.withdraw_host')), function(obj) {

				D.show({
					title: {
						are_you_sure_to_cancel_withdraw_order: {
							'en': 'Are you sure you want to cancel the withdrawal request?',
							'ru': 'Вы уверены что хотите отменить заявку на вывод средств?'
						}
					},
					message: false,
					css: D.getCss({type: 'norm'}),
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide(function() {
									B.post({
										r: 'user/main',
										Offer: 'cancel_by_seller',
										offer_id: B.getID(obj, 'id_'),
										update_sell_board: $('.available_offers_host').is(':visible')
												? that.selected_payment_type
												: 0
									});
								});
							}
						}
					}
				});

			});


		} else {
			$('.my_withdraw_request').hide();
		}

		if (list && list.accepted) {
			$('.offers_list', $('.my_accepted_withdraw_requests').show()).html(list.accepted);
		} else {
			$('.my_accepted_withdraw_requests').hide();
		}

		if (list && list.confirmed) {
			$('.offers_list', $('.my_confirmed_withdraw_requests').show()).html(list.confirmed);

			B.click($('.check_payment', $('.withdraw_host')), function(obj) {
				B.post({
					r: 'user/main',
					Offer: 'payment_details',
					seller_check: 1,
					offer_id: B.getID(obj, 'id_')
				});
			});

		} else {
			$('.my_confirmed_withdraw_requests').hide();
		}

		if (list && list.disputed) {
			$('.withdraw_disputed_offers_host').show();
			$('.withdraw_disputed_offers_list').html(list.disputed);

			B.click($('.cancel_chargeback'), function(obj) {
				D.show({
					title: {
						are_you_sure_to_cancel_claim_and_continue_transaction: {
							en: 'Are you sure to cancel the claim?',
							ru: 'Вы уверены что снимаете претензию?'
						}
					},
					message: false,
					css: D.getCss({type: 'ok'}),
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Offer: 'cancel_chargeback',
									offer_id: B.getId(obj)
								});
							}
						}
					}
				});
			});

			B.click($('.view_claim'), function(obj) {
				B.get({
					r: 'user/main',
					Offer: 'view_claim',
					offer_id: B.getId(obj)
				});
			});

		} else {
			$('.withdraw_disputed_offers_host').hide();
		}

		//run self timer
		if (list && (list.confirmed || list.accepted || list.disputed)) {
			that.restartRemainTimer();
		}

		if (list && (list.balance || list.balance === 0)) {
			User.data.money = list.balance;
			$('.user_balance_detector').html(User.data.money + '$');
		}

		$('.request_line .user').unbind('click').click(function() {
			B.get({
				User: 'view',
				user_id: B.getID($(this)),
				heIs: $(this).hasClass('heIsSeller')
						? 'seller'
						: 'buyer'
			});
		});

	},
	remainTimer: false,
	updateTime: false,
	restartRemainTimer: function() {
		clearTimeout(this.remainTimer);
		this.remainTimer = false;
		this.updateTime = Date.now() / 1000;

		//check ans synchronize all timers
		var same = {};

		$('.time_remain2').each(function() {
			var id = B.getId($(this));
			if (same[id]) {
				if (same[id].attr('timestamp') * 1 < $(this).attr('timestamp') * 1) {
					var min = same[id].attr('timestamp') * 1;
				} else {
					var min = $(this).attr('timestamp') * 1;
				}
				$('.time_remain.id_' + id).attr({
					timestamp: min
				});
			} else {
				same[id] = $(this);
			}
		});

		this.showRemainTime();
	},
	alreadyUpdated: {},
	showRemainTime: function() {
		var that = this;
		var pass = Date.now() / 1000 - that.updateTime;
		$('.time_remain2').each(function() {
			var timestamp = $(this).attr('timestamp') * 1 - pass;

			if (timestamp < 0) {

				var id = B.getID($(this));

				if (!that.alreadyUpdated[id]) { //to prevent double updating
					that.updateWithdraw();
					that.alreadyUpdated[id] = true;
				}

				$('.request_line.id_' + id).hide();

				if ($('.time_remain.id_' + id, $('.time_remain_host')).is(':visible')) {
					D.hide();
				}

			} else {
				var date = new Date(timestamp * 1000);
				$(this).html(('0' + (date.getUTCHours() + date.getUTCDay() * 24)).slice(-2) + ':' + ('0' + date.getUTCMinutes()).slice(-2) + ':' + ('0' + date.getUTCSeconds()).slice(-2));
			}
		});

		that.remainTimer = setTimeout(function() {
			that.showRemainTime();
		}, 1000);
	},
	withdraw_methods: {},
	getWithdraw_methods: function() {
		var that = this;
		var result = [];
		for (var i in that.withdraw_methods) {
			if (that.withdraw_methods[i]) {
				result.push(i);
			}
		}
		return result;
	},
	showChargeBackDialog: function(input) {
		var that = this;

		//console.log(input);

		B.get({
			r: 'user/main',
			Offer: 'get_chargeback',
			offer_id: input.offer_id,
			ok: function(response) {

				D.show({
					title: input.isCounter
							? {
								'Are you sure to run counterclaim': {
									en: 'You disagree with the claim and want to put a counter claim?',
									ru: 'Вы не согласны с претензией и хотите выставить встречную?'
								}
							}
					: {
						'sure_to_run_cahellenge': {
							en: 'Are you sure to run challenge deal mechanism?',
							ru: 'Вы уверены что хотите запустить процедуру отмены сделки?'
						}
					},
					exit: input.onExit
							? function() {
								input.onExit();
							}
					: false,
					onShow: function() {
						$('.chargeback_host').html(response.html);

						Site.restoreFormParameters(input.obj
								? input.obj
								: 'Offer');

						A.w(['Site'], function() {

							Site.initImages({
								onExit: function() {
									that.showChargeBackDialog(input);
								},
								onDelete: function(id, image_id) {

									var post = {
										r: 'user/main',
										image_id: image_id * 1,
										offer_id: input.offer_id,
										ok: function() {
											that.showChargeBackDialog(input);
										}
									};

									if (input.obj) {
										post[input.obj] = 'delete_uploaded';
									} else {
										post['Offer'] = 'delete_uploaded';
									}

									B.post(post);
								},
								get: {
									chargeback: input.offer_id
								}
							});

							that.showChargeBackData = input;

							Site.initFileUploader({
								obj: input.obj
										? input.obj
										: 'Offer',
								id: input.offer_id,
								get: {
									chargeback: input.offer_id
								}
							});

							that.controlRunArbitrage({
								id: input.offer_id
							});

							$('.controlRunArbitrage').unbind('change').change(function() {
								that.controlRunArbitrage({
									id: input.offer_id
								});
							}).unbind('keyup').keyup(function() {
								that.controlRunArbitrage({
									id: input.offer_id
								});
							}).unbind('mouseup').mouseup(function() {
								that.controlRunArbitrage({
									id: input.offer_id
								});
							});

						});
					},
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {

							}
						}
					},
					message: '<div class="chargeback_host"></div>',
					css: D.getCss({type: 'no'})
				});

			}
		});


	},
	controlRunArbitrage: function(input) {

		var id = input.id;

		if ($('.challenge_reason').val() && ($('.challenge_reason').hasClass('Deal_claim') || $('.chargeback_host .image_preview_host').length)) {
			B.click($('.button_ok').unbind('click').css({
				opacity: 1,
				cursor: 'pointer'
			}), function(obj) {
				D.hide();
				B.post({
					r: 'user/main',
					Offer: 'run_chargeback',
					id: id,
					claim: $('.challenge_reason').val()
				});

			});
		} else {
			$('.button_ok').unbind('click').css({
				opacity: 0.5,
				cursor: 'default'
			});
		}
	},
	chargeBackShowImage: function(input) {
		var that = this;

		Site.showImage($.extend(input, {
			onExit: function() {
				that.showChargeBackDialog(that.showChargeBackData);
			},
			onDelete: function(id, image_id) {

				if (input.obj && input.obj == 'Deal') {

					B.post({
						r: 'user/main',
						Deal: 'delete_uploaded',
						offer_id: id,
						image_id: image_id * 1,
						ok: function() {
							that.showChargeBackDialog(that.showChargeBackData);
						}
					});

				} else {

					B.post({
						r: 'user/main',
						Offer: 'delete_uploaded',
						offer_id: id,
						image_id: image_id * 1,
						ok: function() {
							that.showChargeBackDialog(that.showChargeBackData);
						}
					});

				}
			}
		}));
	},
	chargeBackUploadedFiles: function(input) {
		var that = this;
		var host = $('.uploaded_files_host');
		$('.image_preview_host', host).remove();
		host.append(input.html);

		Site.initImages({
			onExit: function() {
				that.showChargeBackDialog(that.showChargeBackData);
			},
			onDelete: function(id, image_id) {

				var post = {
					r: 'user/main',
					offer_id: id,
					image_id: image_id * 1,
					ok: function() {
						that.showChargeBackDialog(that.showChargeBackData);
					}
				};

				post[input.obj
						? input.obj
						: 'Offer'] = 'delete_uploaded';

				B.post(post);
			},
			get: {
				chargeback: input.offer_id
			}
		});

		//recontrol buttons
		that.controlRunArbitrage({
			id: input.offer_id
		});
	},
	selected_payment_type: false,
	//show payment details and confirmation status
	showPaymentDetails: function(input) {
		var that = this;

		D.show({
			title: input.title,
			message: input.message,
			cls: 'dialog-asphalt',
			onShow: function() {
				that.initSendConfirmationButton();

				that.initImages(input.mode == 'seller'
						? 1
						: 0);
				that.initConfirmAgainButton(input);

				that.restartRemainTimer();

				A.w(['Site'], function() {
					Site.initFileUploader({
						obj: 'Offer',
						id: input.offer_id
					});
				});

				B.click($('.run_challenge'), function(obj) {
					D.hide(function() {
						that.showChargeBackDialog({
							offer_id: B.getId(obj),
							onExit: function() {
								D.hide(function() {
									that.showPaymentDetails(input);
								}, false, 'slide');
							}
						});
					}, false, 'slide');
				});
			}
		});

	},
	initConfirmAgainButton: function(input) {
		var that = this;
		B.click($('.ask_confirmation'), function(obj) {
			D.hide(function() {
				that.showReconfirmDialog(B.getId(obj, 'offer_id_'), input);
			});
		});

		B.click($('.release_balance'), function(obj) {
			D.hide(function() {
				D.show({
					title: {
						sure_that_release_funds: {
							en: 'You receive the required amount and ready to release guarantee balance?',
							ru: 'Вы получили требуемую сумму и готовы выпустить гарантийный балланс?'
						}
					},
					exit: function() {
						D.hide(function() {
							that.showPaymentDetails(input);
						}, false, 'slide');
					},
					message: '<div class="msg_1278"></div>',
					onShow: function() {
						T.translate({
							once_you_release: {
								en: 'Please be careful! The operation is irreversible, and it is impossible to challenge!',
								ru: 'Будьте внимательны! Операция необратима, и ее невозможно оспорить!'
							}
						}, $('.msg_1278'));
					},
					css: D.getCss({type: 'no'}),
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide(function() {
									B.post({
										r: 'user/main',
										Offer: 'release',
										offer_id: B.getID(obj, 'offer_id_'),
										update_sell_board: $('.available_offers_host').is(':visible')
												? that.selected_payment_type
												: 0
									});
								});
							}
						}
					}
				});
			}, false, 'slide');
		});

	},
	showReconfirmDialog: function(offer_id, input) {
		var that = this;
		D.show({
			title: {
				reconfirm_title: {
					en: 'Ask buyer to confirm payment once again',
					ru: 'Попросите покупателя подтвердить платеж еще раз'
				}
			},
			exit: function() {
				D.hide(function() {
					that.showPaymentDetails(input);
				}, false, 'slide');
			},
			css: D.getCss({type: 'norm'}),
			message: 'pages/dialogs/reconfirm_message.php',
			buttons: {
				ok: {
					image: A.baseURL() + 'images/go.png',
					action: function() {
						D.hide(function() {
							B.post({
								r: 'user/main',
								Offer: 'ask_confirmation',
								offer_id: offer_id,
								message: $('.dialog .message_to_buyer').val(),
								update_sell_board: $('.available_offers_host').is(':visible')
										? that.selected_payment_type
										: 0
							});
						});
					}
				}
			}
		});
	},
	viewClaim: function(input) {

		var that = this;

		D.show({
			title: {
				view_claim_title2: {
					en: 'Your partner has run chargeback procedure, his claim:',
					ru: 'Ваш партнер запустил процедуру отмены сделки, его претензия:'
				}
			},
			message: input.message || false,
			css: D.getCss({type: 'no'}),
			onShow: function() {

				that.initImages(function() {
					that.viewClaim(input);
				});

				that.restartRemainTimer();

				B.click($('.run_challenge'), function(obj) {
					D.hide(function() {
						that.showChargeBackDialog({
							offer_id: B.getId(obj),
							onExit: function() {
								D.hide(function() {
									that.viewClaim(input);
								}, false, 'slide');
							}
						});
					}, false, 'slide');
				});

				B.click($('.accept_claim'), function(obj) {
					D.hide(function() {
						D.show({
							title: {
								sure_to_accept_claim: {
									en: 'Are you sure to accept the claim?',
									ru: 'Вы уверены что признаете претензию?'
								}
							},
							css: D.getCss({type: 'ok'}),
							buttons: {
								ok: {
									image: A.baseURL() + 'images/go.png',
									action: function() {
										D.hide();
										B.post({
											r: 'user/main',
											Offer: 'accept_claim',
											offer_id: B.getID(obj)
										});
									}
								}
							}
						});
					}, false, 'slide');

				});

			}
		});

	},
	initSendConfirmationButton: function() {
		if ($('.image_preview_host').length && $('.image_preview_host').is(':visible')) {
			$('.button_confirm').css({
				opacity: 1,
				cursor: 'pointer'
			}).unbind('click').click(function() {
				var obj = $(this);
				B.press(obj);
				D.hide(function() {
					B.post({
						r: 'user/main',
						Offer: 'send_payment_confirmation',
						offer_id: B.getID(obj, 'id_')
					});
				});
			});
		} else {
			$('.button_confirm').css({
				opacity: 0.6,
				cursor: 'default'
			}).unbind('click');
		}
	},
	findOffers: function(input, showPreloader) {
		
		B.get({
			r: 'user/main',
			Offer: 'get_sell',
			page: input.page
					? input.page
					: 0,
			payment_id: input.payment_id
					? input.payment_id
					: null,
			filter: $('input:radio[name="currency_nominal_filter"]:checked').val(),
			amount: $('.fill_balance_amount').val() * 1,
			initiated: 1
		}, showPreloader); 
		
	},
	//show selling offers and accepted
	showAvailableOffers: function(data) {

		var that = this;

		if (data && (data.balance || data.balance === 0)) {
			User.data.money = data.balance * 1;
			$('.user_balance_detector').html(User.data.money + '$');
		}

		//простановка статусов кнопок
		if (data && data.payment_id * 1) {

			that.selected_payment_type = data.payment_id;

			$('.button_payment_method_host .payment_method_host', $('.fill_up_host')).removeClass('active');

			if (data.payment_id) {
				$('.button_payment_method_host .payment_method_host.id_' + data.payment_id, $('.fill_up_host')).addClass('active');
			}

		}

		//селектор валют
		if (data && data.currencies) {
			$('.currency_filter_host').html(data.currencies).show();

			if ($('.currency_nominal_filter').length) {
				$('.currency_nominal_filter').unbind('change').change(function() {

					$('.available_offers_list').slideUp();

					that.findOffers({
						page: 0
					},true);

					//Site.scrollTo($('.fillup_header'));
				});
			}

		} else {
			$('.currency_filter_host').hide();
		}

		//список продаваемых валют
		if (data && data.selling) {

			$('.available_offers_host').slideDown();
			$('.available_offers_list').html(data.selling).slideDown();

			//pagination has been pressed
			$('.goto_page.selling_offers').unbind('click').click(function() {
				that.findOffers({
					page: B.getID($(this))
				},true);
			});

			B.click($('.hide_offer'), function(obj) {
				var id = B.getID(obj, 'id_');

				D.show({
					title: {
						sure_that_spam: {
							en: 'Are you sure that payment details are wrong or this is spam?',
							ru: 'Вы уверены что платежные реквизиты неправильные или это спам?'
						}
					},
					message: 'pages/dialogs/spam_report.php',
					css: D.getCss({type: 'no'}),
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Hide: 'hide',
									offer_id: id,
									description: $('.spam_report').val()
								});
							}
						}
					}
				});

			});

			/*
			 B.click($('.show_balance_offer'), function(obj){
			 var id = B.getID(obj, 'id_');
			 
			 $('.offer_balance_description').slideUp();
			 
			 $('.offer_balance_description.id_'+id).slideDown();
			 
			 });	*/

			B.click($('.accept_offer'), function(obj) {
				var id = B.getID(obj, 'id_');


				if ($('.fill_balance_amount').val() * 1 <= 0) {

					D.show({
						title: 'pages/dialogs/error.php?error=amount_expected_not_zero',
						message: false,
						css: D.getCss({type: 'no'})
					});

				} else {

					if (obj.hasClass('disableShow')) {
						return;
					}

					obj.addClass('disableShow');

					D.show({
						title: 'pages/dialogs/confirmation.php?offer_id=' + id + '&type=accept_balance_fillup_offer&requested_amount=' +
								$('.fill_balance_amount').val() * 1 +
								//'&filled_amount=' + has_amount +
								'&method=' + that.selected_payment_type,
						buttons: {
							ok: {
								image: A.baseURL() + 'images/go.png',
								action: function() {
									D.hide(function() {
										B.post({
											r: 'user/main',
											Offer: 'accept',
											id: id,
											amount: $('.fill_balance_amount').val() * 1,
											payment_id: that.selected_payment_type
										});
									});
								}
							}
						},
						onShow: function() {

							obj.removeClass('disableShow');

							$('.viewUser.fromAcceptForm').unbind('click').click(function() {
								var id = B.getId($(this));

								if ($('.contragent_details').hasClass('isEmpty')) {
									B.get({
										User: 'view',
										user_id: B.getID($(this)),
										heIs: 'seller',
										ok: function(response) {
											$('.contragent_details').html(response.User.showUserProfile.html).slideDown().removeClass('isEmpty');
										}
									});
								} else if ($('.contragent_details').is(':visible')) {
									$('.contragent_details').slideUp();
								} else if (!$('.contragent_details').is(':visible')) {
									$('.contragent_details').slideDown();
								}

							});


							B.click($('.hide_offer'), function(obj) {
								var id = B.getID(obj, 'id_');
								D.hide(function() {
									D.show({
										title: {
											sure_that_spam: {
												en: 'Are you sure that payment details are wrong or this is spam?',
												ru: 'Вы уверены что платежные реквизиты неправильные или это спам?'
											}
										},
										message: 'pages/dialogs/spam_report.php',
										css: D.getCss({type: 'no'}),
										buttons: {
											ok: {
												image: A.baseURL() + 'images/go.png',
												action: function() {
													D.hide();
													B.post({
														Hide: 'hide',
														offer_id: id,
														description: $('.spam_report').val()
													});
												}
											}
										}
									});

								});
							});

						},
						message: 'pages/dialogs/confirmation.php?offer_id=' + id + '&type=not_enough_money_notification&requested_amount=' +
								$('.fill_balance_amount').val() * 1 +
								'&method=' + that.selected_payment_type,
						css: D.getCss({type: 'blue'})
					});

				}
			});

		} else {
			
			if (data && !data.selling && data.initiated) {
				$('.available_offers_host').show();
				A.w(['T'], function() {
					T.translate({
						no_offers_have_found2: {
							en: '<span style="color:red;">No offers. Try another payment menthod.</span>',
							ru: '<span style="color:red;">Нет предложений. Попробуйте другой способ оплаты.</span>'
						}
					}, $('.available_offers_list'));
				});
			} else {
				//$('.available_offers_host').hide();
			}
		}

		if (data && data.confirmed) {
			$('.confirmed_offers_host').show();
			$('.confirmed_offers_list').html(data.confirmed);

			B.click($('.reload_confirmation', $('.fill_up_host')), function(obj) {
				that.confirmByByer({
					id: B.getID(obj, 'id_'),
					confirmed: 1
				});
			});

		} else {
			$('.confirmed_offers_host').hide();
		}

		if (data && data.accepted) {
			$('.accepted_offers_host').show();
			$('.accepted_offers_list').html(data.accepted);

			B.click($('.info_offer'), function(obj) {
				B.post({
					r: 'user/main',
					Offer: 'payment_details',
					offer_id: B.getID(obj, 'id_')
				});
			});

			B.click($('.confirm_payment'), function(obj) {
				that.confirmByByer({
					id: B.getID(obj, 'id_')
				});
			});
		} else {
			$('.accepted_offers_host').hide();
		}

		if (data && data.disputed) {
			$('.disputed_offers_host').show();
			$('.disputed_offers_list').html(data.disputed);

			B.click($('.cancel_chargeback'), function(obj) {
				D.show({
					title: {
						are_you_sure_to_cancel_claim_and_continue_transaction: {
							en: 'Are you sure to cancel the claim?',
							ru: 'Вы уверены что снимаете претензию?'
						}
					},
					message: false,
					css: D.getCss({type: 'ok'}),
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									r: 'user/main',
									Offer: 'cancel_chargeback',
									offer_id: B.getId(obj)
								});
							}
						}
					}
				});
			});

			B.click($('.view_claim'), function(obj) {
				B.get({
					r: 'user/main',
					Offer: 'view_claim',
					offer_id: B.getId(obj)
				});
			});

		} else {
			$('.disputed_offers_host').hide();
		}

		/*
		 if (data && data.completed) {
		 $('.completed_host').show();
		 $('.completed_list').html(data.completed);
		 } else {
		 $('.completed_host').hide();
		 } */

		if (data && (data.accepted || data.confirmed || data.disputed)) {

			that.restartRemainTimer();

			B.click($('.claim_automatic_offer', $('.fill_up_host')), function(obj0) {

				D.show({
					title: {
						you_transfer_money_but_balance_is_not_filled: {
							en: 'You transferred money but the balance is not filled?',
							ru: 'Вы перевели деньги но баланс не пополнен?'
						}
					},
					message: 'pages/balance/claim_automatic_payment.php',
					//css: D.getCss({type: 'asphalt'}),
					cls: 'dialog-asphalt',
					onShow: function() {

						B.click($('.claim_automatic_payment .just_cancel_offer'), function(obj) {
							D.hide(function() {
								B.post({
									r: 'user/main',
									Offer: 'cancel_by_byer',
									offer_id: B.getID(obj0),
									update_sell_board: $('.available_offers_host').is(':visible')
											? that.selected_payment_type
											: 0
								});
							});
						});

						B.click($('.claim_automatic_payment .request_refund'), function(obj) {
							D.hide(function() {
								that.showChargeBackDialog({
									offer_id: B.getId(obj0)
								});
							}, false, 'slide');
						});

					}
				});


			});


			B.click($('.cancel_offer', $('.fill_up_host')), function(obj) {
				D.show({
					title: {
						sure_that_you_want_to_cancel_the_order7: {
							en: 'Are you sure to cancel fill up request?',
							ru: 'Вы уверены, что хотите отменить заявку на пополнение?'
						}
					},
					message: 'pages/dialogs/confirmation.php?type=cancel_fillup_order_red_message',
					css: D.getCss({type: 'no'}),
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide(function() {
									B.post({
										r: 'user/main',
										Offer: 'cancel_by_byer',
										offer_id: B.getID(obj),
										update_sell_board: $('.available_offers_host').is(':visible')
												? that.selected_payment_type
												: 0
									});
								});
							}
						}
					}
				});
			});
		}

		$('.request_line .user').unbind('click').click(function() {
			B.get({
				User: 'view',
				user_id: B.getID($(this)),
				heIs: $(this).hasClass('heIsSeller')
						? 'seller'
						: 'buyer'
			});
		});


	},
	gotoPaymentPage: function(input) {
		location.href = input.url;
	},
	confirmByByer: function(input) {
		var that = this;
		B.get({
			r: 'user/main',
			Offer: 'confirm_by_byer',
			offer_id: input.id,
			confirmed: input.confirmed || 0,
			update_sell_board: $('.available_offers_host').is(':visible')
					? that.selected_payment_type
					: 0
		});
	},
	changeOffersBecauseChangeAmountTimer: false,
	changeOffersBecauseChangeAmount: function(payment_id) {
		var that = this;
		if (that.changeOffersBecauseChangeAmountTimer) {
			clearTimeout(that.changeOffersBecauseChangeAmountTimer);
		}

		that.changeOffersBecauseChangeAmountTimer = setTimeout(function() {
			that.findOffers({
				payment_id: payment_id,
				page: 0
			}, false);
		}, 500);
	},
	control: function() {

		var that = this;

		if (window.Comission) {
			var comission = Math.max(Comission.minimum, $('.fill_balance_amount').val() * Comission.value);
			var tobalance = Math.max(0, $('.fill_balance_amount').val() - comission);
		} else {
			var comission = 0;
			var tobalance = 0;
		}

		if ($('.fill_balance_amount').length) {
			B.forceToFloat($('.fill_balance_amount'), 0);
		}
		
		if ($('.fill_balance_amount_default').val()*1 != $('.fill_balance_amount').val()*1) {

			
			//console.log($('.fill_balance_amount').val() * 1);
			
			//дозагрузка данных при изменении стоимости

			if ($('.fill_up_host .payment_method_host.active').length) { //какая-либо кнопка активирована
				if ($('.fill_balance_amount').val() * 1 > 0) {
					that.changeOffersBecauseChangeAmount(B.getID($('.fill_up_host .payment_method_host.active')));
				}
			}

			$('.fill_balance_amount_default').val($('.fill_balance_amount').val());


			if ($('.fill_balance_amount').val() * 1 > 0) {

				A.w(['Comission'], function() {
					$('.credited_b', $('.comission_indicator_host').css({
						visibility: 'visible'
					})).html(B.round(tobalance, 0.01));

					$('.comission_b').html(B.round(comission, 0.01));
				});

				if (tobalance > 0) {

					B.click($('.fill_up_host .payment_method_host').removeClass('available').addClass('available'), function(obj) {


						obj.removeClass('active').addClass('active');
						$('.available_offers_list').slideUp();

						that.findOffers({
							payment_id: B.getId(obj, 'id_'),
							page: 0
						}, true);

						//Site.scrollTo($('.fillup_header'));
					});

					/*
					if (that.selected_payment_type) {
						$('.payment_method_host.id_' + that.selected_payment_type, $('.fill_up_host')).css({
							opacity: 1
						});
					} */

				} else {

					B.click($('.fill_up_host .payment_method_host').removeClass('available').removeClass('active'), function(obj) {
						D.show({
							title: {
								tobalance: {
									en: 'Too small amount, less than service comission!',
									ru: 'Слишком маленькая сумма, меньше комиссии сервиса!'
								}
							},
							css: D.getCss({type: 'no'})
						});
					});

				}

			} else {

				$('.comission_indicator_host').css({
					visibility: 'hidden'
				});

				$('.fill_up_host .payment_method_host').removeClass('available').unbind('click');
				
				$('.available_offers_list').html('');
				
				$('.available_offers_host').hide();
				
			}

		}

		//withdraw block

		if ($('.withdraw_amount').length) {
			B.forceToFloat($('.withdraw_amount'), 0, User.data.money * 1);
		}

		if ($('.withdraw_amount').val() * 1 > 0) {

			$('.payment_method_host', $('.withdraw_host')).css({
				opacity: 0.6,
				cursor: 'pointer'
			});

			$('.payment_method_host.available_method', $('.withdraw_host')).css({
				opacity: 1
			});

			B.click($('.payment_method_host', $('.withdraw_host')).css({
				cursor: 'pointer'
			}), function(obj) {
				var id = B.getID(obj, 'id_');
				B.get({
					r: 'user/main',
					UserPaymentMethod: 'open',
					payment_id: id
				});
			});


		} else {

			$('.payment_method_host', $('.withdraw_host')).css({
				opacity: 0.6,
				cursor: 'default'
			}).unbind('click');

		}
	},
	outputUploadedFiles: function(input) {
		var host = $('.uploaded_files_host');
		$('.image_preview_host', host).remove();
		host.append(input.html);
		Balance.initImages();
		Balance.initSendConfirmationButton();
	},
	imageActions: {
		onDelete: function(id, image_id) {
			B.post({
				r: 'user/main',
				Offer: 'delete_uploaded',
				offer_id: id,
				image_id: image_id * 1
			});
		},
		onExit: function(id) {
			Balance.confirmByByer({
				id: id
			});
		},
		onExitClaim: function(id) {
			Balance.viewClaim({
				id: id
			});
		},
		onExitReadonly: function(id) {
			D.hide();
			B.post({
				r: 'user/main',
				Offer: 'payment_details',
				seller_check: 1,
				offer_id: id
			});
		}
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
	initImages: function(readonly) {
		var that = this;

		Site.initImages({
			readonly: readonly
					? 1
					: 0,
			onExit: readonly
					? (typeof readonly === 'function'
							? function() {
								readonly()
							}
					: that.imageActions.onExitReadonly)
					: that.imageActions.onExit,
			onDelete: that.imageActions.onDelete
		});

	}
};