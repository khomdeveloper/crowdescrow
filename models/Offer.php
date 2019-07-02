<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Offer
 * cance
 * @author valera261104
 */
class Offer extends M {

	public static function action($r, $dontdie = false) {
		$com = empty($r[get_called_class()])
				? false
				: $r[get_called_class()];

		if (!in_array($com, [
					'confirmRemotePayment',
					'checkOrderAmount'
				])) {
			self::rapidTimoutChecker();
			$user = User::logged();
		}

		if ($com == 'confirmRemotePayment') { //confirm Remote payment by access key
			self::required([
				'transaction_id' => true,
				'signature'		 => true
					], $r);

			$offer = self::getBy([
						'id'		 => $r['transaction_id'],
						'status'	 => 'accepted',
						'currency'	 => '!=_deal',
						'_notfound'	 => 'Offer with transaction_id = ' . $r['transaction_id'] . ' not found!'
			]);

			$us = User::getBy([
						'id'		 => $offer->get('user_id'),
						'_notfound'	 => true
			]);

			$offer->addEvent('Payment confirmed remotely', $us);

			if (md5($r['transaction_id'] . $us->get('payment_key')) == $r['signature']) {
				$offer->releaseBySeller('remote_payment');
			} else {
				throw new Exception('Wrong signature "' . $r['signature'] . '"');
			}

			return [
				'success' => 'payment confirmed'
			];
		} elseif ($com == 'checkOrderAmount') { //return amount for remote payment
			self::required([
				'transaction_id' => true,
				'signature'		 => true
					], $r);

			$offer = self::getBy([
						'id'		 => $r['transaction_id'],
						'currency'	 => '!=_deal',
						'_notfound'	 => 'Offer with transaction_id = ' . $r['transaction_id'] . ' not found!'
			]);

			if (md5($r['transaction_id'] . User::getBy([
								'id'		 => $offer->get('user_id'),
								'_notfound'	 => true
							])->get('payment_key')) != $r['signature']) {
				throw new Exception('Wrong signature "' . $r['signature'] . '"');
			}

			return [
				'quantity'	 => $offer->get('amount'),
				'amount'	 => $offer->get('price'),
				'currency'	 => $offer->get('currency')
			];
		} elseif ($com == 'cancel_chargeback') {

			self::required([
				'offer_id' => true
					], $r);

			$user = User::logged();

			$offer = self::getBy([
						'id'		 => $r['offer_id'],
						'status'	 => [
							'disputed',
							'arbitrage'
						],
						'_notfound'	 => true
			]);

			if ($offer->get('status') == 'disputed') {
				$offer->set([
					'status'						 => $offer->get('iam') == 'seller'
							? 'confirmed'
							: 'accepted',
					$offer->get('iam') . '_claim'	 => '' //make claim zero
				])->returnArbitrageDeposit($offer->get('iam') == 'seller'
								? $offer->get('accepted_by')
								: $offer->get('user_id'))->setActions([
					'update_withdraw_orders',
					'update_arbitrage_list'
				])->addEvent('Balance charge back cancelled');

				//email notification?

				return [
					'Balance' => [
						'updateWithdraw' => [
							'data' => false
						]
					]
				];
			} else {//arbitrage
				//удаляем экспертов с возвратом зарезервированных сумм
				//$claim = Expert::removeAllExperts($offer->get('id'));
				if ($offer->get('iam') == 'seller') { //if iam seller - release
					$offer->removeAllExperts()->addEvent('Chargeback admitted')->releaseBySeller();
				} elseif ($offer->get('iam') == 'customer') { //if iam customer - cancel
					$offer->removeAllExperts()->addEvent('Chargeback admitted')->cancelByBuyer();
				}

				return [
					'Arbitrage'	 => [
						'updateList' => [
							'data' => 'none'
						]
					],
					'User'		 => [
						'updateBalance' => [
							'data' => 'none'
						]
					]
				];
			}
		} elseif ($com == 'get_claims') {

			$user = User::getBy();

			//$_SESSION['stop'] = true;

			$claims = Claim::getBy([
						'||'		 => [
							'user_id'		 => $user->get('id'),
							'accepted_by'	 => $user->get('id')
						],
						'status'	 => 'arbitrage',
						'_return'	 => ['id' => 'object']
			]);

			$h = [];

			if (!empty($claims)) {
				$template = [
					'balance'	 => H::getTemplate('pages/arbitrage/balance_claim', [], true),
					'deal'		 => H::getTemplate('pages/arbitrage/deal_claim', [], true)
				];
				$data = [];
				foreach ($claims as $claim) {
					$h[] = $claim->getHTML($template[$claim->get('currency') == '_deal'
									? 'deal'
									: 'balance']);
					$data[$claim->get('id')] = $claim->toArray();
				}
			} else {
				$data = false;
			}


			$h[] = H::getTemplate('pages/dialogs/inline_help', [
						'style'		 => 'padding:10px;',
						'content'	 => H::getTemplate('pages/arbitrage/arbitrage_inline_help', [], true)
							], true);

			$html = join('', $h);


			$action = Action::getBy([
						'key' => 'update_arbitrage_list'
			]);

			if (!empty($action)) {
				if (UserAction::getBy([
							'user_id'	 => $user->get('id'),
							'action_id'	 => $action->get('id'),
							'_return'	 => 'count'
						]) > 0) {
					UserAction::getBy([
						'user_id'	 => $user->get('id'),
						'action_id'	 => $action->get('id')
					])->remove();
				}
			}

			return [
				'Arbitrage' => [
					'outBalanceClaims' => [
						'html'		 => $html,
						'data'		 => $data,
						'expertise'	 => Expert::getClaimsOnExpertise()
					]
				]
			];
		} elseif ($com == 'run_chargeback') { //run chargeback	
			self::required([
				'id'	 => true,
				'claim'	 => true
					], $r);

			$user = User::logged();

			$offer = self::getBy([
						'id'		 => $r['id'],
						'status'	 => [
							'confirmed',
							'accepted',
							'disputed',
							'completed'
						],
						'_notfound'	 => true
			]);

			if ($offer->get('currency') == '_deal') {

				if ($offer->get('status') == 'accepted' && !$offer->get('reconfirm') && $offer->get('remain')) {
					throw new Exception(T::out([
						'cannot_claim_while_no_corrections_or_expirings (error)' => [
							'en' => 'Cannot claim while not expired and no any corrections',
							'ru' => 'Невозможно выставить претензию пока не истек срок работ и нет никаких правок'
						]
					]));
				}
			} else {
				if ($offer->d('remain') < 0) {
					throw new Exception('Expired');
				}
			}

			//save cash
			$offer->set([
				'storage' => json_encode([
					'date'	 => $offer->get('autopay'),
					'status' => $offer->get('status')
				])
			]);

			if ($offer->get('status') == 'completed' && $offer->get('currency') != '_deal') {
				throw new Exception('In this case need to be a deal');
			}

			$claimer = $offer->get('iam');

			//fillup the_balance
			if (!$offer->d($claimer . '_hold')) {

				$need = round(S::getBy([
							'key'		 => 'claim_deposit',
							'_notfound'	 => [
								'key'	 => 'claim_deposit',
								'val'	 => 10
							]
						])->get('val') / 100 * $offer->d('amount'), 2);

				if (empty($need) || (round($need / 4, 2) == 0)) {
					throw new Exception(T::out([
						'not_suitable_for_expertise' => [
							'en' => 'The deal amount is too less to run arbitrage mechanism.',
							'ru' => 'Сумма сделки слишком мала, чтобы ее можно было отправить на экспертизу.'
						]
					]));
				}

				if ($need > $user->d('money')) {

					return [
						'D'		 => [
							'show' => [
								'title'		 => T::out([
									'Not enough money_need_2' => [
										'en'		 => 'Not enough money! Please fill up the balance. Нужно {{need}}$',
										'ru'		 => 'Недостаточно средств! Пожалуйста пополните баланс. Need {{need}}$',
										'_include'	 => [
											'need' => $need
										]
									]
								]),
								'message'	 => false,
								'css'		 => [
									'A' => [
										'getCss' => [
											'type' => 'no'
										]
									]
								]
							]
						],
						'Site'	 => [
							'switchTo' => [
								'page' => 'balance'
							]
						]
					];
				} else {

					$offer->set([
						$claimer . '_hold' => $need
					]);

					$user = User::getBy([
								'id' => $user->get('id')
							])->set([
								'money' => max(0, $user->d('money') - max(0, $need))
							])->cash();
				}
			}

			if ($offer->get('status') == 'disputed') {
				if ($offer->get($claimer . '_claim')) {
					throw new Exception(T::out([
						'claim_has_been_sent_already' => [
							'en' => 'Claim has been sent',
							'ru' => 'Претензия уже отправлена'
						]
					]));
				} else {

					$offer->sendEmailNotification('arbitrage')->set([
						$claimer . '_claim'	 => $r['claim'],
						'autopay'			 => '2100-00-00 00:00',
						'status'			 => 'arbitrage',
						'seller_declines'	 => 10,
						'customer_declines'	 => 10,
					])->addEvent('Counter claim created');

					$gotoArbitrage = true;
				}
			} else { //first claim
				$offer->set([
					'status'			 => 'disputed',
					$claimer . '_claim'	 => $r['claim'],
					'autopay'			 => (new DateTime())->modify('+' . S::getBy([
								'key'		 => 'time_should_pay',
								'_notfound'	 => [
									'key'	 => 'time_should_pay',
									'val'	 => 8
								]
							])->get('val') . ' hour')->format('Y-m-d H:i:s')
				])->sendEmailNotification('chargeback')->addEvent('Claim created');
			}

			//throw new Exception('stop');

			if ($offer->get('currency') == '_deal') {


				$deal = Deal::getBy([
							'id' => $offer->get('id')
						])->setActions([
					'update_deals_list',
					empty($gotoArbitrage)
							? false
							: 'update_arbitrage_list'
				]);

				return array_merge([
					'Deal'	 => [
						'list' => [
							$deal->get('role') => Deal::getList($deal->get('role'))
						]
					],
					'User'	 => [
						'updateBalance' => [
							'data' => 'none'
						]
					]
						], empty($gotoArbitrage)
								? []
								: [
							'Arbitrage'	 => [
								'updateList' => [
									'data' => 'none'
								]
							],
							'Site'		 => [
								'switchTo' => [
									'page' => 'arbitrage'
								]
							]
				]);
			} else {

				$offer->setActions([
					'update_withdraw_orders',
					empty($gotoArbitrage)
							? false
							: 'update_arbitrage_list'
				]);

				return array_merge([
					'Balance'	 => [
						'updateWithdraw' => [
							'data' => false
						]
					],
					'User'		 => [
						'updateBalance' => [
							'data' => 'none'
						]
					]
						], empty($gotoArbitrage)
								? []
								: [
							'Arbitrage'	 => [
								'updateList' => [
									'data' => 'none'
								]
							],
							'Site'		 => [
								'switchTo' => [
									'page' => 'arbitrage'
								]
							]
				]);
			}
		} elseif ($com == 'accept_claim') {

			self::required([
				'offer_id' => true
					], $r);

			$offer = self::getBy([
						'id'		 => $r['offer_id'],
						'status'	 => 'disputed',
						'autopay'	 => [
							'_between' => [
								(new DateTime())->format('Y-m-d H:i:s'),
								'2100-00-00 00:00',
							]
						],
						'_notfound'	 => true
			]);

			$user = User::logged();

			if ($offer->get('user_id') == $user->get('id')) {
				$claimer = 'customer';
				$iam = 'seller';
			} elseif ($offer->get('accepted_by') == $user->get('id')) {
				$claimer = 'seller';
				$iam = 'customer';
			} else {
				throw new Exception('Offer does not deal with current user');
			}

			if ($iam == 'seller') {
				$offer->addEvent('Seller addmit claim')->releaseBySeller();
			} else {
				$offer->addEvent('Customer admit claim')->cancelByBuyer();
			}

			return [
				'Balance' => [
					'updateWithdraw' => [
						'data' => false
					]
				]
			];
		} elseif ($com == 'view_claim') {

			self::required([
				'offer_id' => true
					], $r);

			$offer = self::getBy([
						'id'		 => $r['offer_id'],
						'status'	 => 'disputed',
						'autopay'	 => [
							'_between' => [
								(new DateTime())->format('Y-m-d H:i:s'),
								'2100-00-00 00:00',
							]
						],
						'_notfound'	 => true
			]);

			if ($offer->get('currency') == '_deal') {
				$deal = Deal::getBy([
							'id' => $offer->get('id')
				]);
			} else {
				$deal = $offer;
			}

			$user = User::logged();

			if ($offer->get('user_id') == $user->get('id')) {
				$claimer = 'customer';
				$iam = 'seller';
			} elseif ($offer->get('accepted_by') == $user->get('id')) {
				$claimer = 'seller';
				$iam = 'customer';
			} else {
				throw new Exception('Offer does not deal with current user 2');
			}

			$userPayment = UserPaymentMethod::getBy([
						'id'		 => $offer->get('method_id'),
						'user_id'	 => $offer->get('user_id'),
						'_notfound'	 => T::out([
							'payment_detales_not_found_2' => [
								'en' => 'Payment details not found!',
								'ru' => 'Платежные реквизиты не найдены!'
							]
						])
			]);

			$payment = Payment::getBy([
						'id' => $userPayment->get('payment_id')
			]);

			return [
				'Balance' => [
					'viewClaim' => [
						'message' => H::getTemplate('pages/withdraw/view_claim', [
							'time'			 => $offer->get('autopay'),
							'remain'		 => T::out([
								'auto_accept_after' => [
									'en'		 => 'Autoaccept after {{t}}',
									'ru'		 => 'Автосогласие через {{t}}',
									'_include'	 => [
										't' => '<span class="time_remain id_' . $offer->get('id') . '" timestamp="' . $offer->get('remain') . '">' . $offer->get('remain_string') . '</span>'
									]
								]
							]),
							'fill'			 => round($offer->d('price'), 6) . $offer->fget('currency'),
							'amount'		 => $offer->d('amount'),
							'offer_id'		 => $offer->get('id'),
							'claim'			 => $offer->fget($claimer . '_claim'),
							'iam'			 => $iam,
							'payment_system' => H::getTemplate('pages/balance/payment_method_in_title', [
								'image'	 => $payment->get(['image' => 0]),
								'href'	 => $payment->get('url'),
								'title'	 => $payment->get('title')
									], true),
							'user_payment'	 => $userPayment->get('id'),
							'seller'		 => $offer->get('User')->get('name'),
							'uploaded_files' => join('', $deal->uploadedFilesHTML())
								], true)
					]
				]
			];
		} elseif ($com == 'get_sell') { //return orders ready to withdraw
			if (empty($r['payment_id'])) {
				if (empty($_SESSION['filter_payment_id'])) {
					throw new Exception('Please select payment method!');
				} else {
					$r['payment_id'] = $_SESSION['filter_payment_id'];
				}
			} else {
				$_SESSION['filter_payment_id'] = $r['payment_id'];
			}

			return [
				'Balance' => [
					'showAvailableOffers' => self::showAvailableOffers($r)
				]
			];
		} elseif ($com == 'get_accepted') {

			/* $user = User::getBy([
			  'id' => User::logged()->get('id')
			  ]); */

			Action::tryToRead([
				'update_withdraw_orders_customer',
				'update_withdraw_orders'
					], User::logged());

			return [
				'Balance' => [
					'showAvailableOffers' => self::showAvailableOffers($r)
				]
			];

//_____________________________________________________________________________________
//
// CONFIRM BY BUYER + GET PAYMENT DETAILS (i and ^ buttons in fill th e ballance menu)
//_____________________________________________________________________________________
		} elseif ($com == 'confirm_by_byer' || $com == 'payment_details') { //детали платежа
			self::required([
				'offer_id' => true
					], $r);

			//TODO: get offer status

			if (empty($r['seller_check'])) {
				$offer = self::getBy([
							'id'		 => $r['offer_id'],
							'accept_by'	 => User::logged()->get('id'),
							'status'	 => empty($r['confirmed'])
									? 'accepted'
									: 'confirmed',
							'autopay'	 => [
								'_between' => [
									(new DateTime())->format('Y-m-d H:i:s'),
									'2100-00-00 00:00',
								]
							],
							'_notfound'	 => true
				]);
			} else { //return my offers
				$offer = self::getBy([
							'id'		 => $r['offer_id'],
							'user_id'	 => User::logged()->get('id'),
							'status'	 => 'confirmed',
							'_notfound'	 => true
				]);
			}

			$userPayment = UserPaymentMethod::getBy([
						'id'		 => $offer->get('method_id'),
						'user_id'	 => $offer->get('user_id'),
						'_notfound'	 => T::out([
							'payment_detales_not_found_2' => [
								'en' => 'Payment details not found!',
								'ru' => 'Платежные реквизиты не найдены!'
							]
						])
			]);

			$payment = Payment::getBy([
						'id' => $userPayment->get('payment_id')
			]);

			if ($com == 'confirm_by_byer' && $userPayment->get('mode') == 'automatic') {

				return [
					'Balance' => [
						'gotoPaymentPage' => [
							'url' => $offer->get('add_parameters_to_payment_url')
						]
					]
				];
			} else {

				return [
					'Balance' => [
						'showPaymentDetails' => [
							'mode'		 => empty($r['seller_check'])
									? 'byer'
									: 'seller',
							'offer_id'	 => $offer->get('id'),
							'title'		 => empty($r['confirmed']) && empty($r['seller_check']) && (!$offer->get('reconfirm'))
									? '<div class="please_pay_v">' . T::out([

										'please_pay_n_confirm_777' => [
											'en'		 => 'Transfer {{s}}{{amount}}</span> to {{via}}{{account}}You will get {{s}}{{fill}}$</span> {{comission}}',
											'ru'		 => 'Переведите {{s}}{{amount}}</span> на {{via}}{{account}}Вы получите {{s}}{{fill}}$</span> {{comission}}',
											'_include'	 => [
												'via'		 => H::getTemplate('pages/balance/payment_method_in_title', [
													'image'	 => $payment->get(['image' => 0]),
													'href'	 => $payment->get('url'),
													'title'	 => $payment->fget('title')
														], true),
												's'			 => '<span class="gold">',
												'amount'	 => round($offer->d('price'), 6) . $offer->fget('currency'),
												'fill'		 => $offer->fget('amountWithComission'),
												'comission'	 => '<span class="fsz_08rem">' . $offer->getComissionString() . '</span>',
												'account'	 => '<div class="al payment_detales">' . nl2br($userPayment->fget('description')) . '</div>'
											]
										]
									]) . '</div>'
									: ( empty($r['seller_check'])
											?
											T::out([
												'check_pay_n_confirm_17' => [
													'en'		 => 'Make sure you pay {{s}}{{amount}}</span> to {{via}}{{account}}Attach more screenshots and confirm once again.',
													'ru'		 => 'Убедитесь что оплатили {{s}}{{amount}}</span> на {{via}}{{account}}Прикрепите дополнительгные скриншоты и подтвердите еще раз.',
													'_include'	 => [
														'via'		 => H::getTemplate('pages/balance/payment_method_in_title', [
															'image'	 => $payment->get(['image' => 0]),
															'href'	 => $payment->get('url'),
															'title'	 => $payment->fget('title')
																], true),
														's'			 => '<span class="gold">',
														'amount'	 => round($offer->d('price'), 6) . $offer->fget('currency'),
														'account'	 => '<div class="al payment_detales">' . nl2br($userPayment->fget('description')) . '</div>'
													]
												]
											])
											: '<div class="please_pay_v">' . T::out([
												'please_check_payment_with_amount_17' => [
													'en'		 => 'Check that <span class="gold">{{amount}}</span> was {{b}}actually</span> credited to Your {{payment_system}} account',
													'ru'		 => 'Проверьте что <span class="gold">{{amount}}</span> {{b}}реально</span> поступили на Ваш {{payment_system}} счет',
													'_include'	 => [
														'b'				 => '<span class="red_bold">',
														'amount'		 => round($offer->d('price'), 6) . $offer->fget('currency'),
														'payment_system' => $payment->get('title')
													]
												]
											]) . '</div>'
									),
							'message'	 => H::getTemplate('pages/dialogs/' . (empty($r['seller_check'])
											? 'attach'
											: 'check') . '_confirmation', [
								'type'			 => 'accept_offer',
								'reconfirm'		 => $offer->get('reconfirm')
										? $offer->fget('reconfirm')
										: 0,
								'time'			 => $offer->get('autopay'),
								'remain'		 => empty($r['seller_check'])
										?
										T::out([
											'autocancel_in_without_confirmation' => [
												'en'		 => 'Without confirmation autocancel after {{t}} even if You pay',
												'ru'		 => 'Без подтверждения автоотмена через {{t}} даже если Вы оплатили',
												'_include'	 => [
													't' => '<span class="time_remain id_' . $offer->get('id') . '" timestamp="' . $offer->get('remain') . '">' . $offer->get('remain_string') . '</span>'
												]
											]
										]) . ($offer->get('reconfirm')
												? '<div style="margin:5px 0px;">' . T::out([
													'seller_message' => [
														'en'		 => 'Message from seller: {{message}}',
														'ru'		 => 'Сообщение от продавца: {{message}}',
														'_include'	 => [
															'message' => '</div><span style="font-size:0.7rem;">' . $offer->fget('reconfirm') . '</span>'
														]
													]
												])
												: '')
										: T::out([
											'autopay_in' => [
												'en'		 => 'Autopay after {{t}}',
												'ru'		 => 'Автоплатеж через {{t}}',
												'_include'	 => [
													't' => '<span class="time_remain id_' . $offer->get('id') . '" timestamp="' . $offer->get('remain') . '">' . $offer->get('remain_string') . '</span>'
												]
											]
										]),
								'amount'		 => $offer->get('amount'),
								'offer_id'		 => $offer->get('id'),
								'user_payment'	 => $userPayment->get('id'),
								'seller'		 => $offer->get('User')->get('name'),
								'uploaded_files' => join('', $offer->uploadedFilesHTML())
									], true)
						]
					]
				];
			}
		} elseif ($com == 'uploaded_images') { //?not used
			self::required([
				'offer_id' => true
					], $r);

			$offer = Offer::getBy([
						'id'			 => $r['offer_id'],
						'accepted_by'	 => User::logged()->get('id')
			]);

			return [
				'Balance' => [
					'outputUploadedFiles' => [
						'offer_id'	 => empty($offer)
								? null
								: $offer->get('id'),
						'html'		 => empty($offer)
								? ''
								: join('', $offer->uploadedFilesHTML())
					]
				]
			];
		} elseif ($com === 'delete_uploaded') {

			self::required([
				'offer_id'	 => true,
				'image_id'	 => true
					], $r);

			return [
				'Balance' => [
					'confirmByByer' => [
						'id' => self::getBy([
							'id'		 => $r['offer_id'],
							'_notfound'	 => true
						])->addEvent('Delete uploaded file')->deleteUploaded($r)->get('id')
					]
				]
			];
		} elseif ($com == 'upload') {

			self::getBy([
				'id'		 => $r['id'],
				'_notfound'	 => true
			])->addEvent('Upload file');

			self::upload($r, 'multi');
		} elseif ($com == 'send_payment_confirmation') {

			self::required([
				'offer_id' => true
					], $r);

			$offer = self::getBy([
						'id'		 => $r['offer_id'],
						'accept_by'	 => User::logged()->get('id'),
						'status'	 => 'accepted',
						'autopay'	 => [
							'_between' => [
								(new DateTime())->format('Y-m-d H:i:s'),
								'2100-00-00 00:00',
							]
						],
						'_notfound'	 => true
			]);

			if (!$offer->get('files')) {

				throw new Exception(T::out([
					'no_atachemnt' => [
						'en' => 'It is necessary to attach screenshots which can confirm payment',
						'ru' => 'Необходимо приложить скриншоты, подтверждающие платеж'
					]
				]));
			}

			$autopay = (new DateTime())->modify('+' . $offer->get('UserPaymentMethod')->get('wait') . ' hour')->format('Y-m-d H:i:s');

			$offer->sendEmailNotification('confirmed')->set([
				'status'	 => 'confirmed',
				'autopay'	 => $autopay
			])->setActions([
				'update_withdraw_orders',
					], $offer->get('user_id'))->addEvent('Confirmation has been sent');

			return [
				'Balance' => [
					'showAvailableOffers' => self::showAvailableOffers($r)
				]
			];
		} elseif ($com == 'cancel_by_byer') {

			self::required([
				'offer_id' => true
					], $r);

			self::getBy([
				'id'			 => $r['offer_id'],
				'accept_by'		 => User::logged()->get('id'),
				'status'		 => ['accepted',
					'confirmed',
					'disputed'],
				'seller_claim'	 => 'is null',
				'autopay'		 => [
					'_between' => [
						(new DateTime())->format('Y-m-d H:i:s'),
						'2100-00-00 00:00',
					]
				],
				'_notfound'		 => true
			])->addEvent('Offer cancelled by buyer')->cancelByBuyer();

			return [
				'Balance' => [
					'showAvailableOffers' => self::showAvailableOffers($r)
				]
			];
		} elseif ($com === 'get_chargeback') {//html для диалога chargeback	 TODO: remove it to offer
			self::required([
				'offer_id' => true
					], $r);

			$user = User::logged();



			if ($user->get(['image' => 0]) || $user->get(['image' => 1]) || $user->get(['image' => 2])) {
				
			} else {

				M::no([
					'error'	 => 'need_documents_before_claim',
					'action' => [
						'Site' => [
							'switchWithError' => [
								'page'		 => 'user',
								'message'	 => T::out([
									'documents_to_claim' => [
										'en' => 'You need to attach screenshots of your identification documents (passport, drive license) before sending a claim.',
										'ru' => 'Вы должны прикрепить скриншоты документов удостоверяющих личность (паспорт, права) прежде чем отправить жалобу.'
									]
								])
							]
						]
					]
				]);
			}

			$offer = self::getBy([
						'id'		 => $r['offer_id'],
						'_notfound'	 => true
			]);

			if ($offer->get('user_id') == $user->get('id')) {
				//seller send claim
			} elseif ($offer->get('accepted_by') == $user->get('id')) {
				//buyer send claim
			} else {
				throw new Exception('Offer does not deal with current user 3');
			}

			$deal = $offer->get('currency') == '_deal'
					? Deal::getBy([
						'id' => $offer->get('id')
					])
					: $offer;

			return [
				'html' => H::getTemplate('pages/withdraw/run_challenge', [
					'user_id'		 => $user->get('id'),
					'offer_id'		 => $r['offer_id'],
					'Object'		 => $offer->get('currency') == '_deal'
							? 'Deal'
							: 'Offer',
					'uploaded_files' => join('', $deal->uploadedFilesHTML())
						], true)
			];
		} elseif ($com == 'release') {

			self::required([
				'offer_id' => true
					], $r);

			$offer = self::getBy([
						'id'		 => $r['offer_id'],
						'user_id'	 => User::logged()->get('id'),
						'status'	 => 'confirmed',
						'_notfound'	 => true
					])->addEvent('Released manually')->releaseBySeller();


			return [
				'Balance' => [
					'outputOffersList' => [
						'waiting'	 => self::getWithdrawWaitingOffers(),
						'accepted'	 => self::getWithdrawAcceptedOffers(),
						'confirmed'	 => self::getWithdrawConfirmedOffers(),
						'disputed'	 => self::getDisputedOffers('seller')
					]
				]
			];
		} elseif ($com == 'ask_confirmation') {

			self::required([
				'offer_id' => true
					], $r);

			$user = User::logged();

			$offer = self::getBy([
						'id'		 => $r['offer_id'],
						'user_id'	 => $user->get('id'),
						'status'	 => 'confirmed',
						'autopay'	 => [
							'_between' => [
								(new DateTime())->format('Y-m-d H:i:s'),
								'2100-00-00 00:00',
							]
						],
						'_notfound'	 => true
			]);

			$offer->set([
				'status'	 => 'accepted',
				'autopay'	 => (new DateTime())->modify('+ ' . $offer->get('UserPaymentMethod')->get('wait') . ' hour')->format('Y-m-d H:i:s'),
				'reconfirm'	 => empty($r['message'])
						? 'Please confirm payment once again'
						: $r['message']
			])->sendEmailNotification('ask_confirmation')->setActions([
				'update_withdraw_orders'
					], $offer->get('accepted_by'))->addEvent('Additional confirmation requested');

			return [
				'Balance' => [
					'outputOffersList' => [
						'waiting'	 => self::getWithdrawWaitingOffers(),
						'accepted'	 => self::getWithdrawAcceptedOffers(),
						'confirmed'	 => self::getWithdrawConfirmedOffers(),
						'disputed'	 => self::getDisputedOffers('seller')
					]
				]
			];
		} elseif ($com == 'cancel_by_seller') { //отмена продавцом	
			self::required([
				'offer_id' => true
					], $r);

			$user = User::logged();

			$offer = self::getBy([
						'id'		 => $r['offer_id'],
						'user_id'	 => $user->get('id'),
						'status'	 => 'waiting',
						'_notfound'	 => true
					])->addEvent('Cancel by seller manually');

			$new_balance = $offer->get('amount') * 1 + $user->get('money') * 1;

			User::getBy([
				'id' => $user->get('id')
			])->set([
				'money' => $new_balance
			])->cash();

			$offer->remove();

			return [
				'Balance' => [
					'outputOffersList'		 => [
						'waiting'	 => self::getWithdrawWaitingOffers(),
						'accepted'	 => self::getWithdrawAcceptedOffers(),
						'confirmed'	 => self::getWithdrawConfirmedOffers(),
						'disputed'	 => self::getDisputedOffers('seller'),
						'balance'	 => $new_balance
					],
					'showAvailableOffers'	 => self::showAvailableOffers($r)
				]
			];
		} elseif ($com == 'accept') {

			self::required([
				'id' => true
					], $r);

			if ($r['amount'] * 1 <= 0) {
				throw new Exception(T::out([
					'amount_expected_not_zero' => [
						'en' => 'Positive value expected',
						'ru' => 'Ожидается положительная величина'
					]
				]));
			}

			$offer = self::getBy([
						'id'		 => $r['id'],
						'status'	 => 'waiting',
						'_notfound'	 => T::out([
							'offer_status_already_changed' => [
								'en' => 'Offer has been accepted by another user or cancelled by owner!',
								'ru' => 'Предложение уже принято другим пользователем или снято владельцем!'
							]
						])
					])->addEvent('Accept the offer');

			$offer->setActions([
				'update_withdraw_orders'
					], $offer->get('user_id'));


			$autopay = (new DateTime())->modify('+' . $offer->get('UserPaymentMethod')->get('wait') . ' hour')->format('Y-m-d H:i:s');

			if ($offer->d('amount') == $r['amount'] * 1) { //один оффер
				$offer = $offer->set([
					'status'		 => 'accepted',
					'autopay'		 => $autopay,
					'accepted_by'	 => User::logged()->get('id')
				]);
			} elseif ($offer->d('amount') > $r['amount'] * 1) {//divide offer
				$price = $offer->d('price') / $offer->d('amount');
				$buyback = $offer->d('buyback') / $offer->d('amount');
				$new_amount = $offer->d('amount') - $r['amount'] * 1;

				$newOffer = self::create([
							'user_id'	 => $offer->get('user_id'),
							'amount'	 => $new_amount,
							'status'	 => 'waiting'
						])->set([
					'price'		 => $new_amount * $price,
					'buyback'	 => $new_amount * $buyback,
					'currency'	 => $offer->get('currency'),
					'method_id'	 => $offer->get('method_id')
				]);

				$offer = $offer->set([
					'status'		 => 'accepted',
					'accepted_by'	 => User::logged()->get('id'),
					'autopay'		 => $autopay,
					'amount'		 => $r['amount'] * 1,
					'price'			 => $offer->d('price') - $newOffer->d('price'),
					'buyback'		 => $offer->d('buyback') - $newOffer->d('buyback')
				]);

				//remove payment offers
			} else { //the order amount is less than we need to buy
				$offer = $offer->set([
					'status'		 => 'accepted',
					'autopay'		 => $autopay,
					'accepted_by'	 => User::logged()->get('id')
				]);
			}

			$userPayment = UserPaymentMethod::getBy([
						'id'		 => $offer->get('method_id'),
						'user_id'	 => $offer->get('user_id'),
						'_notfound'	 => T::out([
							'payment_detales_not_found_2' => [
								'en' => 'Payment details not found!',
								'ru' => 'Платежные реквизиты не найдены!'
							]
						])
			]);

			$payment = Payment::getBy([
						'id' => $userPayment->get('payment_id')
			]);

			if ($userPayment->get('mode') === 'manual') {

				$r['amount'] = $r['amount'] - $offer->d('amount');

				return [
					'Balance' => [
						'changeRequestedAmount'	 => [
							'amount' => $r['amount']
						],
						'showAvailableOffers'	 => self::showAvailableOffers($r),
						'showPaymentDetails'	 => [
							'offer_id'	 => $offer->get('id'),
							'title'		 => '<div class="please_pay_v">' . T::out([
								'please_pay_n_confirm_777' => [
									'en'		 => 'Transfer {{s}}{{amount}}</span> to {{via}}{{account}}You will get {{s}}{{fill}}$</span> {{comission}}',
									'ru'		 => 'Переведите {{s}}{{amount}}</span> на {{via}}{{account}}Вы получите {{s}}{{fill}}$</span> {{comission}}',
									'_include'	 => [
										'via'		 => H::getTemplate('pages/balance/payment_method_in_title', [
											'image'	 => $payment->get(['image' => 0]),
											'href'	 => $payment->get('url'),
											'title'	 => $payment->fget('title')
												], true),
										's'			 => '<span class="gold">',
										'amount'	 => round($offer->d('price'), 6) . $offer->fget('currency'),
										'fill'		 => $offer->fget('amountWithComission'),
										'comission'	 => '<span class="fsz_08rem">' . $offer->getComissionString() . '</span>',
										'account'	 => '<div class="al payment_detales">' . nl2br($userPayment->fget('description')) . '</div>'
									]
								]
							]) . '</div>',
							'message'	 => H::getTemplate('pages/dialogs/attach_confirmation', [
								'type'			 => 'accept_offer',
								'amount'		 => $offer->get('amount'),
								'time'			 => $offer->get('autopay'),
								'offer_id'		 => $offer->get('id'),
								'remain'		 => T::out([
									'autocancel_in_without_confirmation' => [
										'en'		 => 'Without confirmation autocancel after {{t}} even if You pay',
										'ru'		 => 'Без подтверждения автоотмена через {{t}} даже если Вы оплатили',
										'_include'	 => [
											't' => '<span class="time_remain id_' . $offer->get('id') . '" timestamp="' . $offer->get('remain') . '">' . $offer->get('remain_string') . '</span>'
										]
									]
								]),
								'user_payment'	 => $userPayment->get('id'),
								'seller'		 => $offer->get('User')->get('name'),
								'uploaded_files' => join('', $offer->uploadedFilesHTML())
									], true)
						]
					]
				];
			} else {

				$d = $r;

				$d['amount'] = max(0, $r['amount'] - $offer->d('amount'));


				return [
					'Balance' => [
						'showAvailableOffers'	 => self::showAvailableOffers($d),
						'changeRequestedAmount'	 => [
							'amount' => $d['amount']
						],
						'gotoPaymentPage'		 => [
							'url' => $offer->get('add_parameters_to_payment_url')
						]
					]
				];
			}

//=============== MY WITHDRAWAL REQUESTS ==============
		} elseif ($com == 'my_withdrawal_requests') {

			$action = Action::getBy([
						'key' => 'update_withdraw_orders_seller'
			]);

			if (!empty($action) && UserAction::getBy([
						'user_id'	 => User::logged()->get('id'),
						'action_id'	 => $action->get('id'),
						'_return'	 => 'count'
					]) > 0) {
				UserAction::getBy([
					'user_id'	 => User::logged()->get('id'),
					'action_id'	 => $action->get('id')
				])->remove();
			}

			return [
				'Balance' => [
					'outputOffersList' => [
						'waiting'	 => self::getWithdrawWaitingOffers(),
						'accepted'	 => self::getWithdrawAcceptedOffers(),
						'confirmed'	 => self::getWithdrawConfirmedOffers(),
						'disputed'	 => self::getDisputedOffers('seller')
					]
				]
			];
		}
	}

