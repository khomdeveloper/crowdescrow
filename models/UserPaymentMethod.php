<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of UserPaymentMethod
 *
 * @author valera261104
 */
class UserPaymentMethod extends M {

	public static function action($r, $dontdie = false) {

		$com = empty($r[get_called_class()])
				? false
				: $r[get_called_class()];


		if ($com === 'get') {
			return [
				'Balance' => [
					'outputWithdrawalMethods' => [
						'html' => self::prepareWithdrawalMethodsHTML()
					]
				]
			];
		} elseif ($com == 'getSecretKey') {
			
		} elseif ($com == 'load') {

			self::required([
				'id' => true
					], $r);

			$upm = self::getBy([
						'id'		 => $r['id'],
						'_notfound'	 => true
			]);

			return [
				'Balance' => [
					'loadUserPaymentMethod' => $upm->toArray(['id' => true])
				]
			];
		} elseif ($com == 'delete') {

			self::required([
				'id' => true
					], $r);

			$method = self::getBy([
						'id'		 => $r['id'],
						'_notfound'	 => true
			]);

			if ($method->getActiveOffers()) {
				throw new Exception(T::out([
					'unable_to_remove' => [
						'en' => 'Unable to remove while you have active offers. Remove or complete them.',
						'ru' => 'Невозможно удалить пока у вас есть активные лоты. Удалите или завершите их.'
					]
				]));
			}

			$method->remove();

			return [
				'B' => [
					'get' => [
						'r'					 => 'user/main',
						'UserPaymentMethod'	 => 'open',
						'payment_id'		 => $method->get('payment_id')
					]
				]
			];
		} elseif ($com == 'set') {

			self::required([
				'withdraw'		 => true,
				'price'			 => true,
				'payment_id'	 => true,
				'currency'		 => true,
				'description'	 => true,
				'mode'			 => true
					], $r);

			$user = User::logged();

			$PaymentMethod = Payment::getBy([ //check for method presense
						'id'		 => $r['payment_id'],
						'_notfound'	 => true
			]);

			//check amount not less than a comission
			$comission = round(max(S::getBy([
								'key'		 => 'minimum',
								'_notfound'	 => [
									'key'	 => 'minimum',
									'val'	 => 1
								]
							])->d('val'), $r['withdraw'] * S::getBy([
								'key'		 => 'comission',
								'_notfound'	 => [
									'key'	 => 'comission',
									'val'	 => 0.5
								]
							])->d('val') / 100), 2);


			if ($r['withdraw'] * 1 - $comission <= 0) {
				throw new Exception(T::out([
					'error_to_less_comission2' => [
						'en'		 => 'The amount is to less. With {{comission}}$ service fee buyer will get nothing.',
						'ru'		 => 'Сумма слишком маленькая, с учетом комиссии сервиса {{comission}}$ покупатель ничего не получит.',
						'_include'	 => [
							'comission' => $comission
						]
					]
				]));
			}

			if ($user->get('money') * 1 === 0) {
				throw new Exception(T::out([
					'error_no_money' => [
						'en' => 'Balance is empty yet!',
						'ru' => 'Баланс пока пуст!'
					]
				]));
			}

			if ($r['withdraw'] * 1 <= 0 || $r['price'] * 1 <= 0) {
				throw new Exception(T::out([
					'amount_expected_not_zero' => [
						'en' => 'Positive value expected',
						'ru' => 'Ожидается положительная величина'
					]
				]));
			}

			if (empty($r['currency'])) {
				throw new Exception(T::out([
					'need_currency_name' => [
						'en' => 'Please enter the currency name in which you would like to get money',
						'ru' => 'Пожалуйста введите название валюты в которой вы хотите получить средства'
					]
				]));
			}

			if (!in_array($r['currency'], $PaymentMethod->get('currencies'))) {
				throw new Exception(T::out([
					'currency_type_error' => [
						'en'		 => '{{types}} - are possible for {{system}}',
						'ru'		 => '{{types}} - допустимы для {{system}}',
						'_include'	 => [
							'types'	 => $PaymentMethod->get('currency'),
							'system' => $PaymentMethod->get('title')
						]
					]
				]));
			}

			$min_wait = S::getBy([
						'key'		 => 'time_should_pay',
						'_notfound'	 => [
							'key'	 => 'time_should_pay',
							'val'	 => 8
						]
					])->d('val');

			if (isset($r['wait'])) {
				$max_wait = S::getBy([
							'key'		 => 'time_should_pay_max',
							'_notfound'	 => [
								'key'	 => 'time_should_pay_max',
								'val'	 => 56
							]
						])->d('val');

				$r['wait'] = min(max($r['wait'] * 1, $min_wait), $max_wait);
			} else {
				$r['wait'] = $min_wait;
			}

			//check if any (not cancelled or completed offer use this method
			//TODO: add wait to methodUsr instead offer

			$methodUser = self::getBy([
						'user_id'		 => $user->get('id'),
						'payment_id'	 => $PaymentMethod->get('id'),
						'description'	 => $r['description'],
						'mode'			 => $r['mode'],
						'_notfound'		 => [
							'user_id'		 => $user->get('id'),
							'payment_id'	 => $PaymentMethod->get('id'),
							'description'	 => $r['description'],
							'mode'			 => $r['mode']
						]
					])->set([
				'mode'		 => $r['mode'],
				'wait'		 => $r['wait'],
				'currency'	 => $r['currency']
			]);

			$new_balance = max(0, $user->get('money') * 1 - $r['withdraw'] * 1);

			$amount = min($r['withdraw'] * 1, $user->get('money') * 1);

			$price = $amount * $r['price'] / $r['withdraw'];

			Offer::create([
				'user_id'	 => $user->get('id'),
				'method_id'	 => $methodUser->get('id'),
				'amount'	 => min($r['withdraw'] * 1, $user->get('money') * 1),
				'price'		 => $price,
				'currency'	 => $r['currency'],
				'status'	 => 'waiting'
			]);

			//new get by because of bug in vh2015
			$user = User::getBy([
						'id' => $user->get('id')
					])->set([
						'money' => $new_balance
					])->cash();

			return [
				'Balance' => [
					'outputOffersList'		 => [
						'waiting'	 => Offer::getWithdrawWaitingOffers(),
						'accepted'	 => Offer::getWithdrawAcceptedOffers(),
						'confirmed'	 => Offer::getWithdrawConfirmedOffers(),
						'balance'	 => $new_balance
					],
					'disablePaymentMethod'	 => [
						'id'	 => $methodUser->get('payment_id'),
						'action' => 'enable'
					]
				]
			];

			/* 				
			  } elseif ($r['mode'] == 'manual' && !empty($r['withdraw']) && empty($r['changed'])) {//
			  throw new Exception(T::out([
			  'error_empty_rquisites' => [
			  'en' => 'Payment details empty!',
			  'ru' => 'Платежные реквизиты пустые!'
			  ]
			  ]));
			  } elseif ($r['mode'] == 'manual') {

			  return [
			  'Balance' => [
			  'disablePaymentMethod' => [
			  'id'	 => $methodUser->get('payment_id'),
			  'action' => 'disable'
			  ]
			  ]
			  ];
			  }

			  return [
			  'Balance' => [
			  'outputOffersList'		 => [
			  'waiting'	 => Offer::getWithdrawWaitingOffers(),
			  'accepted'	 => Offer::getWithdrawAcceptedOffers(),
			  'confirmed'	 => Offer::getWithdrawConfirmedOffers(),
			  'balance'	 => $new_balance
			  ],
			  'disablePaymentMethod'	 => [
			  'id'	 => $methodUser->get('payment_id'),
			  'action' => 'enable'
			  ]
			  ]
			  ]; */
		} elseif ($com == 'open') {

			self::required([
				'payment_id' => true
					], $r);

			$userMethods = self::getBy([
						'user_id'	 => User::logged()->get('id'),
						'payment_id' => $r['payment_id'],
						'_return'	 => ['id' => 'object']
			]);

			$payment = Payment::getBy([
						'id'		 => $r['payment_id'],
						'_notfound'	 => true
			]);

			if (!empty($userMethods)) {

				$userMethod = current($userMethods);
				$userMethodArray = $userMethod->toArray(['id' => true]);
				if (empty($userMethodArray['currency'])) {
					$userMethodArray['currency'] = explode(',', $payment->get('currency'))[0];
				}
			}


			M::ok([
				'Balance' => [
					'editWithdrowalMethod' => [
						'title'			 => H::getTemplate('pages/balance/create_withdraw_amount', [
							'payment_id'	 => $payment->get('id'),
							'paymentmethod'	 => H::getTemplate('pages/balance/payment_method_in_title', [
								'image'	 => $payment->get(['image' => 0]),
								'href'	 => $payment->get('url'),
								'title'	 => $payment->get('title')
									], true)
								], true),
						'userMethodsIDS' => !empty($userMethodArray)
								? array_keys($userMethods)
								: false,
						'userMethod'	 => !empty($userMethodArray)
								? $userMethodArray
								: false,
						'payment'		 => [
							'title'			 => $payment->get('title'),
							'image'			 => $payment->get(['image' => 0]),
							'description'	 => $payment->get('description'),
							'payment_id'	 => $payment->get('id'),
							'secret_key'	 => User::logged()->get('payment_key'),
							'currency'		 => $payment->get('currency')
									? explode(',', $payment->get('currency'))
									: ''
						]
					]
				]
			]);
		}
	}

