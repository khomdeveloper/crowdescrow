/**
 * simple version of transaction
 */
var Deal = {
	init: function() {
		this.onShow();
	},
	onShow: function() {
		var that = this;
		that.update();
	},
	update: function(input) {

		//console.log(input);

		if ($('.customer_page').is(':visible')) {
			B.get({
				Deal: 'list',
				role: 'customer',
				page: 0
			});
			$('.indicator_customer').hide();
		} else if (input && input.role == 'customer') {
			$('.indicator_customer').fadeOut(function() {
				$('.indicator_customer').show();
			});
		}

		if ($('.seller_page').is(':visible')) {
			B.get({
				Deal: 'list',
				role: 'seller',
				page: 0
			});
			$('.indicator_seller').hide();
		} else if (input && input.role == 'seller') {
			$('.indicator_seller').fadeOut(function() {
				$('.indicator_seller').show();
			});
		}
		//TODO: add notification circle
	},
	form: function(input) {
		var that = this;
		D.show({
			title: input.title,
			message: input.message,
			//css: D.getCSS({type: 'asphalt'}),
			cls: 'dialog-asphalt',
			onShow: function() {
				that.images(input.offer_id); //init images
				Site.restoreFormParameters('Deal'); //restore 
				that.control('init');

				$('.viewUser.fromForm').unbind('click').click(function() {
					B.get({
						User: 'view',
						user_id: B.getID($(this)),
						ok: function(response) {
							if (response.User && response.User.showUserProfile) {
								A.w(['User'], function() {
									D.show({
										title: response.User.showUserProfile.name,
										css: D.getCSS({type: 'norm'}),
										message: response.User.showUserProfile.html,
										exit: function() {
											D.hide(function() {
												that.form(input);
											}, false);
										},
										onShow: function() {
											Site.initImages({
												readonly: 1,
												onExit: function() {
													User.showUserProfile(response.User.showUserProfile);
												}
											});
										}
									});
								});
							}
						}
					});
				});

			},
			buttons: input.noaction
					? false
					: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {

							}
						}
					}
		});
	},
	control: function(init) {
		var that = this;
		if (init) {
			B.initControl($('.input'), function() {
				that.control();
			});
		}

		B.forceToFloat($('.Deal_amount'), 0);
		B.forceToInt($('.Deal_term'), 0);

		var post = false;
		$('.input', $('.form_Deal')).each(function() {
			if ($(this).val() != $(this).attr('default') || $(this).hasClass('required')) {
				if (!post) {
					post = {
						Deal: 'set',
						id: B.getID($(this))
					};
				}
				post[B.getID($(this), 'Deal_')] = $(this).val();
			}
		});

		$('.required', $('.form_Deal')).each(function() {
			if (!$(this).val() || $(this).val() * 1 == 0) {
				post = false;
			}
		});

		if (post) {
			$('.button_ok').css({
				opacity: 1
			}).unbind('click').click(function() {
				D.hide();
				B.post(post);
			});
		} else {
			$('.button_ok').css({
				opacity: 0.5
			}).unbind('click');
		}

	},
	load: function(offer_id) {
		B.get({
			Deal: 'load',
			id: offer_id
		});
	},
	outputUploadedFiles: function(input) {
		var that = this;
		var host = $('.uploaded_files_host');
		$('.image_preview_host', host).remove();
		host.append(input.html);
		that.images();
	},
	images: function(offer_id) {
		var that = this;
		A.w(['Site'], function() {

			Site.initImages({
				onExit: function(id) {
					B.get({
						Deal: 'load',
						id: id
					});
				},
				onDelete: function(offer_id, image_id) {
					B.post({
						Deal: 'delete_uploaded',
						offer_id: offer_id,
						image_id: image_id * 1
					});
				}
			});

			Site.initFileUploader({
				obj: 'Deal',
				id: offer_id
			});
		});
	},
	list: function(input) {
		var that = this;

		if (input.seller || input.customer) {
			$('.customer_page .simple_deals_host').html(input.customer);
			$('.seller_page .simple_deals_host').html(input.seller);

			var host = input.seller
					? $('.seller_page .simple_deals_host')
					: $('.customer_page .simple_deals_host');

			Site.contextHelpProcessor($('.my_444', host));
			Site.contextHelpProcessor($('.me_444', host));
			Site.contextHelpProcessor($('.accepted_444', host));
			Site.contextHelpProcessor($('.completed_444', host));
			Site.contextHelpProcessor($('.disputed_444', host));

			$('.viewUser').unbind('click').click(function() {
				B.get({
					User: 'view',
					user_id: B.getID($(this))
				});
			});

			B.click($('.deal_description', host), function(obj) {
				B.get({
					Deal: 'load',
					id: B.getID(obj)
				});
			});

			B.click($('.create_simple_deal', input.seller
					? $('.seller_page')
					: $('.customer_page')), function() {
				B.post({
					Deal: 'create',
					role: $('.customer_page').is(':visible')
							? 'customer'
							: 'seller'
				});
			});

			B.click($('.cancel_deal', host), function(obj) {

				var id = B.getID(obj);

				D.show({
					title: obj.hasClass('isCreator')
							?
							(obj.hasClass('isFunded')
									? {
										'sure_to_cancel (in deal list) 3': {
											en: 'Are you sure to cancel the deal? The deposit will be refunded to customer.',
											ru: 'Вы уверены, что хотите отменить сделку? Депозит будет возвращен покупателю.'
										}
									}
							: {
								'sure_to_cancel (in deal list)': {
									en: 'Are you sure to cancel the deal?',
									ru: 'Вы уверены, что хотите отменить сделку?'
								}
							})
							:
							{
								'sure to reject the offer (in deal list)': {
									en: 'Are you sure to reject the offer?',
									ru: 'Вы уверены что хотите отклонить предложение?'
								}
							},
					message: false,
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Deal: 'cancel',
									id: id
								});
							}
						}
					},
					css: D.getCSS({type: 'no'})
				});
			});

			B.click($('.add_contractor'), function(obj) {

				that.addContractor({
					id: B.getID(obj)
				});

			});

			B.click($('.reject_counterparty'), function(obj) {

				D.show({
					title: {
						sure_to_temporary_withdraw_offer: {
							en: 'Are you sure to temporary withdraw offer?',
							ru: 'Вы уверены что хотите временно отозвать предложение?'
						}
					},
					message: false,
					css: $.extend(D.getCSS({type: 'no'}), {
						message_back: {
							background: 'rgba(255,215,0,0.8)'
						}
					}),
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Deal: 'pause',
									id: B.getID(obj)
								});
							}
						}
					},
				});

			});

			B.click($('.accept_deal'), function(obj) {
				D.show({
					title: {
						sore_to_accept_the_offer: {
							en: 'Are you sure to accept this offer?',
							ru: 'Вы уверены что хотите принять предложение?'
						}
					},
					css: D.getCSS({type: 'ok'}),
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Deal: 'accept',
									id: B.getID(obj)
								});
							}
						}
					},
					message: false
				});
			});

			B.click($('.deal_completed'), function(obj) {

				D.show({
					title: {
						'sure to report work/delivery as completed': {
							en: 'Are you sure to report deal as completed?',
							ru: 'Вы уверены что хотите уведомить о выполнении обязательств?'
						}
					},
					message: false,
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Deal: 'complete',
									id: B.getID(obj)
								});
							}
						}
					},
					css: D.getCSS({
						type: 'ok'
					})
				});

			});

			B.click($('.deal_ok'), function(obj) {

				D.show({
					title: {
						'sure to accept and release': {
							en: 'Are you sure accept results and release deposit?',
							ru: 'Вы уверены что хотите принять результаты и выплатить депозит?'
						}
					},
					message: false,
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Deal: 'release',
									id: B.getID(obj)
								});
							}
						}
					},
					css: D.getCSS({
						type: 'ok'
					})
				});

			});

			B.click($('.deal_restart'), function(obj) {

				D.show({
					title: {
						'you have not completed yet': {
							en: 'You have not completed everything yet?',
							ru: 'Вы еще не все закончили?'
						}
					},
					message: false,
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Deal: 'restart',
									id: B.getID(obj)
								});
							}
						}
					},
					css: D.getCSS({
						type: 'ok'
					})
				});

			});

			B.click($('.deal_correct'), function(obj) {
				B.get({
					Deal: 'loadCorrectionForm',
					id: B.getID(obj)
				});
			});

			B.click($('.deal_chargeback'), function(obj) {
				A.w(['Balance'], function() {
					Balance.showChargeBackDialog(obj.hasClass('isCounter')
							? {
								offer_id: B.getID(obj),
								obj: 'Deal',
								isCounter: 1
							}
					: {
						obj: 'Deal',
						offer_id: B.getID(obj)
					});
				});
			});

			B.click($('.deal_cancel_claim'), function(obj) {

				D.show({
					title: {
						'sure_to_cancel_claim (in deal list)2': {
							en: 'Are you sure to cancel your claim and return the deal to previous state?',
							ru: 'Вы уверены что хотите отменить свою претензию и вернуть сделку к преженему состоянию?'
						}
					},
					css: D.getCSS({type: 'norm'}),
					message: false,
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Deal: 'cancel_claim',
									id: B.getID(obj)
								});
							}
						}
					}
				});

			});

			B.click($('.deal_admit_claim'), function(obj) {
				D.show({
					title: {
						'sure_to_admit_claim (in confirmation)': {
							en: 'Are you sure to admit a claim?',
							ru: 'Вы уверены что призанете претензию?'
						}
					},
					message: 'pages/deal/admit_confirmation.php?role=' + ($('.customer_page').is('visible')
							? 'customer'
							: 'seller'),
					css: D.getCSS({type: 'no'}),
					buttons: {
						ok: {
							image: A.baseURL() + 'images/go.png',
							action: function() {
								D.hide();
								B.post({
									Deal: 'admit',
									id: B.getID(obj)
								});
							}
						}
					}
				});
			});

		}
	},
	showCorrectionForm: function(input) {
		var that = this;
		D.show({
			title: {
				'what_is_it_necessary_to_correct': {
					en: 'What is it necessary to correct?',
					ru: 'Что необходимо исправить?'
				}
			},
			buttons: {
				ok: {
					image: A.baseURL() + 'images/go.png',
					action: function() {
						D.hide();
						B.post({
							Deal: 'correct',
							message: $('.Deal_reconfirm').val(),
							id: input.id
						});
					}
				}
			},
			css: D.getCss({type: 'norm'}),
			message: input.message,
			onShow: function() {

				$('.viewUser.fromForm').unbind('click').click(function() {
					B.get({
						User: 'view',
						user_id: B.getID($(this)),
						ok: function(response) {
							if (response.User && response.User.showUserProfile) {
								A.w(['User'], function() {
									D.show({
										title: response.User.showUserProfile.name,
										css: D.getCSS({type: 'norm'}),
										message: response.User.showUserProfile.html,
										exit: function() {
											D.hide(function() {
												that.showCorrectionForm(input);
											}, false);
										},
										onShow: function() {
											Site.initImages({
												readonly: 1,
												onExit: function() {
													User.showUserProfile(response.User.showUserProfile);
												}
											});
										}
									});
								});
							}
						}
					});
				});

				A.w(['Site'], function() {

					Site.initImages({
						onExit: function(id) {
							B.get({
								Deal: 'loadCorrectionForm',
								id: id
							});
						},
						onDelete: function(offer_id, image_id) {
							B.post({
								Deal: 'delete_uploaded',
								offer_id: offer_id,
								image_id: image_id * 1
							});
						}
					});

				});

				Site.initFileUploader({
					obj: 'Deal',
					id: input.id
				});

			}
		});

	},
	closeInvitationDialog: function(input) {
		if ($('.counterparty_invitation_link').is(':visible')) {
			D.hide();
		}
	},
	callUpdateInvitationLink: function(id) {
		B.get({
			Deal: 'update_invitation_link',
			offer_id: id
		});
	},
	updateInvitationLink: function(input) {
		$('.counterparty_invitation_link').val(input.href);
	},
	addContractor: function(input) {
		var that = this;
		D.hide(function() {
			D.show({
				title: {
					invite_contractor: {
						en: 'Find and invite contractor',
						ru: 'Найти и пригласить контрагента'
					}
				}, /*
				 css: $.extend(D.getCss({type: 'norm'}), {
				 message_back: {
				 background: 'rgba(147,112,219,0.7)'
				 }
				 }),*/
				cls: 'dialog-invite',
				message: 'pages/deal/invite.php?offer_id=' + input.id,
				onShow: function() {
					that.callUpdateInvitationLink(input.id);
					that.searchControl(input, $('.contractor_name'));
					$('.contractor_name').unbind('keyup').keyup(function(){
						var val = $(this).val();
						if (val.length > 3){
							that.runSearch({
								page: 0,
								what: $(this).val(),
								offer_id: input.id
							});
						}
					});
				}
			});
		}, false, 'slide');
	},
	searchControl: function(input, obj) {
		var that = this;
		Site.control({
			obj: obj || false,
			condition: function() {
				return $('.contractor_name').val() && !that.nowSearching
						? true
						: false;
			},
			button: $('.search_contractor'),
			during: function() {
				$('.contractor_list_error').hide();
			},
			action: function() {
				that.runSearch({
					page: 0,
					what: $('.contractor_name').val(),
					offer_id: input.id
				});
			}
		});
	},
	nowSearching: false, //now searchingflag
	runSearch: function(input) {
		var that = this;

		if (that.nowSearching) {
			return false;
		}

		that.nowSearching = true;
		that.searchControl({
			id : input.offer_id
		});
		var page = input.page;
		var that = this;
		B.get({
			Deal: 'search',
			page: page,
			what: input.what,
			ok: function(response) {

				that.nowSearching = false;
				that.searchControl({
					id : input.offer_id
				});

				$('.contractor_list').html(response.html);
				$('.invite_contractor_host .pagination_host').html(response.pagination);

				if (response.html) {
					$('.contractor_list_host').slideDown();
					$('.contractor_list_error').slideUp();
				} else {
					$('.contractor_list_host').slideUp();
					$('.contractor_list_error').slideDown();
					return;
				}

				B.click($('.invite_contractor_host .pagination'), function(obj) {
					that.runSearch({
						page: B.getId(obj),
						what: input.what,
						offer_id: input.offer_id
					});
				});

				B.click($('.invite_contractor_host .list_user_profile'), function(obj) {
					D.hide();
					B.post({
						Deal: 'invite',
						user_id: B.getId(obj),
						offer_id: input.offer_id,
						role: $('.customer_page').is(':visible')
								? 'customer'
								: 'seller'
					});
				});
			}
		});
	}
};