	public static function uploadSuccess($r, $obj) {
		if (!isset($r['image'])) { //reshow list
			//throw new Exception($r['chargeback']);
			if (isset($r['chargeback'])) {
				M::jsonp([
					'parent.A.run' => [
						'Balance' => [
							'chargeBackUploadedFiles' => [
								'html'		 => join('', $obj->uploadedFilesHTML()),
								'offer_id'	 => $r['chargeback']
							]
						]
					]
				]);
			} else {
				M::jsonp([
					'parent.A.run' => [
						'Balance' => [
							'outputUploadedFiles' => [
								'html' => join('', $obj->uploadedFilesHTML())
							]
						]
					]
				]);
			}
		} else {//reshow current image
			$files = $obj->get('files');
			if (isset($r['chargeback'])) {

				M::jsonp([
					'parent.A.run' => [
						'Balance' => [
							'chargeBackShowImage' => [
								'obj'		 => get_called_class(),
								'image'		 => B::baseURL() . $files[$r['image'] * 1] . '?s=' . filectime('./' . $files[$r['image'] * 1]),
								'id'		 => $obj->get('id'),
								'image_id'	 => $r['image'],
								'get'		 => [
									'chargeback' => $r['chargeback']
								]
							]
						]
					]
				]);
			} else {
				M::jsonp([
					'parent.A.run' => [
						'Balance' => [
							'showImage' => [
								'obj'		 => get_called_class(),
								'image'		 => $files[$r['image'] * 1],
								'id'		 => $obj->get('id'),
								'image_id'	 => $r['image']
							]
						]
					]
				]);
			}
		}
	}