	public static function prepareWithdrawalMethodsHTML() {

		$dealPayment = Payment::getBy([
					'url'		 => '_deal',
					'_notfound'	 => [
						'url' => '_deal'
					]
				])->get('id');

		$methods = self::getBy([
					'user_id'		 => User::logged()->get('id'),
					'payment_id'	 => '!=' . $dealPayment,
					'description'	 => '>>0',
					'_return'		 => ['payment_id' => 'array']
		]);

		if (!empty($methods)) {
			$other_methods = Payment::getBy([
						'id'		 => [
							'_not_in' => array_keys($methods)
						],
						'url'		 => '!=_deal',
						'currency'	 => 'is not null',
						'_return'	 => ['id' => 'object']
			]);
		} else {
			$other_methods = Payment::getBy([
						'url'		 => '!=_deal',
						'currency'	 => 'is not null',
						'_return'	 => ['id' => 'object']
			]);
		}

		$h = [
			'<div class="wmh_11 ib"><input type="text" value="" class="withdraw_amount" placeholder="' .
			T::out([
				'enter_amount_short' => [
					'en' => 'amount in $',
					'ru' => 'сумма в $'
				]
			]) . '"/><span style="color:gold;" class="amount_in_offer">$</span></div>'
		];

		if (!empty($methods) || !empty($other_methods)) {

			$template = H::getTemplate('pages/balance/payment_button', [
						'host_class' => 'withdraw_a_host'
							], true);

			if (!empty($methods)) {
				foreach ($methods as $payment_id => $method) {

					$payment = Payment::getBy([
								'id' => $payment_id
					]);

					$h[] = H::parse($template, [
								'payment_id' => $payment_id,
								'title'		 => $payment->get('title'),
								'image'		 => $payment->get(['image' => 0]),
								'class'		 => 'withdraw available_method'
									], true);
				}
			}

			if (!empty($other_methods)) {
				foreach ($other_methods as $payment_id => $payment) {
					$h[] = H::parse($template, [
								'payment_id' => $payment_id,
								'title'		 => $payment->get('title'),
								'image'		 => $payment->get(['image' => 0]),
								'class'		 => 'withdraw'
									], true);
				}
			}
		} else {
			$h[] = [
				T::out([
					'no_any_methods' => [
						'en'		 => '{{s}}There are no any payment methods!</span>',
						'ru'		 => '{{s}}Нет доступных способов оплаты!</span>',
						'_include'	 => [
							's' => '<span style="color:red;">'
						]
					]
				])
			];
		}

		return join('', $h);
	}

