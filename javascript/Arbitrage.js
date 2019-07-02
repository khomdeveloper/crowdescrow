var Arbitrage = {
	init: function() {
		var that = this;
		that.onShow();
	},
	onShow: function() {
		var that = this;
		that.updateList();
	},
	checkInvitation: function() {
		B.get({
			Expert: 'check_invitation'
		});
	},
	offerToExpert: function(input) {
		var that = this;
		if ($('.dialog').is(':visible')) {
			setTimeout(function() {
				that.offerToExpert(input);
			}, 500);
		} else {

			D.show({
				title: {
					'you_has_been_invited_as_an_expert2': {
						en: 'Would you like to participate as an expert in the dispute of the parties?',
						ru: 'Хотите принять участие в качестве эксперта в споре сторон?'
					}
				},
				buttons: {
					ok: {
						image: A.baseURL() + 'images/go.png',
						action: function() {
							A.w([
								'Site'
							], function() {
								Site.switchTo('arbitrage');
							});
						}
					}
				},
				message: false,
				css: D.getCSS({type: 'norm'})
			});

			/* D.show({
			 title: {
			 are_you_agree_to_act_as_expert_for: {
			 en: 'Are you ready to act as expert on the disputed transaction for <span class="expert_royalty_host"></span>$?',
			 ru: 'Вы готовы выступить экспертом по спорной сделке за <span class="expert_royalty_host"></span>$?'
			 }
			 },
			 message: 'pages/arbitrage/offer_to_expert.php',
			 buttons: {
			 ok: {
			 image: A.baseURL() + 'images/go.png',
			 action: function() {
			 
			 }
			 }
			 },
			 onShow: function() {
			 
			 //console.log('we are here');
			 
			 $('.expert_royalty_host').html('<input type="text" class="expert_royalty" placeholder="" min="0" max="100" style="font-size:0.7rem; width:50px;"/>');
			 $('.dispute_subject_host').html(input.html);
			 
			 that.controlRoyalty(input.id);
			 $('.expert_royalty').attr({
			 max: input.max
			 }).unbind('mouseup').mouseup(function() {
			 that.controlRoyalty(input.id);
			 }).unbind('keyup').keyup(function() {
			 that.controlRoyalty(input.id);
			 }).unbind('change').change(function() {
			 that.controlRoyalty(input.id);
			 });
			 
			 Site.initImages({
			 onExit: function() {
			 D.hide();
			 },
			 onDelete: function() {
			 
			 },
			 get: {
			 },
			 onlyPreview: true
			 });
			 },
			 css: D.getCSS({type: 'norm'})
			 }); */
		}
	},
	controlRoyalty: function(id, init, max) {

		var that = this;

		if (init) {

			if ($('.expert_royalty').is(':visible')) {

				$('.expert_royalty').attr({
					max: max
				}).unbind('mouseup').mouseup(function() {
					that.controlRoyalty(id);
				}).unbind('keyup').keyup(function() {
					that.controlRoyalty(id);
				}).unbind('change').change(function() {
					that.controlRoyalty(id);
				});
			} else {
				setTimeout(function() {
					that.controlRoyalty(id, init, max)
				}, 200);

				return false;
			}

		}

		if ($('.expert_royalty').val() || $('.expert_royalty').val() === 0) {

			if ($('.expert_royalty').val() * 1 > $('.expert_royalty').attr('max') * 1) {
				$('.expert_royalty').val($('.expert_royalty').attr('max'));
			}

			B.click($('.button_ok').css({
				opacity: 1,
				cursor: 'pointer'
			}), function() {
				D.hide();
				B.post({
					Expert: 'expert_agree',
					id: id,
					price: $('.expert_royalty').val() * 1
				});
			});
		} else {
			$('.button_ok').css({
				opacity: 0.5,
				cursor: 'default'
			}).unbind('click');
		}
	},
	showLimits: function(input) {
		var that = this;

		if (!User || !User.data) {
			setTimeout(function() {
				that.showLimits(input)
			}, 200);
			return;
		}

		for (var i in input.data) {
			var record = input.data[i];
			var iam = User.data.id == record.user_id
					? 'seller'
					: 'customer';

			$('.can_decline_experts.id_' + i).html(record[iam + '_declines']);
			$('.can_invite_experts.id_' + i).html(record['can_invite']);
		}
	},
	outBalanceClaims: function(input) {
		var that = this;

		if (input.data) {

			$('.balance_dispute_host').slideDown();
			$('.balance_claims_host').html(input.html);

			$('.balance_claims_host .help_line').css({
				'min-width' : '10px'
			});

			A.w(['User'], function() {

				that.showLimits(input);

				//decline invited expert
				B.click($('.balance_claims_host .decline_this_expert'), function(obj) {
					D.hide(function() {
						D.show({
							title: {
								sure_to_declie_this_expert: {
									en: 'Are you sure to decline this expert?',
									ru: 'Вы уверены что хотите отказаться от этого эксперта?'
								}
							},
							message: false,
							buttons: {
								ok: {
									image: A.baseURL() + 'images/go.png',
									action: function() {
										B.post({
											Expert: 'decline_this_expert',
											id: B.getID(obj)
										});
									}
								}
							},
							css: D.getCSS({type: 'no'})
						});
					});

				});

				//accept invited expert
				B.click($('.accept_this_expert'), function(obj) {

					D.hide(function() {
						D.show({
							title: {
								sure_to_accept_this_expert2: {
									en: 'Are you sure to accept this expert and pay amount requested by expert?',
									ru: 'Вы уверены что признаете этого эксперта и готовы оплатить запрашиваемую экспертом сумму?'
								}
							},
							message: 'pages/arbitrage/expert_royalty_conditions.php',
							buttons: {
								ok: {
									image: A.baseURL() + 'images/go.png',
									action: function() {
										B.post({
											Expert: 'accept_this_expert',
											id: B.getID(obj)
										});
									}
								}
							},
							css: D.getCSS({type: 'ok'})
						});
					});

				});

				//invite exprt
				B.click($('.invite_expert'), function(obj) {
					var id = B.getId(obj);
					D.show({
						title: {
							invite_expert: {
								en: 'Find and invite expert',
								ru: 'Найти и пригласить эксперта'
							}
						},
						cls : 'dialog-invite',
						/*
						css: $.extend(D.getCss({type: 'norm'}), {
							message_back: {
								background: 'rgba(147,112,219,0.7)'
							}
						}),*/
						message: 'pages/arbitrage/invite.php',
						onShow: function() {

							var callback = function() {
								that.runSearch({
									page: 0,
									claim_id: id
								});
							};
							that.controlSearchExpert(callback);
							$('.expert_name').unbind('mouseup').mouseup(function() {
								that.controlSearchExpert(callback);
							}).unbind('keyup').keyup(function() {
								that.controlSearchExpert(callback);
							}).unbind('change').change(function() {
								that.controlSearchExpert(callback);
							});
							B.get({
								Expert: 'create_invitation',
								claim_id: id,
								ok: function(response) {
									$('.expert_invitation_link').val(response.link);
								}
							});
						}
					});
				});
				//Cancel chargeback
				B.click($('.cancel_chargeback'), function(obj) {

					var claim_id = B.getId(obj);
					if (input.data) {
						var iam = input.data[claim_id].user_id == User.data.id
								? 'seller'
								: 'customer';
					} else {
						var iam = false;
					}

					D.show({
						title: {
							are_you_sure_to_agree_claim_and_continue_transaction: {
								en: 'Are you sure to accept the claim of your contragent?',
								ru: 'Вы уверены что согласны с претензией контрагента? '
							}
						},
						message: 'pages/arbitrage/warning.php?iam=' + iam,
						css: D.getCss({type: 'no'}),
						buttons: {
							ok: {
								image: A.baseURL() + 'images/go.png',
								action: function() {
									D.hide();
									B.post({
										Offer: 'cancel_chargeback',
										offer_id: claim_id
									});
								}
							}
						}
					});
				});
			});

			//View user

			$('.viewUser').unbind('click').click(function() {
				B.get({
					User: 'view',
					user_id: B.getID($(this)),
					offer_id: B.getID($(this), 'offer_id_')
				});
			});

			//for deals
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

			Site.contextHelpProcessor($('.balance_claims_host'));

		} else {
			console.log(input);
			$('.balance_dispute_host').slideDown();
			$('.balance_claims_host').html(input.html);
			$('.balance_claims_host .help_line').css({
				'min-width' : '100%'
			});
		}

		if (input.expertise) {
			$('.claims_on_expertise').html(input.expertise);


			B.click($('.claims_on_expertise .vote_dispute'), function(obj) {

				var side = B.getID(obj, 'for_');
				var id = B.getID(obj, 'id_');

				D.hide(function() {
					D.show({
						title: side == (obj.hasClass('isDeal')
								? 'customer'
								: 'seller')
								? {
									vote_for_seller: {
										en: 'Are you sure that Seller is right?',
										ru: 'Вы уверены что прав Продавец?'
									}
								}
						: {
							vote_for_customer: {
								en: 'Are you sure that Customer is right?',
								ru: 'Вы уверены что прав Покупатель?'
							}
						},
						buttons: {
							ok: {
								image: A.baseURL() + 'images/go.png',
								action: function() {
									D.hide();
									B.post({
										Expert: 'vote',
										side: side,
										id: id
									});
								}
							}
						},
						message: false,
						css: D.getCSS({type: 'norm'})
					});
				});
			});

			B.click($('.claims_on_expertise .agree_to_act_as_expert'), function(obj) {
				B.get({
					Expert: 'showExpertFeeForm',
					id: B.getID(obj)
				});
			});

			B.click($('.claims_on_expertise .decline_this_expert'), function(obj) {
				D.hide(function() {
					D.show({
						title: {
							sure_to_expert_disagree_57: {
								en: 'You sure you don`t want to pursue the expertise of the dispute?',
								ru: 'Вы уверены что не хотите проводить экспертизу данного спора?'
							}
						},
						buttons: {
							ok: {
								image: A.baseURL() + 'images/go.png',
								action: function() {
									B.post({
										Expert: 'expert_disagree',
										id: B.getID(obj)
									});
								}
							}
						},
						message: false,
						css: D.getCSS({type: 'no'})
					});
				});
			});

			$('.viewUser').unbind('click').click(function() {
				B.get({
					User: 'view',
					user_id: B.getID($(this)),
					offer_id: B.getID($(this), 'offer_id_')
				});
			});

			$('.expertise_host').slideDown();
			Site.contextHelpProcessor($('.claims_on_expertise'));
		} else {
			$('.claims_on_expertise').html('');
			$('.expertise_host').slideUp();
		}

		Site.initImages({
			onExit: function() {
				D.hide();
			},
			onDelete: function() {
			},
			get: {}
		});


	},
	/**
	 * 
	 * @param {type} input 
	 * 
	 * id
	 * max
	 * 
	 * @returns {undefined}
	 */
	showExpertFeeForm: function(input) {
		var that = this;

		D.hide(function() {
			D.show({
				title: {
					are_you_agree_to_act_as_expert_for3: {
						en: 'Are you ready to act as expert on the disputed transaction for <span class="expert_royalty_host">{{text}}</span>$?',
						ru: 'Вы готовы выступить экспертом по спорной сделке за <span class="expert_royalty_host">{{text}}</span>$?',
						_include: {
							text: '<input type="text" class="expert_royalty" placeholder="" min="0" max="100" style="font-size:0.7rem; width:50px;"/>'
						}
					}
				},
				buttons: {
					ok: {
						image: A.baseURL() + 'images/go.png',
						action: function() {

						}
					}
				},
				onShow: function() {

					//console.log('we are here');

					//$('.expert_royalty_host').html('<input type="text" class="expert_royalty" placeholder="" min="0" max="100" style="font-size:0.7rem; width:50px;"/>');



					that.controlRoyalty(input.id, true, input.max);

					/*
					 $('.expert_royalty').attr({
					 max: input.max
					 }).unbind('mouseup').mouseup(function() {
					 that.controlRoyalty(input.id);
					 }).unbind('keyup').keyup(function() {
					 that.controlRoyalty(input.id);
					 }).unbind('change').change(function() {
					 that.controlRoyalty(input.id);
					 });*/
				},
				css: D.getCSS({type: 'norm'})
			});
		});

	},
	runSearch: function(input) {

		var page = input.page;
		var that = this;
		B.get({
			Expert: 'search',
			claim_id: input.claim_id,
			page: page,
			what: $('.invite_expert_host .expert_name').val(),
			ok: function(response) {

				$('.experts_list').html(response.html);
				$('.pagination_host').html(response.pagination);
				if (response.html) {
					$('.experts_list_host').slideDown();
				} else {
					$('.experts_list_host').slideUp();
					return;
				}

				$('.invite_expert_host .pagination').unbind('click').click(function() {
					that.runSearch({
						page: B.getId($(this)),
						claim_id: input.claim_id
					});
				});
				//TODO: затемнять уже приглашенных

				B.click($('.invite_expert_host .list_user_profile'), function(obj) {
					B.post({
						Expert: 'invite',
						user_id: B.getId(obj),
						claim_id: input.claim_id
					});
				});
			}
		});
	},
	controlSearchExpert: function(callback) {

		if ($('.expert_name').val()) {

			B.click($('.search_expert').css({
				opacity: 1,
				cursor: 'pointer'
			}), function(obj) {
				callback();
			});
			if ($('.expert_name').val().indexOf('@') !== -1) {
				$('.send_expert_invitation').css({
					opacity: 1,
					cursor: 'pointer'
				}).unbind('click').click(function() {
					//TODO: send invitation link to specified user
				});
			} else {
				$('.send_expert_invitation').css({
					opacity: 0.5,
					cursor: 'default'
				}).unbind('click');
			}

		} else {
			$('.search_expert').css({
				opacity: 0.5,
				cursor: 'default'
			}).unbind('click');
			$('.send_expert_invitation').css({
				opacity: 0.5,
				cursor: 'default'
			}).unbind('click');
		}

	},
	updateList: function(input) {

		if ($('.arbitrage_page').is(':visible')) {
			A.w(['D', 'B'], function() {
				D.hide();
				B.get({
					Offer: 'get_claims'
				});
			});
			$('.indicator_arbitrage').hide();
		} else if (input && input.notification) {
			$('.indicator_arbitrage').fadeOut(function() {
				$('.indicator_arbitrage').show();
			});
		}
	}
};