	public static function uploadFail($e) {
		M::jsonp([
			'parent.A.run' => [
				'D' => [
					'show' => [
						'title'	 => $e->getMessage(),
						'css'	 => [
							'A' => [
								'getCss' => [
									'type' => 'no'
								]
							]
						]
					]
				]
			]
		]);
	}

	public function uploadRestricted($image_id = null, $noexception = false) {

		if (empty($image_id) && $image_id !== 0) {
			return $this;
		}

		$path = '.' . B::baseDir() . $this->get(['image' => $image_id * 1]);

		if (!file_exists($path)) {
			throw new Exception($path);
		}

		$fileDate = filectime($path);
		$offerDate = (new DateTime($this->get('changed')))->getTimestamp();

		if ($fileDate < $offerDate) {
			if (empty($noexception)) {
				throw new Exception(T::out([
					'unable_to_reload_or_remove_because_changed_too_late' => [
						'en' => 'Unable to reload or remove file which was already sent to contractor',
						'ru' => 'Невозможно перезагрузить или удалить файл, уже отправленный контрагенту'
					]
				]));
			} else {
				return false;
			}
		}

		return $this;
	}

	public static function showAvailableOffers($r) {

		if (isset($r['update_sell_board'])) {
			$r['payment_id'] = $r['update_sell_board'];
		}

		$user = User::logged();

		return [
			'selling'	 => empty($r['payment_id'])
					? false
					: self::getSellOffers([
						'payment_id' => $r['payment_id'],
						'user_id'	 => $user->get('id'),
						'amount'	 => !empty($r['amount'])
								? $r['amount']
								: null,
						'page'		 => empty($r['page'])
								? 0
								: $r['page'],
						'filter'	 => empty($r['filter'])
								? null
								: $r['filter']
					]),
			'currencies' => empty($r['payment_id'])
					? false
					: Payment::getBy([
						'id'		 => $r['payment_id'],
						'_notfound'	 => true
					])->getRadioGroupHTML(),
			'accepted'	 => self::getAcceptedOffers([
				'user_id' => $user->get('id')
			]),
			'confirmed'	 => self::getConfirmedOffersForByer([
				'user_id' => $user->get('id')
			]),
			'payment_id' => empty($r['payment_id'])
					? null
					: $r['payment_id'],
			'initiated'	 => empty($r['initiated'])
					? 0
					: $r['initiated'],
			'disputed'	 => self::getDisputedOffers('customer'),
			'balance'	 => $user->get('money')
		];
	}