	public function get($what, $data = false) {

		if ($what == 'method_id') {
			return $this->get('payment_id');
		}

		if ($what == 'wait') {

			if ($this->wait) {
				return $this->wait;
			} else { //return default 8 hours
				return S::getBy([
							'key'		 => 'time_should_pay',
							'_notfound'	 => [
								'key'	 => 'time_should_pay',
								'val'	 => 8
							]
						])->get('val');
			}
		}

		/*
		  if ($what == 'secret_key') {
		  return md5($this->get('id') . 'UserPaymentMethod_129' . $this->get('payment_id'));
		  } */

		return parent::get($what, $data);
	}

	public function getActiveOffers() {

		return Offer::getBy([
					'method_id'	 => $this->get('id'),
					'status'	 => [
						'waiting',
						'accepted',
						'disputed',
						'confirmed'
					],
					'_return'	 => 'count'
		]);
	}

	public static function f() {
		return [
			'title'		 => 'Payment methods available for User',
			'datatype'	 => [
				'user_id'	 => [
					'User' => [
						'id' => ' ON DELETE CASCADE '
					]
				],
				'payment_id' => [
					'Payment' => [
						'id' => ' ON DELETE CASCADE '
					]
				]
			],
			'create'	 => [
				'user_id'		 => "bigint unsigned default null comment 'Link to user'",
				'payment_id'	 => "bigint unsigned default null comment 'Link to method'",
				'description'	 => "text comment 'Payment details'",
				'mode'			 => "enum('automatic','manual','deal', 'draft') default 'manual' comment 'Type of processing'",
				'wait'			 => "int unsigned default 8 comment 'Hours to wait for transaction'",
				'currency'		 => "tinytext comment 'Currency title'"
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