	public function get($what, $data = false) {

		if (in_array($what, ['remain',
					'remain_string'])) {

			$remain = (new DateTime())->diff(new DateTime($this->get('autopay')));
			$sign = ($remain->format('%R') . '1') * 1;

			if ($what == 'remain') {
				return $sign < 0
						? false
						: ($remain->days * 86400 + $remain->h * 3600 + $remain->i * 60 + $remain->s);
			} else {

				$hours = $remain->days * 24 + $remain->h;

				return join(':', [
					$hours < 10
							? '0' . $hours
							: $hours,
					$remain->i < 10
							? '0' . $remain->i
							: $remain->i,
					$remain->s < 10
							? '0' . $remain->s
							: $remain->s
				]);
			}
		}

		if ($what == 'UserPaymentMethod') {

			return UserPaymentMethod::getBy([
						'id'		 => $this->get('method_id'),
						'_notfound'	 => true
			]);
		}

		if ($what == 'PaymentMethod') {
			return Payment::getBy([
						'id'		 => $this->get('UserPaymentMethod')->get('payment_id'),
						'_notfound'	 => true
			]);
		}

		if ($what == 'payment_url') {
			return UserPaymentMethod::getBy([
						'id'			 => $this->get('method_id'),
						'description'	 => 'is not null',
						'mode'			 => 'automatic', //TODO: remove this check
						'_notfound'		 => true
					])->get('description');
		}

		if ($what == 'add_parameters_to_payment_url'/* && !empty($data) */) {
			$payment_url = UserPaymentMethod::getBy([
						'id'		 => $this->get('method_id'),
						'mode'		 => 'automatic',
						'_notfound'	 => true
					])->get('description');
			return $payment_url . (strpos($payment_url, '?') !== false
							? '&'
							: '?') . 'transaction_id=' . $this->get('id');
		}

		if ($what == 'User' || $what == 'Seller') {
			return User::getBy([
						'id' => $this->get('user_id')
			]);
		}

		if ($what == 'Buyer') {
			return User::getBy([
						'id' => $this->get('accepted_by')
			]);
		}

		if ($what == 'methods') { //to output
			$price = empty($data)
					? $this->d('price')
					: $data / $this->d('amount') * $this->d('price');

			return join('', [
				'<div>' . round($price, 6) . ' ' . $this->get('currency') . '</div>',
				'<img src="' . B::baseURL() . $this->get('PaymentMethod')->get(['image' => 0]) . '" style="width:40px; display:inline-block; vertical-align:middle; margin:5px;" alt="' . $this->get('method_id') . '"/>'
			]);
		}

		if ($what == 'methodHTML') {
			return '<img src="' . B::baseURL() . $this->get('PaymentMethod')->get(['image' => 0]) . '" alt="' . $this->get('method_id') . '" class="paymentmethod_in_offer_line"/>';
		}

		if ($what == 'payHTML') {
			$price = empty($data)
					? $this->d('price')
					: $data / $this->d('amount') * $this->d('price');
			return round($price, 6) . ' ' . $this->get('currency');
		}

		if ($what == 'payHTMLnoCurrency') {
			$price = empty($data)
					? $this->d('price')
					: $data / $this->d('amount') * $this->d('price');
			return round($price, 6);
		}

		if ($what == 'formattedAmount') {
			return $this->d('amount');
		}

		if ($what == 'offerAmount') {
			return $this->d('amount');
		}

		if ($what == 'comission') {

			$amount = empty($data)
					? $this->d('amount')
					: min($this->d('amount'), $data);

			return round(max(S::getBy([
								'key'		 => 'minimum',
								'_notfound'	 => [
									'key'	 => 'minimum',
									'val'	 => 1
								]
							])->d('val'), $amount * S::getBy([
								'key'		 => 'comission',
								'_notfound'	 => [
									'key'	 => 'comission',
									'val'	 => 0.5
								]
							])->d('val') / 100), 2);
		}

		if ($what == 'amountWithComission') {
			if (empty($data)) {
				return $this->d('amount') - $this->d('comission');
			} else {
				return min($data, $this->d('amount')) - $this->d('comission', $data);
			}
		}


		if ($what == 'roundPriceWithCurrency') {

			if ($this->d('amount') == 0 || $part = 0 || $this->d('price') == 0) {
				return 0;
			}

			if (empty($data)) {
				$data = 0;
			}

			$part = $data / $this->d('amount');

			if ($this->d('amountWithComission', $data) <= 0) {
				$price = 0;
			} else {
				$price = $this->d('price') * $part / $this->d('amountWithComission', $data);
			}

			if ($price <= 0.01) {

				$price = $this->get('amountWithComission', $data) * 1 / $this->d('price') / $part;

				return round($price, 2) . ' $/' . $this->get('currency');
			} else {

				if ($price > 0.01) {
					$signs = 3;
				} elseif ($price > 0.001) {
					$signs = 4;
				} elseif ($price > 0.0001) {
					$signs = 6;
				} else {
					$signs = 7;
				}

				return round($price, $signs) . ' ' . $this->get('currency') . '/$';
			}
		}

		if ($what == 'roundPrice') {

			if ($this->d('amount') == 0) {
				return 0;
			}

			if (empty($data)) {
				$data = 0;
			}

			$part = $data / $this->d('amount');

			if ($this->d('amountWithComission', $data) <= 0) {
				$price = 0;
			} else {
				$price = $this->d('price') * $part / $this->d('amountWithComission', $data);
			}

			if ($price > 0.01) {
				$signs = 3;
			} elseif ($price > 0.001) {
				$signs = 4;
			} elseif ($price > 0.0001) {
				$signs = 6;
			} else {
				$signs = 7;
			}

			return round($price, $signs);
		}

		if ($what == 'roundBuyBackPrice') {

			if ($this->d('amount') == 0) {
				return 0;
			}

			if (empty($data)) {
				$data = 0;
			}

			$part = $data / $this->d('amount');

			if ($this->d('amountWithComission', $data) <= 0) {
				$price = 0;
			} else {
				$price = $this->d('buyback') * $part / $this->d('amountWithComission', $data);
			}

			if ($price > 0.01) {
				$signs = 3;
			} elseif ($price > 0.001) {
				$signs = 4;
			} elseif ($price > 0.0001) {
				$signs = 6;
			} else {
				$signs = 7;
			}

			return round($price, $signs);
		}


		if ($what == 'iam' || $what == 'notme' || $what == 'myDealRole' || $what == 'hisDealRole') {

			$user = empty($data)
					? User::logged()
					: $data;

			if ($this->get('user_id') == $user->get('id')) {
				return $what == 'iam' || $what == 'hisDealRole'
						? 'seller'
						: 'customer';
			} elseif ($this->get('accepted_by') == $user->get('id')) {
				return $what == 'iam' || $what == 'myDealRole'
						? 'customer'
						: 'seller';
			} else {
				throw new Exception('Offer does not deal with current user');
			}
		}

		return parent::get($what, $data);
	}

	/**
	 * Логируем события
	 * 
	 * @param type $event - description
	 * @param type $user - инициатор события
	 * @param type $data
	 * @return \Offer
	 */
	public function addEvent($event, $user = null, $data = null) {

		Log::getBy([
			'id'		 => '_new',
			'_notfound'	 => [
				'user_id'	 => empty($user)
						? User::logged()->get('id')
						: ($user instanceof User
								? $user->get('id')
								: null),
				'offer_id'	 => $this->get('id'),
				'event'		 => $event,
				'data'		 => $data,
				'date'		 => (new DateTime())->format('Y-m-d H:i:s'),
				'useragent'	 => isset($_SERVER['HTTP_USER_AGENT'])
						? $_SERVER['HTTP_USER_AGENT']
						: 'uncknown',
				'ip'		 => $_SERVER['REMOTE_ADDR']
			]
		]);

		return $this;
	}

	public function outputEvents() {

		$events = Log::getBy([
					'offer_id'	 => $this->get('id'),
					'_return'	 => [0 => 'object'],
					'_order'	 => '`date`'
		]);

		$h = [];

		if (!empty($events)) {
			foreach ($events as $event) {
				$h[] = $event->html();
			}
		}

		return join('', $h);
	}

	/**
	 * Взыскиваем комиссию
	 * @return type
	 */
	public function comission() {

		$admin = User::getBy([
					'role'		 => 'admin',
					'_notfound'	 => [
						'role' => 'admin'
					]
		]);

		$admin->set([
			'money' => $admin->d('money') + $this->d('comission')
		]);

		return $this->set([
					'amount' => $this->d('amount') - $this->d('comission')
		]);
	}

	public function removeAllExperts() {
		//удаляем экспертов и возвращаем суммы с их депозитов
		if ($this->get('status') == 'arbitrage') {

			Expert::removeAllExperts($this->get('id'));

			return static::getBy([
						'id' => $this->get('id')
			]);
		} else {
			return $this;
		}
	}

	public function returnArbitrageDeposit($setActionFor = false) {

		//если нечего возвращать - просто выходим
		if ($this->d('seller_hold') + $this->d('customer_hold') == 0) {
			return $this;
		}

		if ($this->d('seller_hold')) {
			User::getBy([
				'id'		 => $this->get('user_id'),
				'_notfound'	 => true
			])->inc([
				'money' => max(0, $this->d('seller_hold'))
			]);
		}

		if ($this->d('customer_hold')) {
			User::getBy([
				'id'		 => $this->get('accepted_by'),
				'_notfound'	 => true
			])->inc([
				'money' => max(0, $this->d('customer_hold'))
			]);
		}

		//actions
		if (!empty($setActionFor) && (empty($_SESSION['User']) || $setActionFor != User::logged()->get('id'))) {
			$this->setActions(['update_balance'], $setActionFor);
		}

		return $this->set([
					'seller_hold'	 => 0,
					'customer_hold'	 => 0
		]);
	}

	/**
	 * 
	 * @param type $automatic - in automatic mode === autopay, claim_autopay, remote_payment  (email message key)
	 * @return type
	 */
	public function releaseBySeller($automatic = false) {

		User::getBy([
			'id'		 => $this->get('accepted_by'),
			'_notfound'	 => empty($automatic)
					? true
					: false
		])->inc([
			'money' => $this->comission()->get('amount') * 1
		]);

		return $this->returnArbitrageDeposit($this->get('user_id'))
						->rateSeller(!empty($automatic) && in_array($automatic, [
									'claim_autopay',
									'customer_right'
								])
										? 'balance_failure'
										: 'balance_success')
						->setActions([
							'update_withdraw_orders',
							'update_balance',
							$this->get('status') == 'arbitrage'
									? 'update_arbitrage_list'
									: false
								], [$this->get('accepted_by'),
							$this->get('user_id')])
						->sendEmailNotification('balance_filled')
						->sendEmailNotification($automatic)->set([
					'status'		 => 'completed',
					'customer_claim' => '',
					'seller_claim'	 => '',
					'reconfirm'		 => ''
		]);
	}

	public function getShortDescription() {

		try {
			$payment = Payment::getBy([
						'id'		 => UserPaymentMethod::getBy([
							'id'		 => $this->get('method_id'),
							'user_id'	 => $this->get('user_id'),
							'_notfound'	 => true
						])->get('payment_id'),
						'_notfound'	 => true
			]);

			return T::out([
						'short_description_2' => [
							'en'		 => '{{buyer}} filled up the balance to {{amount}}$ {{comission}} via {{system}} from {{seller}}',
							'ru'		 => '{{buyer}} пополнял баланс на {{amount}}$ {{comission}} через {{system}} у {{seller}}',
							'_include'	 => [
								'buyer'		 => User::getBy([
									'id'		 => $this->get('accepted_by'),
									'_notfound'	 => true
								])->get('name'),
								'seller'	 => User::getBy([
									'id'		 => $this->get('user_id'),
									'_notfound'	 => true
								])->get('name'),
								'amount'	 => $this->get('amountWithComission'),
								'comission'	 => $this->getComissionString(),
								'system'	 => $payment->get('title')
							]
						]
			]);
		} catch (Exception $e) {
			return 'Deal ' . $offer->get('id') . 'not available';
		}
	}

	public function sendEmailNotification($type = false) {


		if (empty($type)) {
			return $this;
		}

		if ($type == 'chargeback') {
			if ($this->get('seller_claim')) {
				$claimer = 'seller';
				$email = $this->get('Buyer')->get('confirmed_email');
			} else {
				$claimer = 'customer';
				$email = $this->get('Seller')->get('confirmed_email');
			}
		} elseif ($type == 'spam_reported') {
			$email = $this->get('Seller')->get('confirmed_email');
		} elseif ($type == 'arbitrage') {

			//throw new Exception($this->get('customer_claim') . ',' . $this->get('seller_claim'));

			if ($this->get('customer_claim')) {
				$claimer = 'seller';
				$email = $this->get('Buyer')->get('confirmed_email');
			} else {
				$claimer = 'customer';
				$email = $this->get('Seller')->get('confirmed_email');
			}
		} else {
			$claimer = false;

			$email = in_array($type, [
						'cancel',
						'confirmed',
						'autopay',
						'remote_payment'
					])
					? $this->get('Seller')->get('confirmed_email')
					: $this->get('Buyer')->get('confirmed_email');
		}



		if ($type === 'autocancel') {
			$message = T::out([
						'notification_autocancel_3' => [
							'en'		 => 'The deal "<b>{{deal}}</b>" has automatically canceled because of timeout.',
							'ru'		 => 'Сделка «<b>{{deal}}</b>» автоматически отменена по истечении срока ожидания.',
							'_include'	 => [
								'deal' => $this->getShortDescription()
							]
						]
			]);
			$link = B::setProtocol('https:', B::baseURL()) . 'balance';
		} elseif ($type == 'spam_reported') {

			$message = T::out([
						'one of your offers cancelled because wrong payment details' => [
							'en' => 'One of your offers has been cancelled because of users reports about wrong payment details.',
							'ru' => 'Одно из ваших предложений отменено, потому что пользователи сообщают о неправильных реквизитах.'
						]
			]);

			$link = B::setProtocol('https:', B::baseURL()) . 'withdraw';
		} elseif ($type == 'autopay' || $type == 'claim_autopay') {

			$message = T::out([
						'notification_autopay' => [
							'en'		 => 'The deal "<b>{{deal}}</b>" has been automatically paid because of timeout.',
							'ru'		 => 'Сделка «<b>{{deal}}</b>» автоматически оплачена по истечении срока ожидания.',
							'_include'	 => [
								'deal' => $this->getShortDescription()
							]
						]
			]);
			$link = B::setProtocol('https:', B::baseURL()) . 'balance';
		} elseif ($type == 'chargeback') { //ok
			$subject = T::out([
						'attention_claim (in email)' => [
							'en' => 'Chargeback request',
							'ru' => 'Претензия по сделке'
						]
			]);

			$description = $this->get('currency') == '_deal'
					? Deal::getBy([
						'id' => $this->get('id')
					])->getVeryShortDescription()
					: $this->getShortDescription();

			$message = T::out([
						'notification_claim2' => [
							'en'		 => '{{claimer}} put a claim on the deal "<b>{{deal}}</b>":<p><b>{{claim}}</b></p>In case of no reaction from your side the deal will be completed in contractor favour {{time}}. You can put a counter claim.',
							'ru'		 => '{{claimer}} выставил претензию по сделке «<b>{{deal}}</b>»:<p><b>{{claim}}</b></p>В случае отсутствия реакции с вашей стороны сделка будет завершена в пользу контрагента {{time}}. Вы можете выставить встречную претензию.',
							'_include'	 => [
								'deal'		 => $description,
								'time'		 => $this->get('autopay'),
								'claimer'	 => $claimer == 'seller'
										? T::out([
											'seller_VHS' => [
												'en' => 'The seller',
												'ru' => 'Продавец'
											]
										])
										: T::out([
											'customer_VHS' => [
												'en' => 'The customer',
												'ru' => 'Покупатель'
											]
										]),
								'claim'		 => $this->fget($claimer . '_claim')
							]
						]
			]);

			$link = B::setProtocol('https:', B::baseURL()) . ($claimer == 'seller'
							? ($this->get('currency') == '_deal'
									? 'seller'
									: 'balance')
							: ($this->get('currency') == '_deal'
									? 'customer'
									: 'withdraw'));
		} elseif ($type == 'arbitrage') { //ok
			$subject = T::out([
						'attention_counter_claim (in email)' => [
							'en' => 'Counter claim',
							'ru' => 'Встречная претензия'
						]
			]);

			$description = $this->get('currency') == '_deal'
					? Deal::getBy([
						'id' => $this->get('id')
					])->getVeryShortDescription()
					: $this->getShortDescription();

			$message = T::out([
						'notification_counter_claim_2' => [
							'en'		 => '{{claimer}} put a counter claim on the deal "<b>{{deal}}</b>":<p><b>{{claim}}</b></p>Internal arbitration mechanism has been processed.',
							'ru'		 => '{{claimer}} выставил встречную претензию по сделке «<b>{{deal}}</b>»:<p><b>{{claim}}</b></p>Запущен механизм внутреннего арбитража.',
							'_include'	 => [
								'deal'		 => $description,
								'time'		 => $this->get('autopay'),
								'claimer'	 => $claimer == 'seller'
										? T::out([
											'seller_VHS' => [
												'en' => 'The seller',
												'ru' => 'Продавец'
											]
										])
										: T::out([
											'customer_VHS' => [
												'en' => 'The customer',
												'ru' => 'Покупатель'
											]
										]),
								'claim'		 => $this->fget($claimer . '_claim')
							]
						]
			]);
			$link = B::setProtocol('https:', B::baseURL()) . 'arbitrage';
		} elseif ($type == 'balance_filled') {

			$subject = T::out([
						'balance filled subject (in email)' => [
							'en' => 'Balance filled',
							'ru' => 'Баланс пополнен'
						]
			]);

			$message = T::out([
						'notification_balance_filled_2' => [
							'en'		 => 'Your balance in system has been succesfully filled on {{amount}}$',
							'ru'		 => 'Ваш баланс в системе успешно пополнен на {{amount}}$',
							'_include'	 => [
								'amount' => $this->get('amount')
							]
						]
			]);

			$link = B::setProtocol('https:', B::baseURL()) . 'balance';
		} elseif ($type == 'remote_payment') { // -> seller
			$message = T::out([
						'withdraw_order_completed' => [
							'en'		 => 'Withdraw order for {{amount}}$ completed',
							'ru'		 => 'Заявка на вывод средств в размере {{amount}}$ завершена',
							'_include'	 => [
								'amount' => $this->get('amount')
							]
						]
			]);

			$link = B::setProtocol('https:', B::baseURL()) . 'withdraw';
		} elseif ($type == 'ask_confirmation') { // -> buyer
			$subject = T::out([
						'please confirm payment_subject (in email)' => [
							'en' => 'Please confirm payment',
							'ru' => 'Пожалуйста подтвердите платеж'
						]
			]);

			$message = T::out([
						'notification_ask_confirmation' => [
							'en'		 => 'Seller ask to confirm the payment by deal "<b>{{deal}}</b>" once again. In case of no reaction from your side deal will be automatically canceled {{time}} even if you have already paid it. Use chargeback option if can not find silution with the seller.',
							'ru'		 => 'Продавец просит подтвердить платеж по сделке «<b>{{deal}}</b>» еще раз. В случае отсутствия реакции с вашей стороны сделка будет автоматичсеки отменена {{time}} даже если вы ее уже оплатили. Используйте механизм оспаривания если не можете договориться с продавцом',
							'_include'	 => [
								'deal'	 => $this->getShortDescription(),
								'time'	 => $this->get('autopay')
							]
						]
			]);
			$link = B::setProtocol('https:', B::baseURL()) . 'balance';
		} elseif ($type == 'confirmed') { // -> seller
			$subject = T::out([
						'seller_has confirmed payment (in email)' => [
							'en' => 'Check receiving of payment',
							'ru' => 'Проверьте поступление платежа'
						]
			]);

			$message = T::out([
						'notification_set_confirmed' => [
							'en'		 => 'Buyer sent confirmation for deal "<b>{{deal}}</b>"{{once_again}}. In case of no reaction from your side automatic payment will be processed {{time}}.',
							'ru'		 => 'Покупатель{{once_again}} отправил подтверждение платежа по сделке «<b>{{deal}}</b>». В случае отсутствия реакции с вашей стороны {{time}} будет произведен автоматический платеж.',
							'_include'	 => [
								'deal'		 => $this->getShortDescription(),
								'time'		 => $this->get('autopay'),
								'once_again' => $this->get('reconfirm')
										? T::out([
											'once_agaiGJHK' => [
												'en' => ' once again',
												'ru' => ' еще раз'
											]
										])
										: ''
							]
						]
			]);

			$link = B::setProtocol('https:', B::baseURL()) . 'withdraw';
		} elseif ($type == 'cancel') { //duplicated
			$message = T::out([
						'notification_deal_cancel3' => [
							'en'		 => 'The deal "<b>{{deal}}</b>" has canceled by buyer',
							'ru'		 => 'Сделка «<b>{{deal}}</b>» отменена покупателем',
							'_include'	 => [
								'deal' => $this->getShortDescription()
							]
						]
			]);
			$link = B::setProtocol('https:', B::baseURL()) . 'balance';
		} elseif (in_array($type, ['seller_right',
					'customer_right'])) {

			$link = B::setProtocol('https:', B::baseURL());

			if ($type == 'seller_right') {
				$message = T::out([
							'seller_right_(email notification)' => [
								'en'		 => 'The vote of the experts disputed deal "<b>{{deal}}</b>" resolved in favor of the SELLER.',
								'ru'		 => 'В результате голосования экспертов спорная сделка «<b>{{deal}}</b>» разрешена в пользу ПРОДАВЦА.',
								'_include'	 => [
									'deal' => $this->getShortDescription()
								]
							]
				]);
			} else {
				$message = T::out([
							'seller_right_(email notification)' => [
								'en'		 => 'The vote of the experts disputed deal "<b>{{deal}}</b>" resolved in favor of the CUSTOMER.',
								'ru'		 => 'В результате голосования экспертов спорная сделка «<b>{{deal}}</b>» разрешена в пользу ПОКУПАТЕЛЯ.',
								'_include'	 => [
									'deal' => $this->getShortDescription()
								]
							]
				]);
			}

			$seller = User::getBy([
						'id' => $this->get('user_id')
			]);

			$customer = User::getBy([
						'id' => $this->get('accepted_by')
			]);

			$emails = [
				!empty($seller) && $seller->get('confirmed_email')
						? $seller->get('confirmed_email')
						: false,
				!empty($customer) && $customer->get('confirmed_email')
						? $customer->get('confirmed_email')
						: false
			];

			foreach ($emails as $email) {
				if (!empty($email)) {

					Mail::send([
						'to'		 => $email,
						'from_name'	 => 'CrowdEscrow.biz',
						'reply_to'	 => 'admin@crowdescrow.biz',
						'priority'	 => 0,
						'subject'	 => 'CrowdEscrow.biz ' . T::out([
							'notification_arbitrage_result' => [
								'en' => 'Arbitrage results notification',
								'ru' => 'Уведомление о результатах арбитража'
							]
						]),
						'html'		 => H::getTemplate('email/notification', [
							'header'	 => 'CrowdEscrow.biz ' . T::out([
								'notification_arbitrage_result' => [
									'en' => 'Arbitrage results notification',
									'ru' => 'Уведомление о результатах арбитража'
								]
							]),
							'name'		 => 'CrowdEscrow.biz',
							'message'	 => $message,
							'link'		 => $link
								], 'addDelimeters')
					]);
				}
			}

			return $this;
		}



		//TODO: set what notifications he would like to receive

		if (!empty($message) && !empty($email)) {

			if (empty($subject)) {
				$subject = T::out([
							'notification_change_deal_status' => [
								'en' => 'Deal status change notification',
								'ru' => 'Уведомление об изменении статуса сделки'
							]
				]);
			}

			Mail::send([
				'to'		 => $email,
				'from_name'	 => 'CrowdEscrow.biz',
				'reply_to'	 => 'admin@crowdescrow.biz',
				'priority'	 => 0,
				'subject'	 => 'CrowdEscrow.biz ' . $subject,
				'header'	 => $subject,
				'html'		 => H::getTemplate('email/notification', [
					'name'		 => 'CrowdEscrow.biz',
					'message'	 => $message,
					'link'		 => $link
						], 'addDelimeters')
			]);
		}

		return $this;
	}

	/**
	 *  glue order with present or set status waiting
	 * 
	 * $automatic = false || autocancel  === email notification key
	 * 
	 */
	public function cancelByBuyer($automatic = false) {

		//все ожидающие предложения данного пользователя
		$otherHisOffers = self::getBy([
					'status'	 => 'waiting',
					'user_id'	 => $this->get('user_id'),
					'_return'	 => [0 => 'object']
		]);

		//set user action to update list of accepted and withdraw orders

		$this->setActions([
			'update_withdraw_orders',
			'update_arbitrage_list',
			'update_balance'
				], $this->get('user_id'))->returnArbitrageDeposit($this->get('accepted_by'));

		//нам надо найти оферы с таким же пользователем и таким же методом оплаты
		if (!empty($otherHisOffers)) {
			foreach ($otherHisOffers as $offer) {

				if ($offer->get('method_id') == $this->get('method_id')) {//заказ с таким же вариантом оплаты
					$offer->set([
						'amount'	 => $this->get('amount') * 1 + $offer->get('amount') * 1,
						'price'		 => $this->d('price') + $offer->d('price'),
						'buyback'	 => $this->d('buyback') + $offer->d('buyback')
					]);
					$this->sendEmailNotification($automatic)->remove();
					return;
				}
			}
		}

		self::getBy([
			'id'		 => '_new',
			'_notfound'	 => [
				'user_id'			 => $this->get('user_id'),
				'method_id'			 => $this->get('method_id'),
				'amount'			 => $this->get('amount'),
				'price'				 => $this->get('price'),
				'buyback'			 => $this->get('buyback'),
				'currency'			 => $this->get('currency'),
				'status'			 => 'waiting',
				'accepted_by'		 => null,
				'autopay'			 => null, //$this->get('autopay'),
				'customer_claim'	 => '',
				'seller_claim'		 => '',
				'reconfirm'			 => '',
				'key_access'		 => '',
				'customer_declines'	 => $this->get('customer_declines'),
				'seller_declines'	 => $this->get('seller_declines'),
				'customer_hold'		 => 0,
				'seller_hold'		 => 0
			]
		]);

		$this->sendEmailNotification($automatic)->remove();

		return;
	}

	/**
	 * 
	 * return offers on the market
	 * 
	 * @param type $input = [
	  'user' => byer
	 * 		'amount' => amount,
	 * 		'payment_id'
	  ]
	 * @return boolean
	 */
	public static function getSellOffers($input) {

		//TODO: add pagination

		if (empty($input['amount'])) {
			return false;
		}

		//check which currency we need to find

		$variants = Payment::getBy([
					'id'		 => $input['payment_id'],
					'_notfound'	 => true
				])->get('currencies', 'throw');

		if (empty($input['filter'])) {
			if (empty($_SESSION['choosen_filter_currency'])) {
				$filter = current($variants);
			} else {
				if (in_array($_SESSION['choosen_filter_currency'], $variants)) {
					$filter = $_SESSION['choosen_filter_currency'];
				} else {
					unset($_SESSION['choosen_filter_currency']);
					$filter = current($variants);
				}
			}
		} else {
			unset($_SESSION['choosen_filter_currency']);
			if (in_array($input['filter'], $variants)) {
				$filter = $input['filter'];
				$_SESSION['choosen_filter_currency'] = $filter;
			} else {
				$filter = current($variants);
			}
		}

		$minimum = S::getBy([
					'key'		 => 'minimum',
					'_notfound'	 => [
						'key'	 => 'minimum',
						'val'	 => 1
					]
				])->d('val');

		$comission = S::getBy([
					'key'		 => 'comission',
					'_notfound'	 => [
						'key'	 => 'comission',
						'val'	 => 0.5
					]
				])->d('val') / 100;

		$page = empty($input['page'])
				? 0
				: $input['page'];

		$screen = S::getBy([
					'key'		 => 'records_on_the_page',
					'_notfound'	 => [
						'key'	 => 'records_on_the_page',
						'val'	 => 3
					]
				])->d('val');

		//get hidden_offers
		$hidden = Hide::getBy([
					'user_id'	 => $input['user_id'],
					'_return'	 => ['offer_id' => 'object']
		]);

		$hidden_ids = empty($hidden)
				? false
				: array_keys($hidden);

		//get total pages

		$db = Yii::app()->db->createCommand("
				SELECT count(*) as `count`
				FROM `escrow_offer` 
				LEFT JOIN `escrow_userpaymentmethod` ON `escrow_offer`.`method_id` = `escrow_userpaymentmethod`.`id`
				LEFT JOIN `escrow_payment` ON `escrow_payment`.`id` = `escrow_userpaymentmethod`.`payment_id`	
				WHERE `escrow_payment`.`id` = :payment_id
				AND ((`escrow_offer`.`amount` - GREATEST( :amount *" . $comission . "," . $minimum . "))/`escrow_offer`.`price`) > 0
				AND `escrow_userpaymentmethod`.`description` != ''
				AND `escrow_offer`.`currency` = :currency
				AND `escrow_offer`.`user_id` != :user_id
				AND `escrow_offer`.`status` = 'waiting' " .
						(empty($hidden_ids)
								? ''
								: "AND `escrow_offer`.`id` not in (" . join(",", $hidden_ids) . ")")
				)->query([
			'amount'	 => $input['amount'],
			'currency'	 => $filter,
			'payment_id' => $input['payment_id'],
			'user_id'	 => $input['user_id']
		]);

		while (($row = $db->read()) != false) {
			$pages = ceil($row['count'] / $screen);
		}

		//throw new Exception($pages);

		$db = Yii::app()->db->createCommand("
				SELECT `escrow_offer`.`id` as `offer_id`,
					   ((`escrow_offer`.`amount` - GREATEST( :amount *" . $comission . "," . $minimum . "))/`escrow_offer`.`price`) as `sum`
				FROM `escrow_offer` 
				LEFT JOIN `escrow_userpaymentmethod` ON `escrow_offer`.`method_id` = `escrow_userpaymentmethod`.`id`
				LEFT JOIN `escrow_payment` ON `escrow_payment`.`id` = `escrow_userpaymentmethod`.`payment_id`
				WHERE `escrow_payment`.`id` = :payment_id
				AND ((`escrow_offer`.`amount` - GREATEST( :amount *" . $comission . "," . $minimum . "))/`escrow_offer`.`price`) > 0
				AND `escrow_userpaymentmethod`.`description` != ''
				AND `escrow_offer`.`currency` = :currency
				AND `escrow_offer`.`user_id` != :user_id
				AND `escrow_offer`.`status` = 'waiting' " .
						(empty($hidden_ids)
								? ''
								: "AND `escrow_offer`.`id` not in (" . join(",", $hidden_ids) . ")")
						. " 
				ORDER BY `sum` DESC
				LIMIT " . ($page * $screen) . "," . $screen * 1 . "
			   ")->query([
			'amount'	 => $input['amount'],
			'currency'	 => $filter,
			'payment_id' => $input['payment_id'],
			'user_id'	 => $input['user_id']
		]);

		$offers = [];
		while (($row = $db->read()) != false) {
			$offers[] = Offer::getBy([
						'id' => $row['offer_id']
			]);
		}

		if (empty($offers)) {
			return false;
		}

		$template = H::getTemplate('pages/balance/offer_line', [], true);

		$h = [];

		foreach ($offers as $offer) {

			$seller = User::getBy([
						'id' => $offer->get('user_id')
			]);

			$methods = $offer->get('methods');

			$ch = explode(':', $offer->get('changed'));
			$changed = join(' ', explode(' ', $ch[0] . ':' . $ch[1]));

			$amount = min($input['amount'] * 1, $offer->d('amount'));

			$mode = $offer->get('UserPaymentMethod')->get('mode');

			$auto_manual_string = T::out([
						$mode . '(in withdraw line)' => [
							'en' => $mode,
							'ru' => $mode
						]
			]);

			//$price = $offer->get('amountWithComission', $amount);
			//die($offer->get('f'));

			$price = $offer->d('roundPrice', $amount);


			$h[] = self::parse($template, [
						'title'				 => (empty($methods)
								? T::out([
									'unavailable_because_no_requisites' => [
										'en' => 'No payment details has been set, don`t show.',
										'ru' => 'Для заявки не заданы платежные реквизиты, не показывается.'
									]
								])
								: ''),
						'date'				 => $offer->getDate(),
						'seller_id'			 => $seller->get('id'),
						'seller'			 => $seller->get('name'),
						'color'				 => (empty($methods)
								? 'gray'
								: 'gold'),
						'mode'				 => $auto_manual_string,
						'price'				 => $offer->get('roundPriceWithCurrency', $amount),
						'amount'			 => $offer->d('amountWithComission', $amount),
						'comission_string'	 => $offer->getComissionString($amount),
						'pay'				 => $offer->get('payHTML', $amount),
						'method'			 => $offer->get('methodHTML'),
						'buyback'			 => $offer->d('buyback')
								? ''
								: 'display:none;',
						'payment_details' => nl2br($offer->get('UserPaymentMethod')->get('description')),		
						'buyback_price'		 => $offer->fget('roundBuyBackPrice', $amount) . ' ' . $offer->get('currency') . '/$',
						'buttons'			 => H::getTemplate(
								'pages/dialogs/button', [
							'title'		 => T::out([
								'accept_offer' => [
									'en' => 'Accept the offer',
									'ru' => 'Принять предложение'
								]
							]),
							'class'		 => 'accept_offer',
							'id'		 => $offer->get('id'),
							'background' => 'mediumseagreen',
							'icon'		 => 'fa-check',
							'color'		 => 'white'
								], true) . H::getTemplate(
								'pages/dialogs/button', [
							'title'		 => T::out([
								'hide_offer' => [
									'en' => 'Hide the offer',
									'ru' => 'Скрыть предложение'
								]
							]),
							'class'		 => 'hide_offer',
							'id'		 => $offer->get('id'),
							'background' => 'tomato',
							'icon'		 => 'fa-gavel',
							'color'		 => 'white'
								], true),
						'offer_id'			 => $offer->get('id')
							], 'addDelimeters');
		}

		if (count($h) > 0) {
			$h[] = H::getTemplate('pages/balance/inline_help_offers', [], true) . H::getTemplate('pages/balance/inline_help_scheme', [], true);
		}

		$ph = [];
		for ($i = 0; $i < $pages; $i++) {
			$ph[] = '<span class="goto_page selling_offers id_' . $i . ' ' . ($i == $page
							? 'current'
							: '') . '">' . ($i + 1) . '</span>';
		}

		$h[] = '<div class="help ac">' . join('', $ph) . '</div>';

		$selling = join('', $h);

		return $selling;
	}

	public function rateSeller($what) {

		if (!in_array($what, [
					'success',
					'failure',
					'balance_success',
					'balance_failure'
				])) {
			return $this;
		}

		User::getBy([
			'id'		 => $this->get(in_array($what, ['success',
						'failure'])
							? 'accepted_by'
							: 'user_id'),
			'_notfound'	 => true
		])->inc([
			$what => $this->d('amount')
		]);

		return $this;
	}

	public function getComissionString($amount = null) {

		if ($this->d('comission') == 0) {
			return '';
		}

		if (empty($amount)) {
			return '(' . $this->d('amount') . '$ - ' . $this->d('comission') . '$ ' . T::out([
						'comission_in_line' => [
							'en' => 'comission',
							'ru' => 'комиссия'
						]
					]) . ')';
		} else {
			return '(' . min($this->d('amount'), $amount) . '$ - ' . $this->d('comission', $amount) . '$ ' . T::out([
						'comission_in_line' => [
							'en' => 'comission',
							'ru' => 'комиссия'
						]
					]) . ')';
		}
	}

	public function getAmountWithComission($amount) {
		
	}

	public static function remainConverter($remain) {
		return join(':', [
			$remain->days * 24 + $remain->h,
			$remain->i,
			$remain->s
		]);
	}

	public static function remainToTimestamp($remain) {
		return $remain->days * 86400 + $remain->h * 3600 + $remain->i * 60 + $remain->s;
	}

	public function getDate() {

		$user = User::logged();

		if (in_array($this->get('status'), [
					'accepted',
					'confirmed',
					'disputed'])) {

			$remain = (new DateTime())->diff(new DateTime($this->get('autopay')));

			$sign = ($remain->format('%R') . '1') * 1;

			if ($sign < 0) {
				return '<span style="color:tomato;">' . T::out([
							'not_available' => [
								'en' => 'Not available',
								'ru' => 'Недоступнo'
							]
						]) . '</span>';
			}

			$t = '<span class="time_remain id_' . $this->get('id') . '" timestamp="' . $this->get('remain') . '">' . $this->get('remain_string') . '</span>';
		}


		if ($this->get('status') == 'disputed') {

			if ($this->get('customer_claim') && $this->get('seller_claim')) {
				return '<span style="color:black;">' . T::out([
							'wait_for_arbitrage_decision' => [
								'en' => 'Awaits decision of arbitration',
								'ru' => 'Ждет решения арбитража'
							]
						]) . '</span>';
			} elseif (!$this->get('seller_claim') && $this->get('accepted_by') == $user->get('id')) {
				return T::out([
							'Wait for seller decision_3' => [
								'en'		 => 'Wait&nbsp;for&nbsp;seller&nbsp;decision within {{t}} or will be paid.',
								'ru'		 => 'Ждем&nbsp;решения&nbsp;продавца в течение {{t}} или будет оплачен',
								'_include'	 => [
									't' => $t
								]
							]
				]);
			} elseif (!$this->get('seller_claim') && $this->get('user_id') == $user->get('id')) {
				return '<span style="color:red;">' . T::out([
							'seller_send_claim_4' => [
								'en'		 => 'The&nbsp;customer&nbsp;has&nbsp;a&nbsp;claim. After {{t}} claim will be satisfied.',
								'ru'		 => 'Покупатель&nbsp;выдвинул&nbsp;претензию. Через {{t}} будет удовлетворена.',
								'_include'	 => [
									't' => $t
								]
							]
						]) . '</span>';
			} elseif (!$this->get('customer_claim') && $this->get('accepted_by') == $user->get('id')) {
				return '<span style="color:red;">' . T::out([
							'customer_send_claim_4' => [
								'en'		 => 'The&nbsp;seller&nbsp;has&nbsp;a&nbsp;claim. After {{t}} claim will be satisfied.',
								'ru'		 => 'Продавец&nbsp;выдвинул&nbsp;претензию. Через {{t}} будет удовлетворена.',
								'_include'	 => [
									't' => $t
								]
							]
						]) . '</span>';
			} elseif (!$this->get('customer_claim') && $this->get('user_id') == $user->get('id')) {
				return T::out([
							'Wait for customer decision_4' => [
								'en'		 => 'Wait&nbsp;for&nbsp;customer&nbsp;decision within {{t}} or will be canceled',
								'ru'		 => 'Ждем&nbsp;решения&nbsp;покупателя в течение {{t}} или будет отменен',
								'_include'	 => [
									't' => $t
								]
							]
				]);
			}
		}

		if (in_array($this->get('status'), [
					'accepted',
					'confirmed'
				])) {

			if ($this->get('status') == 'accepted' && $this->get('reconfirm')) {
				if ($this->get('accepted_by') == $user->get('id')) {
					return T::out([
								'please_confirm_again_5' => [
									'en'		 => 'Please&nbsp;confirm again. Within {{t}} will be canceled',
									'ru'		 => 'Пожалуйста&nbsp;подтвердите еще раз. Через {{t}} или будет оменен',
									'_include'	 => [
										't' => $t
									]
								]
					]);
				} else {
					return T::out([
								'wait for confirmation_from_customer' => [
									'en'		 => 'Awaiting payment re-confirmation. Cancel after {{t}}',
									'ru'		 => 'Ожидает повторного подтверждения платежа. Отмена через {{t}}',
									'_include'	 => [
										't' => $t
									]
								]
					]);
				}
			}

			return $this->get('status') == 'accepted'
					? T::out([
						'cancel_in' => [
							'en'		 => 'Cancel after {{t}}',
							'ru'		 => 'Отмена через {{t}}',
							'_include'	 => [
								't' => $t
							]
						]
					])
					: T::out([
						'autopay_in' => [
							'en'		 => 'Autopay after {{t}}',
							'ru'		 => 'Автоплатеж через {{t}}',
							'_include'	 => [
								't' => $t
							]
						]
			]);
		} else {
			$ch = explode(':', $this->get('changed'));
			return join(' ', explode(' ', $ch[0] . ':' . $ch[1]));
		}
	}

	public function setActions($keys = null, $for = null) {

		if (empty($keys)) { //when this happen
			throw new Exception('setActions stop');

			Action::getBy([
				'key'		 => 'update_withdraw_orders',
				'_notfound'	 => [
					'key'	 => 'update_withdraw_orders',
					'action' => json_encode([
						'Balance' => [
							'updateWithdraw' => [
								'notification' => 1
							]
						]
					])
				]
			])->setFor(empty($for)
							? ($this->get($this->get('iam') == 'customer'
											? 'user_id'
											: 'accepted_by'))
							: $for);
		} else {

			foreach ($keys as $key) {

				if ($key == 'update_withdraw_orders') {

					if (!empty($for) && is_array($for)) {

						foreach ($for as $element) {
							$this->setActions([$key], $element);
						}

						$action = false;
					} else {

						$target = empty($for)
								? $this->get('notme')
								: ($for == $this->get('user_id')
										? 'seller'
										: 'customer');

						$action = Action::getBy([
									'key'		 => 'update_withdraw_orders_' . $target,
									'_notfound'	 => [
										'key'	 => 'update_withdraw_orders_' . $target,
										'action' => json_encode([
											'Balance' => [
												'updateWithdraw' => [
													'notification' => $target
												]
											]
										])
									]
						]);
					}
				} elseif ($key == 'update_arbitrage_list') {
					$action = Action::getBy([
								'key'		 => 'update_arbitrage_list',
								'_notfound'	 => [
									'key'	 => 'update_arbitrage_list',
									'action' => json_encode([
										'Arbitrage' => [
											'updateList' => [
												'notification' => 1
											]
										]
									])
								]
					]);
				} elseif ($key == 'update_balance') {

					$action = Action::getBy([
								'key'		 => 'update_balance',
								'_notfound'	 => [
									'key'	 => 'update_balance',
									'action' => json_encode([
										'User' => [
											'updateBalance' => [
												'data' => 'none'
											]
										]
									])
								]
					]);

					//add deal actions	
				} else {
					$action = false;
				}

				if (!empty($action)) {


					if (is_array($for)) {

						foreach ($for as $val) {

							if (empty($val)) {
								$who = $this->get($this->get('iam') == 'customer'
												? 'user_id'
												: 'accepted_by');
							} elseif ($val == 'seller') {
								$who = $this->get('user_id');
							} elseif ($val == 'customer') {
								$who = $this->get('accepted_by');
							} else {
								$who = $val;
							}

							$action->setFor($who);
						}
					} else {

						if (empty($for)) {
							$who = $this->get($this->get('iam') == 'customer'
											? 'user_id'
											: 'accepted_by');
						} elseif ($for == 'seller') {
							$who = $this->get('user_id');
						} elseif ($for == 'customer') {
							$who = $this->get('accepted_by');
						} else {
							$who = $for;
						}

						//throw new Exception($who);
						$action->setFor($who);
					}
				}
			} //of for
		}

		return $this;
	}

	public static function getDisputedOffers($iam) {

		$user = User::logged();

		$offers = self::getBy([
					'accepted_by'	 => $iam == 'customer'
							? $user->get('id')
							: '!=' . $user->get('id'),
					'user_id'		 => $iam == 'customer'
							? '!=' . $user->get('id')
							: $user->get('id'),
					'status'		 => 'disputed',
					'currency'		 => '!=_deal',
					'autopay'		 => [
						'_between' => [
							(new DateTime())->format('Y-m-d H:i:s'),
							'2100-00-00 00:00',
						]
					],
					'_return'		 => [
						0 => 'object'
					]
		]);

		$template = H::getTemplate('pages/balance/dispute_line', [], true);

		$t2 = H::getTemplate('pages/withdraw/arbitrage_line', [], true);

		$h = [];
		if (!empty($offers)) {
			foreach ($offers as $offer) {

				$seller = User::getBy([
							'id' => $iam == 'customer'
									? $offer->get('user_id')
									: $offer->get('accepted_by')
				]);

				$methods = $offer->get('methods');

				if (($iam == 'seller' && !$offer->get('customer_claim')) || ($iam == 'customer' && !$offer->get('seller_claim'))) {
					$buttons = H::getTemplate('pages/withdraw/buttons_customer_disputed', [], true);
				} else {
					$buttons = H::getTemplate('pages/withdraw/buttons_seller_disputed', [], true);
				}

				$h[] = self::parse($offer->isOnArbitrage()
										? $t2
										: $template, [
							'title'				 => (empty($methods)
									? T::out([
										'unavailable_because_no_requisites' => [
											'en' => 'No payment details has been set, don`t show.',
											'ru' => 'Для заявки не заданы платежные реквизиты, не показывается.'
										]
									])
									: ''),
							'buttons'			 => $buttons,
							'date'				 => $offer->getDate(),
							'seller_id'			 => $seller->get('id'),
							'seller'			 => $seller->get('name'),
							'color'				 => empty($methods)
									? 'gray'
									: 'gold',
							'amount'			 => $iam == 'seller'
									? $offer->get('amount')
									: $offer->get('amountWithComission'),
							'methods'			 => empty($methods)
									? '<span style="color:red; font-size:0.7rem;">' . T::out([
										'no_payment_methods_has_been_set' => [
											'en' => 'No payment details',
											'ru' => 'Не заданы реквизиты'
										]
									]) . '</span>'
									: $offer->get('methods'),
							'comission_string'	 => $iam == 'seller'
									? ''
									: $offer->getComissionString(),
							'offer_id'			 => $offer->get('id')
								], 'addDelimeters');
			}

			$selling = join('', $h);
		} else {
			$selling = false;
		}

		return $selling;
	}

	public function isOnArbitrage() {

		if ($this->get('status') == 'disputed' && $this->get('seller_claim') && $this->get('customer_claim')) {
			return true;
		} else {
			return false;
		}
	}

//вывод подтвержденных заказов для покупателя
	public static function getConfirmedOffersForByer() {
		$user = User::logged();

		$offers = self::getBy([
					'accepted_by'	 => $user->get('id'),
					'user_id'		 => '!=' . $user->get('id'),
					'status'		 => 'confirmed',
					'currency'		 => '!=_deal',
					'autopay'		 => [
						'_between' => [
							(new DateTime())->format('Y-m-d H:i:s'),
							'2100-00-00 00:00',
						]
					],
					'_return'		 => [
						0 => 'object'
					]
		]);

		$template = H::getTemplate('pages/balance/release_line', [], true);


		$h = [];
		if (!empty($offers)) {
			foreach ($offers as $offer) {

				$seller = User::getBy([
							'id' => $offer->get('user_id')
				]);

				$methods = $offer->get('methods');

				$mode = $offer->get('UserPaymentMethod')->get('mode');

				$auto_manual_string = T::out([
							$mode . '(in withdraw line)' => [
								'en' => $mode,
								'ru' => $mode
							]
				]);

				$h[] = self::parse($template, [
							'title'				 => (empty($methods)
									? T::out([
										'unavailable_because_no_requisites' => [
											'en' => 'No payment details has been set, don`t show.',
											'ru' => 'Для заявки не заданы платежные реквизиты, не показывается.'
										]
									])
									: ''),
							'date'				 => $offer->getDate(),
							'seller_id'			 => $seller->get('id'),
							'seller'			 => $seller->get('name'),
							'color'				 => empty($methods)
									? 'gray'
									: 'gold',
							'mode'				 => $auto_manual_string,
							'amount'			 => $offer->get('amountWithComission'),
							'comission_string'	 => $offer->getComissionString(),
							'methods'			 => empty($methods)
									? '<span style="color:red; font-size:0.7rem;">' . T::out([
										'no_payment_methods_has_been_set' => [
											'en' => 'No payment details',
											'ru' => 'Не заданы реквизиты'
										]
									]) . '</span>'
									: $offer->get('methods'),
							'offer_id'			 => $offer->get('id')
								], 'addDelimeters');
			}

			$selling = join('', $h);
		} else {
			$selling = false;
		}

		return $selling;
	}

	/**
	 * функция обновления статусов по таймауту 
	 */
	public static function rapidTimoutChecker() {

		Deal::auto();

		//check confirmed
		$count = self::getBy([
					'status'	 => [
						'confirmed',
						'accepted',
						'disputed'
					],
					'currency'	 => '!=_deal', //TODO: !!! make it for deals
					'autopay'	 => [
						'_between' => [
							'0000-00-00 00:00',
							(new DateTime())->format('Y-m-d H:i:s')
						]
					],
					'_return'	 => 'count'
		]);

		//throw new Exception('stop' . $count);

		if (!empty($count)) {

			$action = Action::getBy([
						'key'		 => 'update_withdraw_orders',
						'_notfound'	 => [
							'key'	 => 'update_withdraw_orders',
							'action' => json_encode([
								'Balance' => [
									'updateWithdraw' => [
										'notification' => 1
									]
								]
							])
						]
			]);

			$offers = self::getBy([
						'status'	 => [
							'confirmed',
							'accepted',
							'disputed'
						],
						'currency'	 => '!=_deal',
						'autopay'	 => [
							'_between' => [
								'0000-00-00 00:00',
								(new DateTime())->format('Y-m-d H:i:s')
							]
						],
						'_limit'	 => 10,
						'_return'	 => [0 => 'object']
			]);

			foreach ($offers as $offer) {
				if ($offer->get('status') == 'accepted') {
					$offer->cancelByBuyer('autocancel');
				} elseif ($offer->get('status') == 'confirmed') {
					$offer->releaseBySeller('autopay');
				} elseif ($offer->get('status') == 'disputed') {

					if ($offer->get('seller_claim') && $offer->get('customer_claim')) {
						
					} elseif ($offer->get('seller_claim')) {
						$offer->cancelByBuyer('autocancel');
					} elseif ($offer->get('customer_claim')) {
						$offer->releaseBySeller('claim_autopay');
					}
				}

				$action->setFor($offer->get('user_id'));
				$action->setFor($offer->get('accepted_by'));
			}
		}
	}

	public static function getAcceptedOffers() {

		$user = User::logged();

		$offers = self::getBy([
					'accepted_by'	 => $user->get('id'),
					'user_id'		 => '!=' . $user->get('id'),
					'status'		 => 'accepted',
					'currency'		 => '!=_deal',
					'autopay'		 => [
						'_between' => [
							(new DateTime())->format('Y-m-d H:i:s'),
							'2100-00-00 00:00',
						]
					],
					'_return'		 => [
						0 => 'object'
					]
		]);

		$template = H::getTemplate('pages/balance/request_line', [], true);

		$h = [];
		if (!empty($offers)) {
			foreach ($offers as $offer) {

				$seller = User::getBy([
							'id' => $offer->get('user_id')
				]);

				$methods = $offer->get('methods');

				$mode = $offer->get('UserPaymentMethod')->get('mode');

				$auto_manual_string = T::out([
							$mode . '(in withdraw line)' => [
								'en' => $mode,
								'ru' => $mode
							]
				]);

				$h[] = self::parse($template, [
							'title'				 => (empty($methods)
									? T::out([
										'unavailable_because_no_requisites' => [
											'en' => 'No payment details has been set, don`t show.',
											'ru' => 'Для заявки не заданы платежные реквизиты, не показывается.'
										]
									])
									: ''),
							'date'				 => $offer->getDate(),
							'seller_id'			 => $seller->get('id'),
							'seller'			 => $seller->get('name'),
							'button_icon'		 => $offer->get('reconfirm')
									? 'fa-repeat'
									: 'fa-credit-card',
							'backgroundColor'	 => $offer->get('reconfirm')
									? 'background:rgba(255,200,200,0.8);'
									: '',
							'color'				 => empty($methods)
									? 'gray'
									: 'gold',
							'amount'			 => $offer->d('amountWithComission'),
							'comission_string'	 => $offer->getComissionString(),
							'mode'				 => $auto_manual_string,
							'if_manual'			 => $mode == 'manual'
									? 'block'
									: 'none',
							'if_automatic'		 => $mode == 'automatic'
									? 'block'
									: 'none',
							'payment_page'		 => $mode == 'automatic'
									? $offer->get('add_parameters_to_payment_url'/* , $offer->d('price') */)
									: '',
							'methods'			 => empty($methods)
									? '<span style="color:red; font-size:0.7rem;">' . T::out([
										'no_payment_methods_has_been_set' => [
											'en' => 'No payment details',
											'ru' => 'Не заданы реквизиты'
										]
									]) . '</span>'
									: $offer->get('methods'),
							'offer_id'			 => $offer->get('id')
								], 'addDelimeters');
			}

			$selling = join('', $h);
		} else {
			$selling = false;
		}

		return $selling;
	}

	public static function getWithdrawAcceptedOffers() {

		$seller = User::logged();

		$offers = Offer::getBy([
					'user_id'	 => $seller->get('id'),
					'status'	 => 'accepted',
					'autopay'	 => [
						'_between' => [
							(new DateTime())->format('Y-m-d H:i:s'),
							'2100-00-00 00:00',
						]
					],
					'currency'	 => '!=_deal',
					'_return'	 => [0 => 'object'],
					'_order'	 => '`changed` DESC'
		]);

		$h = [];
		if (!empty($offers)) {

			$template = H::getTemplate('pages/balance/withdraw_accepted_line', [], true);

			foreach ($offers as $offer) {

				$methods = $offer->get('methods');

				$ch = explode(':', $offer->get('changed'));
				$changed = join('<br/>', explode(' ', $ch[0] . ':' . $ch[1]));

				$auto_manual = $offer->get('UserPaymentMethod')->get('mode');
				$auto_manual_string = T::out([
							$auto_manual . '(in withdraw line)' => [
								'en' => $auto_manual,
								'ru' => $auto_manual
							]
				]);

				$buyer = User::getBy([
							'id' => $offer->get('accepted_by')
				]);

				$h[] = self::parse($template, [
							'title'		 => (empty($methods)
									? T::out([
										'unavailable_because_no_requisites' => [
											'en' => 'No payment details has been set, don`t show.',
											'ru' => 'Для заявки не заданы платежные реквизиты, не показывается.'
										]
									])
									: ''),
							'date'		 => $offer->getDate(),
							'buyer_id'	 => $buyer->get('id'),
							'buyer'		 => $buyer->get('name'),
							'color'		 => empty($methods)
									? 'gray'
									: 'gold',
							'amount'	 => $offer->get('formattedAmount'),
							'background' => $offer->get('reconfirm')
									? 'background:rgba(255,255,255,0.8);'
									: 'background:rgba(200,255,200,0.8);',
							'methods'	 => empty($methods)
									? '<span style="color:red; font-size:0.7rem;">' . T::out([
										'no_payment_methods_has_been_set' => [
											'en' => 'No payment details',
											'ru' => 'Не заданы реквизиты'
										]
									]) . '</span>'
									: $offer->get('methods'),
							'mode'		 => $auto_manual_string,
							'offer_id'	 => $offer->get('id')
								], 'addDelimeters');
			}
			return join('', $h);
		} else {
			return false;
		}
	}

	public static function getWithdrawConfirmedOffers() {

		$seller = User::logged();

		$offers = Offer::getBy([
					'user_id'	 => $seller->get('id'),
					'status'	 => 'confirmed',
					'autopay'	 => [
						'_between' => [
							(new DateTime())->format('Y-m-d H:i:s'),
							'2100-00-00 00:00',
						]
					],
					'currency'	 => '!=_deal',
					'_return'	 => [0 => 'object'],
					'_order'	 => '`changed` DESC'
		]);

		$h = [];
		if (!empty($offers)) {

			$template = H::getTemplate('pages/balance/withdraw_confirmed_line', [], true);

			foreach ($offers as $offer) {

				$methods = $offer->get('methods');

				$auto_manual = $offer->get('UserPaymentMethod')->get('mode');
				$auto_manual_string = T::out([
							$auto_manual . '(in withdraw line)' => [
								'en' => $auto_manual,
								'ru' => $auto_manual
							]
				]);

				$buyer = User::getBy([
							'id' => $offer->get('accepted_by')
				]);

				$h[] = self::parse($template, [
							'title'		 => (empty($methods)
									? T::out([
										'unavailable_because_no_requisites' => [
											'en' => 'No payment details has been set, don`t show.',
											'ru' => 'Для заявки не заданы платежные реквизиты, не показывается.'
										]
									])
									: ''),
							'date'		 => $offer->getDate(),
							'buyer_id'	 => $buyer->get('id'),
							'buyer'		 => $buyer->get('name'),
							'color'		 => empty($methods)
									? 'gray'
									: 'gold',
							'mode'		 => $auto_manual_string,
							'background' => 'rgba(200,255,200,0.8);',
							'amount'	 => $offer->get('formattedAmount'),
							'methods'	 => empty($methods)
									? '<span style="color:red; font-size:0.7rem;">' . T::out([
										'no_payment_methods_has_been_set' => [
											'en' => 'No payment details',
											'ru' => 'Не заданы реквизиты'
										]
									]) . '</span>'
									: $offer->get('methods'),
							'offer_id'	 => $offer->get('id')
								], 'addDelimeters');
			}
			return join('', $h);
		} else {
			return false;
		}
	}

	public static function getWithdrawWaitingOffers() {

		$seller = User::logged();

		$offers = Offer::getBy([
					'user_id'	 => $seller->get('id'),
					'status'	 => 'waiting',
					'currency'	 => '!=_deal',
					'_return'	 => [0 => 'object'],
					'_order'	 => '`changed` DESC'
		]);


		$h = [];
		if (!empty($offers)) {

			$template = H::getTemplate('pages/balance/withdraw_line', [], true);

			foreach ($offers as $offer) {

				$methods = $offer->get('methods');

				$auto_manual = $offer->get('UserPaymentMethod')->get('mode');

				$h[] = self::parse($template, [
							'title'		 => (empty($methods)
									? T::out([
										'unavailable_because_no_requisites' => [
											'en' => 'No payment details has been set, don`t show.',
											'ru' => 'Для заявки не заданы платежные реквизиты, не показывается.'
										]
									])
									: ''),
							'date'		 => $offer->getDate(),
							'seller'	 => $seller->get('name'),
							'color'		 => empty($methods)
									? 'gray'
									: 'gold',
							'mode'		 => T::out([
								$auto_manual . '(in withdraw line)' => [
									'en' => $auto_manual,
									'ru' => $auto_manual
								]
							]),
							'amount'	 => $offer->get('formattedAmount'),
							'methods'	 => empty($methods)
									? '<span style="color:red; font-size:0.7rem;">' . T::out([
										'no_payment_methods_has_been_set' => [
											'en' => 'No payment details',
											'ru' => 'Не заданы реквизиты'
										]
									]) . '</span>'
									: $offer->get('methods'),
							'offer_id'	 => $offer->get('id')
								], 'addDelimeters');
			}
			return join('', $h);
		} else {
			return false;
		}
	}

	public static function f() {
		return [
			'title'		 => 'Offer on Virtual Guarantee Obligation',
			'datatype'	 => [
				'user_id'		 => [
					'User' => [
						'id' => ' ON DELETE CASCADE '
					]
				],
				'accepted_by'	 => [
					'User' => [
						'id' => ' ON DELETE CASCADE '
					]
				],
				'method_id'		 => [
					'UserPaymentMethod' => [
						'id' => ' ON DELETE CASCADE '
					]
				]
			],
			'create'	 => [
				'method_id'			 => "bigint unsigned default null comment 'Link to method'",
				'user_id'			 => "bigint unsigned default null comment 'Link to user'",
				'accepted_by'		 => "bigint unsigned default null comment 'Person who accept the order'",
				'amount'			 => "float unsigned not null comment 'Amount of offer'",
				'price'				 => "float unsigned default 1 comment 'Price of sell'",
				'buyback'			 => "float unsigned default 1 comment 'Price of repurchase'",
				'currency'			 => "tinytext default null comment 'Currency name'",
				'status'			 => "enum('waiting','accepted','disputed','completed','deleted','confirmed','arbitrage','draft') default 'waiting' comment 'Type of offer'",
				'autopay'			 => "datetime default null comment 'Date of autopayment'",
				'reconfirm'			 => "text default null comment 'Seller ask for confirmation'",
				'customer_claim'	 => "text default null comment 'Customer claim'",
				'seller_claim'		 => "text default null comment 'Seller claim'",
				'invitation'		 => "tinytext default null comment 'Invitation code'",
				'customer_declines'	 => "int default 10 comment 'Number of possible expert declines'", //not used
				'seller_declines'	 => "int default 10 comment 'Number of possible expert declines'", //not used
				'customer_hold'		 => "float unsigned default 0 comment 'Hold for arbitration by customer'",
				'seller_hold'		 => "float unsigned default 0 comment 'Hold for arbitration by seller'",
				'storage'			 => "text default null comment 'Expired and status cash when run claim'"
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
