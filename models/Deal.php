<?php

/*
 * Simple version of deal 
 */

/**
 * Description of Deal
 *
 * @author valera261104
 */
class Deal extends Offer {

	public static function getTable() {
		return 'escrow_offer';
	}

	function tableName() {
		return 'escrow_offer';
	}

	public static function checkInvitation() {

		if (isset($_REQUEST['deal_invite'])) {

			$deal = Deal::getBy([
						'invitation' => $_REQUEST['deal_invite'],
						'status'	 => 'waiting'
			]);

			if (!empty($deal)) {
				$_SESSION['deal_invite'] = $_REQUEST['deal_invite'];
				$_SESSION['userWhoInvite'] = User::getBy([
							'id' => ($deal->get('user_id')
									? $deal->get('user_id')
									: $deal->get('accepted_by'))
						])->get('name');
			}

			header('Location: https://crowdescrow.biz');
			exit;
		}

		$actionAfterLogin = null;
		$actionBeforeLogin = null;

		if (isset($_SESSION['deal_invite'])) {//action after login
			$deal = Deal::getBy([
						'invitation' => $_SESSION['deal_invite'],
						'status'	 => 'waiting',
						'_return'	 => 'count'
			]);

			if (!empty($deal)) {
				$actionAfterLogin = 'A.w(["B"],function(){B.get({Deal:"remote_invite"});});';
			} else {
				unset($_SESSION['deal_invite']);
				unset($_SESSION['userWhoInvite']);
			}

			if (isset($_SESSION['deal_invite']) && !User::isLogged()) {//deal invitation and user is not logged
				$actionBeforeLogin = "D.show({title:'" .
						T::out([
							'notification_under_reference' => [
								'en'		 => '{{user}} {{what}}. Please sign in to get details.',
								'ru'		 => '{{user}} {{what}}. Войдите в систему чтобы узнать подробности.',
								'_include'	 => [
									'user'	 => empty($_SESSION['userWhoInvite'])
											? T::out([
												'your_receive_invitation (get ref)' => [
													'en' => 'You are invited',
													'ru' => 'Вас приглашают'
												]
											])
											: '<b>' . $_SESSION['userWhoInvite'] . '</b> ' . T::out([
												'invites_you (get_ref)' => [
													'en' => 'invites you',
													'ru' => 'приглашает вас'
												]
											]),
									'what'	 => T::out([
										'use_service_for_your_deal (get_ref)' => [
											'en' => 'to use the service for guarantee of your deal',
											'ru' => 'воспользоваться сервисом для гарантий по вашей сделке'
										]
									])
								]
							]
						]) . "',message: false, waitWhile:true, css: D.getCSS({type: 'norm'}) });";
			}
		}

		return [
			'afterLogin'	 => $actionAfterLogin,
			'beforeLogin'	 => $actionBeforeLogin
		];
	}

	public static function action($r, $dontdie = false) {
		$com = empty($r[get_called_class()])
				? false
				: $r[get_called_class()];

		self::auto();

		$user = User::logged();

		if ($com == 'create') {

			self::required([
				'role' => true
					], $r);

			if (!in_array($r['role'], ['seller',
						'customer'])) {
				throw new Exception('Uncknown role');
			}

			$payment_id = Payment::getBy([
						'url'		 => '_deal',
						'_notfound'	 => [
							'url' => '_deal'
						]
					])->get('id');

			$upm = UserPaymentMethod::getBy([
						'user_id'	 => $user->get('id'),
						'payment_id' => $payment_id,
						'mode'		 => 'draft',
						'_notfound'	 => [
							'user_id'	 => $user->get('id'),
							'payment_id' => $payment_id,
							'mode'		 => 'draft',
							'wait'		 => S::getBy([
								'key'		 => 'default_term_for_deals',
								'_notfound'	 => [
									'key'	 => 'default_term_for_deals',
									'val'	 => 72
								]
							])->d('val')
						]
			]);

			$arr = $r['role'] == 'customer'
					? [
				'method_id'	 => $upm->get('id'),
				'user_id'	 => $user->get('id'),
				'status'	 => 'draft',
				'currency'	 => '_deal'
					]
					: [
				'method_id'		 => $upm->get('id'),
				'accepted_by'	 => $user->get('id'),
				'status'		 => 'draft',
				'currency'		 => '_deal'
			];

			return self::getBy(array_merge($arr, [
						'_notfound' => $arr
					]))->set([
						'amount' => 0, //reserved amount
						'price'	 => 0 //agreement price
					])->addEvent('Create new deal', $user)->getDealForm();
		} elseif ($com == 'loadCorrectionForm') { //shown for customer
			self::required([
				'id' => true
					], $r);

			return self::getBy([
						'id'		 => $r['id'],
						'user_id'	 => $user->get('id'),
						'_notfound'	 => true
					])->getDealForm();
		} elseif ($com == 'remote_invite') {

			if (isset($_SESSION['deal_invite'])) {

				$deal = Deal::getBy([
							'invitation' => $_SESSION['deal_invite'],
							'status'	 => 'waiting'
				]);

				//remove session because it is already used
				unset($_SESSION['deal_invite']);
				unset($_SESSION['userWhoInvite']);

				if (empty($deal)) {
					return [
						'warning' => 'Invitation expired'
					];
				}

				if ($deal->get('user_id') && $deal->get('accepted_by')) {
					return [
						'warning' => 'Some partner has already invited'
					];
				} elseif ($deal->get('user_id') == $user->get('id') || $deal->get('accepted_by') == $user->get('id')) {
					throw new Exception(T::out([
						'unable_to_invite_itself' => [
							'en' => 'Cannot invite itself',
							'ru' => 'Невозможно пригласить самого себя'
						]
					]));
				}

				$for = $deal->get('user_id')
						? $deal->get('user_id')
						: $deal->get('accepted_by');

				$deal->set([
					($deal->get('user_id')
							? 'accepted_by'
							: 'user_id')	 => $user->get('id'),
					'invitation'														 => null
				])->setActions([
					'update_deals_list',
					'update_invitation_link'
						], [
					$for
				])->addEvent('Remote invitation', $user);

				$r['role'] = $deal->get('role');

				return [
					'Site' => [
						'switchTo' => [
							'page' => $deal->get('role')
						]
					]
				];
			} else {
				return [
					'warning' => 'Empty invite session'
				];
			}
		} elseif ($com == 'invite') { //email + 
			self::required([
				'user_id'	 => true,
				'offer_id'	 => true
					], $r);

			//check user presense
			User::getBy([
				'id'		 => $r['user_id'],
				'_notfound'	 => true
			]);

			$deal = Deal::getBy([
						'id' => $r['offer_id']
			]);

			if (!$deal->get('isCreator')) {
				throw new Exception('Only creator can change the partner');
			}

			//check role
			if (($r['role'] == 'customer' && $deal->get('accepted_by')) ||
					($r['role'] == 'seller' && $deal->get('user_id'))) {
				throw new Exception(T::out([
					'user_already_invited' => [
						'en' => 'Counterparty has already invited',
						'ru' => 'Контрагент уже приглашен'
					]
				]));
			}

			if ($r['user_id'] == $user->get('id')) {
				throw new Exception('Unable to invite myself');
			}

			$deal = $deal->set($r['role'] == 'customer'
									? [
								'accepted_by' => $r['user_id']
									]
									: [
								'user_id' => $r['user_id']
							])->setActions([
						'update_deals_list'
					])->sendEmailNotification('invite')->addEvent('Invite counterparty', $user);

			if (empty($r['role'])) {
				$r['role'] = $deal->get('role');
			}
		} elseif ($com == 'cancel') {

			self::required([
				'id' => true
					], $r);

			$deal = self::getBy([
						'id'		 => $r['id'],
						'status'	 => [
							'draft',
							'waiting',
							'accepted',
							'completed'
						],
						'_notfound'	 => true
			]);
			$role = $deal->get('role');
			$deal->cancel($user);

			return [
				'Deal'	 => [
					'list' => [
						$role => self::getList($role)
					]
				],
				'User'	 => [
					'updateBalance' => [
						'data' => 'none'
					]
				]
			];


			//UPM может рассматриваться как соглашение для нескольких milestone
		} elseif ($com == 'set') {

			self::required([
				'id'	 => true,
				'amount' => true,
				'term'	 => true
					], $r);

			if ($r['term'] * 1 <= 0 || $r['amount'] * 1 <= 0) {
				throw new Exception(T::out([
					'more_than_zero_expected2 (error)' => [
						'en' => 'Value should be more than zero',
						'ru' => 'Значение должно быть больше нуля'
					]
				]));
			}

			$deal = self::getBy([
						'id'		 => $r['id'],
						'_notfound'	 => true
					])->fund($r['amount'] * 1, $user);

			$upm = UserPaymentMethod::getBy([
						'id'		 => $deal->set([
							'status' => $deal->get('status') == 'draft'
									? 'waiting'
									: $deal->get('status'),
							'price'	 => $r['amount'] * 1 //set total agreement price
						])->get('method_id'),
						'_notfound'	 => true
					])->set([
				'mode'	 => 'deal',
				'wait'	 => $r['term'] * 24
			]);

			if (isset($r['description'])) {
				$upm->set([
					'description' => $r['description']
				]);
			}

			if ($upm->get('user_id') != $user->get('id')) {
				throw new Exception('Only creator can edit the deal');
			}

			if ($deal->get('status') == 'waiting' && $deal->get('user_id') && $deal->get('accepted_by')) {
				throw new Exception('Unable to change deal while it is offered to counterparty');
			}

			$deal->addEvent('Data set', $user, json_encode($r));

			return [
				'Deal'	 => array_merge([
					'list' => [
						$deal->get('role') => self::getList($deal->get('role'))
					]
						], $deal->get($deal->get('find_counterparty'))
								? []
								: [
							'addContractor' => [
								'id' => $deal->get('id')
							]
						]),
				'User'	 => [
					'updateBalance' => [
						'data' => 'none'
					]
				]
			];
		} elseif ($com == 'accept') {

			self::required([
				'id' => true
					], $r);

			$deal = self::getBy([
						'id'			 => $r['id'],
						'status'		 => 'waiting',
						'user_id'		 => '>>0',
						'accepted_by'	 => '>>0',
						'_notfound'		 => true,
					])->fund(false, $user)->addEvent('Offer accepted', $user);

			$r['role'] = $deal->set([
						'status'	 => 'accepted',
						'autopay'	 => (new DateTime())->modify('+' . round($deal->get('UserPaymentMethod')->d('wait')) . ' hour')->format('Y-m-d H:00:00')
					])->setActions(['update_deals_list'])->sendEmailNotification('accept')->get('role');
		} elseif ($com == 'admit') {//признать жалобу	
			self::required([
				'id' => true
					], $r);

			$deal = self::getBy([
						'id'		 => $r['id'],
						'status'	 => [
							'disputed',
							'arbitrage'
						],
						'autopay'	 => [
							'_between' => [
								(new DateTime())->format('Y-m-d H:i:s'),
								'2100-00-00 00:00',
							]
						],
						'_notfound'	 => true
					])->addEvent('Claim admitted', $user);

			if ($deal->get('role') == 'customer') {
				$deal->rateSeller('success')->release('admit');
			} else {
				$deal->cancel($user, 'admit'); //Seller не получает никакого рейтинга
			}

			return [
				'Deal'		 => [
					'list' => [
						$deal->get('role') => self::getList($deal->get('role'))
					]
				],
				'Arbitrage'	 => [
					'updateList' => [
						'notification' => 0
					]
				],
				'User'		 => [
					'updateBalance' => [
						'data' => 'none'
					]
				]
			];
		} elseif ($com == 'update_invitation_link') {

			self::required([
				'offer_id' => true
					], $r);

			return [
				'Deal' => [
					'updateInvitationLink' => [
						'href' => 'https://crowdescrow.biz/?deal_invite=' . Deal::getBy([
							'id'		 => $r['offer_id'],
							'_notfound'	 => true
						])->get('invitation')
					]
				]
			];
		} elseif ($com == 'release') {

			self::required([
				'id' => true
					], $r);

			$r['role'] = self::getBy([
						'id'			 => $r['id'],
						'status'		 => [
							'accepted',
							'confirmed',
							'completed'
						],
						'user_id'		 => $user->get('id'),
						'accepted_by'	 => '>>0',
						'_notfound'		 => true
					])->rateSeller('success')->release('release')->get('role');
		} elseif ($com == 'complete') {

			self::required([
				'id' => true
					], $r);

			$deal = self::getBy([
						'id'			 => $r['id'],
						'status'		 => 'accepted',
						'user_id'		 => '>>0',
						'accepted_by'	 => $user->get('id'),
						'_notfound'		 => true
			]);

			$r['role'] = $deal->set([
						'status' => 'completed'
					])->setActions(['update_deals_list'])->sendEmailNotification('complete')->addEvent('Work reported as completed', $user)->get('role');
		} elseif ($com == 'restart') {

			self::required([
				'id' => true
					], $r);

			$deal = self::getBy([
						'id'			 => $r['id'],
						'status'		 => ['confirmed',
							'completed'],
						'user_id'		 => '>>0',
						'accepted_by'	 => $user->get('id'),
						'_notfound'		 => true
			]);

			$r['role'] = $deal->set([
						'status' => 'accepted'
					])->setActions([
						'update_deals_list'
					])->sendEmailNotification('restart')->addEvent('Work has restarted by seller', $user)->get('role');
		} elseif ($com == 'correct') {

			self::required([
				'id' => true
					], $r);

			$deal = self::getBy([
						'id'		 => $r['id'],
						'user_id'	 => $user->get('id'),
						'_notfound'	 => true
			]);

			if (!empty($r['message'])) {
				$deal->chat($r['message']);
			}

			$r['role'] = $deal->addEvent('Customer ask for correction', $user, json_encode([
						'date' => $deal->get('autopay')
					]))->set([
						'status'	 => 'accepted',
						'autopay'	 => (new DateTime())->modify('+' . round($deal->get('UserPaymentMethod')->d('wait') * S::getBy([
											'key'		 => 'time_to_correct_(part)',
											'_notfound'	 => [
												'key'	 => 'time_to_correct_(part)',
												'val'	 => 1.1
											]
										])->get('val')) . ' hour')->format('Y-m-d H:00:00')
					])->setActions([
						'update_deals_list'
					])->sendEmailNotification('correct')->get('role');
		} elseif ($com == 'load') {

			self::required([
				'id' => true
					], $r);

			return self::getBy([
						'id'		 => $r['id'],
						'_notfound'	 => true
					])->getDealForm();
		} elseif ($com == 'search') {

			self::required([
				'what'	 => true,
				'page'	 => true
					], $r);

			return User::findUsers($r['what'], $r['page'], [], [], 'deal');
		} elseif ($com == 'cancel_claim') {

			self::required([
				'id' => true
			]);

			$deal = Deal::getBy([
						'id'		 => $r['id'],
						'status'	 => 'disputed',
						'autopay'	 => [
							'_between' => [
								(new DateTime())->format('Y-m-d H:i:s'),
								'2100-00-00 00:00',
							]
						],
						'_notfound'	 => true
			]);

			//restore statuses
			$stored = $deal->get('storage')
					? is_array($deal->get('storage'))
							? $deal->get('storage')
							: json_decode($deal->get('storage'), true)
					: [
				'date'	 => $deal->get('autopay'),
				'status' => 'completed'
			];

			$r['role'] = $deal->addEvent('Claim canceled by claimer', $user)->set([
						'status'		 => $stored['status'],
						'autopay'		 => $stored['date'],
						'seller_claim'	 => '',
						'customer_claim' => ''
					])->returnArbitrageDeposit($deal->get('role') == 'customer'
									? $deal->get('accepted_by')
									: $deal->get('user_id'))->setActions([
						'update_deals_list'
					])->sendEmailNotification('cancel_claim')->get('role');
		} elseif ($com == 'pause') { //email +
			self::required([
				'id' => true
					], $r);

			$deal = Deal::getBy([
						'id'		 => $r['id'],
						'status'	 => [
							'draft',
							'waiting'
						],
						'_notfound'	 => true
			]);

			if (!$deal->get('isCreator')) {
				throw new Exception('Only deal creator can do this');
			}

			$r['role'] = $deal->setActions([
						'update_deals_list'
					])->sendEmailNotification('pause')->set($deal->get('role') == 'customer'
									? [
								'accepted_by' => null
									]
									: [
								'user_id' => null
							])->addEvent('Contragent removed, deal paused', $user)->get('role');
		} elseif ($com == 'upload') {

			Deal::getBy([
				'id'		 => $r['id'],
				'_notfound'	 => true
			])->addEvent('Image uploaded', $user);

			self::upload($r, 'multi');
		} elseif ($com == 'delete_uploaded') {

			self::required([
				'offer_id'	 => true,
				'image_id'	 => true
					], $r);

			return self::getBy([
						'id'		 => $r['offer_id'],
						'_notfound'	 => true
					])->addEvent('Image deleted', $user)->deleteUploaded($r)->getDealForm();
		} elseif ($com == 'list') {

			self::required([
				'role' => true
					], $r);

			$action = Action::getBy([
						'key' => 'update_deals_' . $r['role']
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
		}

		if (in_array($com, [
					'create',
					'list',
					'set',
					'invite',
					'pause',
					'remote_invite',
					'accept',
					'complete',
					'release',
					'restart',
					'correct',
					'cancel_claim',
					'admit'
				])) {

			if (isset($r['role']) && in_array($r['role'], [
						'seller',
						'customer'
					])) {
				return [
					'Deal'	 => [
						'list' => [
							$r['role'] => self::getList($r['role'])
						]
					],
					'User'	 => [
						'updateBalance' => [
							'data' => 'none'
						]
					]
				];
			} else {


				throw new Exception('need to set role');

				return [
					'Deal'	 => [
						'list' => [
							'seller'	 => self::getList('seller'),
							'customer'	 => self::getList('customer')
						]
					],
					'User'	 => [
						'updateBalance' => [
							'data' => 'none'
						]
					]
				];
			}
		}

		return parent::action($r, $dontdie);
	}

//of action seller run this option
//pay to reversed customer accepted_by
	public function release($notification = false) {

		return $this->removeAllExperts()
						->returnArbitrageDeposit($this->get('accepted_by'))
						->setActions([
							'update_deals_list',
							'update_balance'
						])->sendEmailNotification($notification)
						->returnFunding($this->get('accepted_by'))
						->addEvent('Deposit released')
						->set([
							'status' => 'deleted'
		]);
	}

	/**
	 * 
	 * @param type $user - initiator
	 */
	public function cancel($user = null, $automatic = null) {

		if (empty($user)) {
			$user = User::getBy([
						'id' => User::logged()->get('id')
			]);
		}

		//если у сделки есть контрагент - необходимо обновить статус
		if ($this->get('user_id') && $this->get('accepted_by')) {
			$this->setActions([
				!empty($automatic) && $automatic === 'admit' && $this->get('status') === 'arbitrage'
						? 'update_arbitrage_list'
						: 'update_deals_list',
				'update_balance'
			]);
			$hasCounterParty = true;
		} else {
			$hasCounterParty = false;
		}

		if ($this->get('status') == 'waiting') {//режим ожидания выставленной сделки
			if ($this->get('isCreator', $user)) { //owner delete permanently
				//$upm = $this->get('UserPaymentMethod');
				$this->returnFunding($this->get('user_id'))
						->addEvent('Removed by creator')
						->sendEmailNotification(empty($hasCounterParty)
										? false
										: (!isset($automatic)
												? 'cancel'
												: $automatic))
						->remove()->get('UserPaymentMethod')->remove();
				//$upm->remove();
				return $this;
			} elseif (!empty($hasCounterParty)) { //not the owner can reject
				return $this->sendEmailNotification(empty($hasCounterParty)
												? false
												: (!isset($automatic)
														? 'reject'
														: $automatic))
								->addEvent(ucfirst($this->get('role')) . ' has rejected the offer')
								->set([
									($this->get('role') == 'seller'
											? 'accepted_by'
											: 'user_id') => null
				]);
				//сделка не удаляется, возврата средств не происходит, убирается контрагент
			}
		} elseif (in_array($this->get('status'), [
					'accepted',
					'completed',
					'disputed',
					'arbitrage'
				]) && $this->get('role') == 'seller') {//отклонена сделка исполнителем с возвратом суммы заказчику
			return $this->removeAllExperts()
							->returnArbitrageDeposit($this->get('user_id'))
							->returnFunding($this->get('user_id'))
							->addEvent('Deal cancelled by Seller with refunding the warranty deposit')
							->sendEmailNotification(empty($hasCounterParty)
											? false
											: (!isset($automatic)
													? 'cancel'
													: $automatic))
							->set([
								'status' => 'delete'
			]);
		} else {
			throw new Exception('Unable to cancel because of deal status');
		}

		return;

		$previous = clone($this);

		$role = $this->get('role');

		$upm = $this->get('UserPaymentMethod');



		if ($upm->get('user_id') == $user->get('id')) { //owner
			if ($this->get('status') == 'waiting' //waiting любая сторона без проблем
					|| (in_array($this->get('status'), ['accepted',
						'completed']) && $role == 'seller') //accepted продавец может отменить
			) {
				if (!empty($hasCounterparty)) {
					$this->sendEmailNotification(!isset($automatic)
									? 'cancel'
									: $automatic);
				}
				$upm->remove();
				$this->addEvent('Removed by creator')->remove();
			} else {
				throw new Exception('Unable to cancel because of deal status');
			}
		} else { //not the owner
			if ($role == 'seller' && in_array($this->get('status'), [
						'waiting',
						'accepted',
						'completed'
					])) {
				
			} else { //i am customer
				if ($this->get('status') === 'waiting') {
					$this->sendEmailNotification(!isset($automatic)
									? 'reject'
									: $automatic)->set([
						'user_id' => null
					])->addEvent('Customer has rejected the offer');
				} else {
					throw new Exception('Unable to cancel because of deal status');
				}
			}
		}

		//возврат средств с арбитражных депозитов и основных средств
		return $previous->removeAllExperts()->returnArbitrageDeposit($this->get('user_id'))->returnFunding($this->get('user_id'));
	}

	//возврат зарезервированных средств покупателю
	public function returnFunding($user_id = null) {

		if ($this->d('amount') > 0) {
			User::getBy([
				'id'		 => empty($user_id)
						? $this->get('user_id')
						: $user_id,
				'_notfound'	 => true
			])->inc([
				'money' => $this->d('amount')
			]);
		}

		return $this->set([
					'amount' => 0
		]);
	}

	/**
	 * Fun deal by customer 
	 */
	public function fund($amount, $user = false) {

		if (empty($user)) {
			$user = User::logged();
		}

		if (empty($amount)) {
			$amount = $this->d('price');
		}

		if ($this->get('role', $user) == 'customer') {

			$need = max(0, $amount * 1) - $this->d('amount');

			if ($need < 0) { //need to return some funding
				$user = User::getBy([
							'id' => $user->get('id')
						])->inc([
					'money' => -$need
				]);
			} else {

				if ($need > $user->d('money')) {// не хватает средств
					M::ok([
						'D'		 => [
							'show' => [
								'title'		 => T::out([
									'Not enough money_need_2' => [
										'en'		 => 'Not enough money! Please fill up the balance. Need {{need}}$',
										'ru'		 => 'Недостаточно средств! Пожалуйста пополните баланс. Нужно {{need}}$',
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
					]);
				}

				$user = User::getBy([
							'id' => $user->get('id')
						])->dec([
					'money' => $need
				]);
			}

			return $this->set([
						'amount' => $amount * 1
			]);
		} else {
			return $this;
		}
	}

	public function setActions($keys = null, $for = null) {

		foreach ($keys as $key) {

			if (in_array($key, [
						'update_deals_list',
						'update_invitation_link'
					]) && !empty($for) && is_array($for)) {


				foreach ($for as $to) {
					$this->setActions([$key], $to);
				}
			} else {

				if ($key === 'update_deals_list') {

					$forKey = empty($for)
							? ($this->get('role') == 'customer'
									? 'seller'
									: 'customer')
							: ($for == $this->get('user_id')
									? 'customer'
									: 'seller');

					if (empty($for)) {
						$for = $this->get('role') == 'customer'
								? $this->get('accepted_by')
								: $this->get('user_id');
					}

					$action = Action::getBy([
								'key'		 => 'update_deals_' . $forKey,
								'_notfound'	 => [
									'key'	 => 'update_deals_' . $forKey,
									'action' => json_encode([
										'Deal' => [
											'update' => [
												'role' => $forKey
											]
										]
									])
								]
							])->setFor($for);
				} elseif ($key === 'update_invitation_link') {

					Action::getBy([
						'key'		 => 'update_invitation_link',
						'_notfound'	 => [
							'key'	 => 'update_invitation_link',
							'action' => json_encode([
								'Deal' => [
									'closeInvitationDialog' => [
										'date' => 'nothing'
									]
								]
							])
						]
					])->setFor($for);
				} else {
					parent::setActions([$key], $for);
				}
			}
		} //of for key

		return $this;
	}

	public function getShortDescription() {

		return H::getTemplate('pages/deal/short_description', [
					'description'	 => $this->get('description'),
					'amount'		 => $this->get('price'),
					'term'			 => $this->get('term')
						], true);
	}

	public function getVeryShortDescription() {


		return T::out([
					'getVeryShortDescritpion' => [
						'en'		 => 'Deal "{{title}}" on amount {{amount}}$, term {{term}} days',
						'ru'		 => 'Сделка «{{title}}» на сумму {{amount}}$, срок выполнения {{term}} дней',
						'_include'	 => [
							'title'	 => $this->get('title'),
							'amount' => $this->get('price'),
							'term'	 => $this->get('term')
						]
					]
		]);
	}

	/**
	 * 
	 * @param type $type
	 * @param type $to = [from_id => to_id]
	 * @return \Deal
	 */
	public function sendEmailNotification($type = false, $to = false) {

		if (empty($type)) {
			return $this;
		} elseif ($type == 'invite') {
			$subject = T::out([
						'invitation_to_deal_header to_You' => [
							'en'		 => '{{partner}} offer to You a deal in the CrowdEscrow.biz service',
							'ru'		 => '{{partner}} предлагает Вам сделку в системе CrowdEscrow.biz',
							'_include'	 => [
								'partner' => User::getBy([
									'id'		 => $this->get('UserPaymentMethod')->get('user_id'),
									'_notfound'	 => true
								])->get('name')
							]
						]
			]);
			$message = $this->getShortDescription();
			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type == 'cancel') {

			$subject = T::out([
						'cancel invitation (header)' => [
							'en'		 => '{{partner}} has removed the deal',
							'ru'		 => '{{partner}} отменил сделку',
							'_include'	 => [
								'partner' => User::getBy([
									'id'		 => $this->get('UserPaymentMethod')->get('user_id'),
									'_notfound'	 => true
								])->get('name')
							]
						]
			]);

			$message = '<div style="margin-top:10px;">' . T::out([
						'cancel the deal (message)' => [
							'en'		 => '{{deal}} cancelled.',
							'ru'		 => '{{deal}} отменена.',
							'_include'	 => [
								'deal' => '<b>' . $this->getVeryShortDescription() . '</b>'
							]
						]
					]) . '</div>';
			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type == 'reject') {

			$subject = T::out([
						'reject the deal (header)' => [
							'en'		 => '{{partner}} reject your offer',
							'ru'		 => '{{partner}} отклонил предложение',
							'_include'	 => [
								'partner' => User::getBy([
									'id'		 => $this->get($this->get('find_counterparty')),
									'_notfound'	 => true
								])->get('name')
							]
						]
			]);

			$message = '<div style="margin-top:10px;">' . T::out([
						'reject the deal (message)' => [
							'en'		 => '{{deal}} rejected by counterparty.',
							'ru'		 => '{{deal}} отклонена контрагентом.',
							'_include'	 => [
								'deal' => '<b>' . $this->getVeryShortDescription() . '</b>'
							]
						]
					]) . '</div>';
			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type == 'pause') {
			$subject = T::out([
						'withdraw invitation (header)' => [
							'en'		 => '{{partner}} withdraw invitation',
							'ru'		 => '{{partner}} отозвал приглашение',
							'_include'	 => [
								'partner' => User::getBy([
									'id'		 => $this->get('UserPaymentMethod')->get('user_id'),
									'_notfound'	 => true
								])->get('name')
							]
						]
			]);
			$message = '<div style="margin-top:10px;">' . T::out([
						'withdraw invitation2 (message)' => [
							'en'		 => '{{deal}} temporary withdrawn.',
							'ru'		 => '{{deal}} временно отозвана.',
							'_include'	 => [
								'deal' => '<b>' . $this->getVeryShortDescription() . '</b>'
							]
						]
					]) . '</div>';
			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type === 'accept') {

			$subject = T::out([
						'accept_deal (header)' => [
							'en'		 => '{{partner}} has accepted deal results and release deposit',
							'ru'		 => '{{partner}} принял результаты сделки и выплатил депозит',
							'_include'	 => [
								'partner' => User::logged()->get('name')
							]
						]
			]);

			$message = $this->getShortDescription();

			//calculate date when it shall be completed

			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type == 'cancel_claim') { //restore to previous state
			$subject = T::out([
						'claim_canceled (header)' => [
							'en'		 => 'Claim was caneled by {{partner}}. Deal was returned to previous state.',
							'ru'		 => '{{partner}} отменил претензию. Сделка вернулась к прежнему состоянию.',
							'_include'	 => [
								'partner' => User::logged()->get('name')
							]
						]
			]);

			$message = $this->getShortDescription();

			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type == 'admit') { //admit claim	
			$subject = T::out([
						'claim_admitted (header)' => [
							'en'		 => 'Claim was admitted by {{partner}}.',
							'ru'		 => '{{partner}} признал претензию.',
							'_include'	 => [
								'partner' => User::logged()->get('name')
							]
						]
			]);

			$message = T::out([
						'deal_released_canceled_in_your favour' => [
							'en'		 => 'Deal {{deal}} was completed in your favour',
							'ru'		 => 'Сделка {{deal}} завершена в Вашу пользу',
							'_include'	 => [
								'deal' => '<b>' . $this->getVeryShortDescription() . '</b>'
							]
						]
			]);

			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type == 'voted_customer') {

			$subject = T::out([
						'voted_customer (header)' => [
							'en' => 'Expertise completed in customer favour',
							'ru' => 'Арбитраж вынес решение в пользу покупателя'
						]
			]);

			$message = T::out([
						'voted_customer (email body)' => [
							'en'		 => 'Deal {{deal}} was completed in favour of customer.',
							'ru'		 => 'Сделка {{deal}} завершена в пользу покупателя.',
							'_include'	 => [
								'deal' => '<b>' . $this->getVeryShortDescription() . '</b>'
							]
						]
			]);

			$link = 'https://crowdescrow.biz/';
		} elseif ($type == 'voted_seller') {

			$subject = T::out([
						'voted_customer (header)' => [
							'en' => 'Expertise completed in seller favour',
							'ru' => 'Арбитраж вынес решение в пользу продавца'
						]
			]);

			$message = T::out([
						'voted_seller (email body)' => [
							'en'		 => 'Deal {{deal}} was completed in favour of seller.',
							'ru'		 => 'Сделка {{deal}} завершена в пользу продавца.',
							'_include'	 => [
								'deal' => '<b>' . $this->getVeryShortDescription() . '</b>'
							]
						]
			]);

			$link = 'https://crowdescrow.biz/';
		} elseif ($type == 'expired') {

			$subject = T::out([
						'claim_expired (header)' => [
							'en' => 'The deadline for filing a counter-claim has expired, the claim is satisfied',
							'ru' => 'Срок подачи встречной жалобы истек, претензия удовлетворена'
						]
			]);

			$message = T::out([
						'claim_expired (email body)' => [
							'en'		 => 'Deal {{deal}} was completed in favour of participant who sent a claim.',
							'ru'		 => 'Сделка {{deal}} завершена в пользу участника подавшего иск.',
							'_include'	 => [
								'deal' => '<b>' . $this->getVeryShortDescription() . '</b>'
							]
						]
			]);

			$link = 'https://crowdescrow.biz/';
		} elseif ($type == 'complete') {

			$subject = T::out([
						'deal_completed (header)' => [
							'en'		 => 'Deal completed by {{partner}}',
							'ru'		 => 'Сделка выполнена {{partner}}',
							'_include'	 => [
								'partner' => User::logged()->get('name')
							]
						]
			]);

			$message = $this->getShortDescription();

			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type == 'release') {

			$subject = T::out([
						'release_deal (header)' => [
							'en'		 => '{{partner}} reporting completion of the deal',
							'ru'		 => '{{partner}} сообщает об исполнении сделки',
							'_include'	 => [
								'partner' => User::logged()->get('name')
							]
						]
			]);

			$message = $this->getShortDescription();

			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type == 'correct') {

			$subject = T::out([
						'correct_deal (header)' => [
							'en'		 => '{{partner}} ask to correct something',
							'ru'		 => '{{partner}} просит кое-что переделать',
							'_include'	 => [
								'partner' => User::logged()->get('name')
							]
						]
			]);

			$message = H::getTemplate('pages/deal/correction_email', [
						'deal'			 => $this->getShortDescription(),
						'corrections'	 => $this->chat(0)
							], true);

			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		} elseif ($type == 'restart') {
			$subject = T::out([
						'restart_deal (header)' => [
							'en'		 => '{{partner}} has not completed yet',
							'ru'		 => '{{partner}} еще не закончил',
							'_include'	 => [
								'partner' => User::logged()->get('name')
							]
						]
			]);

			$message = $this->getShortDescription();

			$link = 'https://crowdescrow.biz/' . ($this->get('role') == 'customer'
							? 'seller'
							: 'customer');
		}

		if (in_array($type, [
					'invite',
					'pause',
					'cancel',
					'reject',
					'accept',
					'complete',
					'release',
					'restart',
					'correct',
					'expired',
					'voted_customer',
					'voted_seller'
				])) {

			if (empty($to)) {

				$email = User::getBy([
							'id'		 => $this->get($this->get('find_counterparty')),
							'_notfound'	 => true
						])->get('confirmed_email');

				$reply = User::getBy([
							'id'		 => $this->get($this->get('role') == 'customer'
											? 'user_id'
											: 'accepted_by'),
							'_notfound'	 => true
						])->get('confirmed_email');
			} else {

				$email = User::getBy([
							'id'		 => current($to),
							'_notfound'	 => true
						])->get('confirmed_email');

				$reply = User::getBy([
							'id'		 => key($to),
							'_notfound'	 => true
						])->get('confirmed_email');
			}
		}

		if (!empty($message) && !empty($email)) {

			if (empty($subject)) {
				$subject = T::out([
							'notification_change_deal_status' => [
								'en' => 'Deal status change notification',
								'ru' => 'Уведомление об изменении статуса сделки'
							]
				]);
			}

			if (is_array($email)) {

				//TODO: multisending
			} else {

				Mail::send([
					'to'		 => $email,
					'from_name'	 => 'CrowdEscrow.biz',
					'reply_to'	 => 'admin@crowdescrow.biz',
					'priority'	 => 0,
					'subject'	 => 'CrowdEscrow.biz ' . $subject,
					'header'	 => $subject,
					'html'		 => H::getTemplate('email/notification', [
						'header'	 => $subject,
						'name'		 => 'CrowdEscrow.biz',
						'message'	 => $message,
						'reply'		 => empty($reply)
								? ''
								: T::out([
									'counter_party_email' => [
										'en'		 => 'Counterparty email: {{email}}',
										'ru'		 => 'Email контрагента: {{email}}',
										'_include'	 => [
											'email' => $reply
										]
									]
								]),
						'link'		 => $link
							], 'addDelimeters')
				]);
			}

			return $this;
		}

		return parent::sendEmailNotification($type);
	}

	public function getDealForm() {

		$agreement = UserPaymentMethod::getBy([
					'id'		 => $this->get('method_id'),
					'_notfound'	 => true
		]);

		if ($this->get('status') == 'draft') {
			$title = T::out([
						'new_deal' => [
							'en' => 'New deal',
							'ru' => 'Новая сделка'
						]
			]);
		} elseif ($this->get('status') == 'waiting' && $this->get('isCreator') && (!$this->get('user_id') || !$this->get('accepted_by'))) {
			$title = T::out([
						'edit_deal' => [
							'en' => 'Edit deal conditions',
							'ru' => 'Редактировать условия сделки'
						]
			]);
		} elseif ($this->get('status') == 'completed' && $this->get('role') == 'customer') {

			return [
				'Deal' => [
					'showCorrectionForm' => [
						'message'	 => H::getTemplate('pages/deal/correction', [
							'corrections'			 => $this->chat(),
							'reconfirm'				 => '',
							'corrections_visible'	 => $this->get('reconfirm')
									? ''
									: 'display:none;',
							'offer_id'				 => $this->get('id'),
							'uploaded_files'		 => join('', $this->uploadedFilesHTML()),
							'shortDescription'		 => $this->getShortDescription(),
							'counterparty'			 => $this->get('accepted_by')
								], true),
						'id'		 => $this->get('id')
					]
				]
			];
		} elseif ($this->get('status') == 'disputed') {

			if (($this->get('seller_claim') && $this->get('role') == 'customer') ||
					($this->get('customer_claim') && $this->get('role') == 'seller')) {

				$title = T::out([
							'your_claim (in deal dialog)' => [
								'en' => 'Your claim',
								'ru' => 'Ваша претензия'
							]
				]);
			} else {

				$title = T::out([
							'counter_part_has_a claim' => [
								'en' => 'Counterparty has a claim',
								'ru' => 'У контрагента есть претензия'
							]
				]);
			}

			$readonly = true;
		} else {

			$title = $this->get('reconfirm')
					? T::out([
						'need_to_correct2' => [
							'en' => 'Need to fix something',
							'ru' => 'Необходимо кое-что исправить'
						]
					])
					: T::out([
						'deal_conditions' => [
							'en' => 'Deal conditions',
							'ru' => 'Условия сделки'
						]
			]);

			$readonly = true;
		}

		$screenshots = join('', $this->uploadedFilesHTML());

		$message = H::getTemplate(empty($readonly)
								? 'pages/deal/form'
								: 'pages/deal/readonly_form', [
					'offer_id'				 => $this->get('id'),
					'description'			 => empty($readonly)
							? $agreement->fget('description')
							: $this->getShortDescription(),
					'amount'				 => $this->d('price'),
					'claims'				 => $this->get($this->get('role') . '_claim')
							? $this->get($this->get('role') . '_claim')
							: $this->get(($this->get('role') == 'customer'
											? 'seller'
											: 'customer') . '_claim'),
					'claims_visible'		 => $this->get('seller_claim') || $this->get('customer_claim')
							? ''
							: 'display:none',
					'corrections_visible'	 => $this->get('reconfirm')
							? ''
							: 'display:none;',
					'readonlyMode'			 => $this->get('status') == 'disputed'
							? 'readonlyMode'
							: '',
					'corrections'			 => $this->chat(),
					'readonly'				 => empty($readonly)
							? false
							: 'readonly="true"',
					'counterparty'			 => $this->get('role') == 'customer' && $this->get('accepted_by')
							? $this->get('accepted_by')
							: (
							$this->get('role') == 'seller' && $this->get('user_id')
									? $this->get('user_id')
									: false),
					'term'					 => round($agreement->d('wait') / 24),
					'uploaded_files'		 => empty($screenshots) && empty($readonly)
							? '<div style="float:right; line-height:70px; margin-left:20px; color:lightyellow;">' . T::out([
								'upload_deal_files' => [
									'en' => '⬅ additional files',
									'ru' => '⬅ дополнительные файлы'
								]
							]) . '</div>'
							: $screenshots
						], true);

		return [
			'Deal' => [
				'form' => [
					'noaction'	 => empty($readonly)
							? false
							: true,
					'title'		 => $title,
					'message'	 => $message,
					'offer_id'	 => $this->get('id')
				]
			]
		];
	}

	/**
	 * 
	 * @param type $input if not empty - add new message
	 *
	 * 
	 */
	public function chat($message = null) {

		$json = $this->get('reconfirm');

		if (is_array($json)) {
			$data = $json;
		} else {
			$data = !empty($json)
					? json_decode($json, true)
					: [];
		}

		if (empty($data)) {
			$data = [];
		}

		if (!empty($message)) {
			$data[] = [
				'date'		 => (new DateTime())->format('Y-m-d H:i:s'),
				'message'	 => $message,
				'sender'	 => User::logged()->get('name')
			];

			$this->set([
				'reconfirm' => json_encode($data, JSON_UNESCAPED_UNICODE)
			]);
		}

		$template = H::getTemplate('pages/deal/correction_string', [], true);

		if ($message === 0 && count($data) > 0) {//last message
			$record = $data[count($data) - 1];

			$h[] = self::parse($template, [
						'partner'	 => $record['sender'],
						'message'	 => nl2br(htmlspecialchars($record['message'])),
						'date'		 => $record['date']
							], true);
		} else {
			$h = [];

			for ($i = count($data) - 1; $i >= 0; $i--) {
				$record = $data[$i];
				$h[] = self::parse($template, [
							'partner'	 => $record['sender'],
							'message'	 => nl2br(htmlspecialchars($record['message'])),
							'date'		 => $record['date']
								], true);
			}
		}

		return join('', $h);
	}

	public function outFundingStatus() {
		if ($this->d('amount') == $this->d('price') && $this->d('amount') > 0) {
			return T::out([
						'funded (inline deal)' => [
							'en' => 'funded',
							'ru' => 'про&shy;фи&shy;нан&shy;си&shy;ро&shy;ва&shy;но'
						]
			]);
		} else {
			return T::out([
						'funded (inline deal)' => [
							'en' => 'not funded',
							'ru' => 'не про&shy;фи&shy;на&shy;н&shy;си&shy;ро&shy;ва&shy;но'
						]
			]);
		}
	}

	public function outFundingColor() {

		if ($this->get('status') == 'disputed') {
			return 'display:none;';
		}

		if ($this->d('amount') == $this->d('price')) {
			return 'color:black;';
		} else {
			return 'color:red;';
		}
	}

	public function outCounterParty($user) {
		$side = $user->get('id') == $this->get('user_id')
				? ($this->get('accepted_by')
						? $this->get('accepted_by')
						: false)
				: ($this->get('user_id')
						? $this->get('user_id')
						: false);


		if (!empty($side) && $this->get('status') === 'accepted' && $this->get('role') == 'seller') {

			return '<div class="al">' .
					T::out([
						'counterparty' => [
							'en'		 => 'Counterparty: {{partner}}',
							'ru'		 => 'Контрагент: {{partner}}',
							'_include'	 => [
								'partner' => '<a class="viewUser id_' . $side . '">' . User::getBy([
									'id'		 => $side,
									'_notfound'	 => true
								])->get('name') . '</a>'
							]
						]
					]) . '</div>';
		} else {
			return '';
		}
	}

	public function outStamp() {

		if ($this->get('status') == 'completed') {
			return '<div class="pa round ac fb stamp id_'. $this->get('id') .'" style="border:2px solid mediumseagreen; color:mediumseagreen;">' . T::out([
						'COMPLETED_(stamp)' => [
							'en' => 'COMPLETED',
							'ru' => 'СДЕЛАНО'
						]
					]) . '</div>';
		} elseif ($this->get('status') == 'disputed') {
			return '<div class="pa round ac fb stamp id_'. $this->get('id') .'" style="border:2px solid tomato; color:tomato;">' . T::out([
						'complaint_(stamp)' => [
							'en' => 'COMPLAINT',
							'ru' => 'ПРЕТЕНЗИЯ'
						]
					]) . '</div>';
		}

		return '';
	}

	public function outStatus() {

		if ($this->get('status') == 'waiting') {

			if ($this->get('role') == 'customer') {

				if ($this->get('isCreator') && ($this->d('amount') == 0 || $this->d('amount') != $this->d('price'))) {
					return '<span style="color:red; font-weight:bold">' . T::out([
								'please (inline deal)' => [
									'en' => 'please fund',
									'ru' => 'профинансируйте'
								]
							]) . '</span>';
				}

				if (!$this->get('accepted_by')) {

					return H::getTemplate('pages/dialogs/line_button', [
								'title'		 => T::out([
									'please find contractor (inline deal)' => [
										'en' => 'Find a contractor',
										'ru' => 'Найдите контрагента'
									]
								]),
								'class'		 => 'add_contractor',
								'id'		 => $this->get('id'),
								'icon'		 => 'fa-user-plus',
								'background' => 'blueviolet',
								'color'		 => 'white'
									], true);
					/*
					  return '<span style="color:blueviolet;">' . T::out([
					  'please find contractor (inline deal)' => [
					  'en' => 'Find a contractor',
					  'ru' => 'Найдите контрагента'
					  ]
					  ]) . '</span>'; */
				} else {

					$counterparty = User::getBy([
								'id' => $this->get('accepted_by')
					]);

					return $this->get('isCreator')
							? T::out([
								'wait contractor 2 (inline deal)' => [
									'en'		 => 'wait for {{name}}',
									'ru'		 => 'ждет {{name}}',
									'_include'	 => [
										'name' => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>'
									]
								]
							])
							: /* T::out([
							  'your decision_mark (inline deal)' => [
							  'en'		 => '{{d}}What is your decision?</div>',
							  'ru'		 => '{{d}}Ваше решение?</div>',
							  '_include'	 => [
							  'd' => '<div class="marked_message">'
							  ]
							  ]
							  ); */
							T::out([
								'counterparty_offers_deal' => [
									'en'		 => '{{counterparty}}<br/>offers you a deal',
									'ru'		 => '{{counterparty}}<br/>предлагает Вам сделку',
									'_include'	 => [
										'counterparty' => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>'
									]
								]
					]);
				}
			} else { //seller
				if (!$this->get('user_id')) {
					return /* '<span style="color:blueviolet">' . T::out([
							  'please find contractor (inline deal)' => [
							  'en' => 'Find a contractor',
							  'ru' => 'Найдите контрагента'
							  ]
							  ]) . '</span>'; */
							H::getTemplate('pages/dialogs/line_button', [
								'title'		 => T::out([
									'please find contractor (inline deal)' => [
										'en' => 'Find a contractor',
										'ru' => 'Найдите контрагента'
									]
								]),
								'class'		 => 'add_contractor',
								'id'		 => $this->get('id'),
								'icon'		 => 'fa-user-plus',
								'background' => 'blueviolet',
								'color'		 => 'white'
									], true);
				} else {

					$counterparty = User::getBy([
								'id' => $this->get('user_id')
					]);

					return $this->get('isCreator')
							? T::out([
								'wait contractor 2 (inline deal)' => [
									'en'		 => 'wait for {{name}}',
									'ru'		 => 'ждет {{name}}',
									'_include'	 => [
										'name' => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>'
									]
								]
							])
							: /* T::out([
							  'your decision_mark (inline deal)' => [
							  'en'		 => '{{d}}What is your decision?</div>',
							  'ru'		 => '{{d}}Ваше решение?</div>',
							  '_include'	 => [
							  'd' => '<div class="marked_message">'
							  ]
							  ]
							  ]); */ T::out([
								'counterparty_offers_deal' => [
									'en'		 => '{{counterparty}}<br/>offers you a deal',
									'ru'		 => '{{counterparty}}<br/>предлагает Вам сделку',
									'_include'	 => [
										'counterparty' => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>'
									]
								]
					]);
				}
			}
		} elseif ($this->get('status') == 'accepted') {

			$counterparty = User::getBy([
						'id'		 => $this->get($this->get('find_counterparty')),
						'_notfound'	 => true
			]);

			if ($this->get('isExpired') === true) {
				return '<span style="color:red;">' . ($this->get('role') === 'customer'
								? T::out([
									'was_supposed to finish_at' => [
										'en'		 => '{{partner}} was supposed to finish {{date}}',
										'ru'		 => '{{partner}} должен был закончить {{date}}',
										'_include'	 => [
											'partner'	 => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>',
											'date'		 => $this->get('Expired')->format('Y-m-d H:00')
										]
									]
								])
								: T::out([
									'you_should_to_complete_before_(in outStatus)' => [
										'en'		 => 'You was supposed to finish {{date}}',
										'ru'		 => 'Вы должны были закончить {{date}}',
										'_include'	 => [
											'date' => $this->get('Expired')->format('Y-m-d H:00')
										]
									]
								])) . '</span>';
			} else {

				$date = $this->get('Expired')->format('Y-m-d H:00');

				$counterparty = User::getBy([
							'id'		 => $this->get($this->get('find_counterparty')),
							'_notfound'	 => true
				]);

				return $this->get('role') === 'customer'
						? T::out([
							'should_complete_at (deal outStatus_)' => [
								'en'		 => '{{partner}}<br/>should complete{{corrections}}<br/>before {{date}}',
								'ru'		 => '{{partner}}<br/>должен закончить{{corrections}}<br/>до {{date}}',
								'_include'	 => [
									'corrections'	 => $this->get('reconfirm')
											? ' ' . T::out([
												'corrections_(in_Deal_outStatus)' => [
													'en'		 => ' {{s}}corrections</span>',
													'ru'		 => ' {{s}}правки</span>',
													'_include'	 => [
														's' => '<span class="inline_deal_but deal_description id_' . $this->get('id') . '">'
													]
												]
											])
											: '',
									'partner'		 => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>',
									'date'			 => $date
								]
							]
						])
						: T::out([
							'you_should_complete_at (deal outStatus)' => [
								'en'		 => 'You should complete{{corrections}}<br/>before {{date}}',
								'ru'		 => 'Вы должны закончить{{corrections}}<br/>до {{date}}',
								'_include'	 => [
									'date'			 => $date,
									'corrections'	 => $this->get('reconfirm')
											? ' ' . T::out([
												'corrections_(in_Deal_outStatus)' => [
													'en'		 => ' {{s}}corrections</span>',
													'ru'		 => ' {{s}}правки</span>',
													'_include'	 => [
														's' => '<span class="inline_deal_but deal_description id_' . $this->get('id') . '">'
													]
												]
											])
											: ''
								]
							]
				]);
			}
		} elseif ($this->get('status') == 'completed') {

			if ($this->get('role') == 'customer') {

				$counterparty = User::getBy([
							'id' => $this->get('accepted_by')
				]);

				return /* T::out([
						  'accept or correct (inline deal)' => [
						  'en'		 => '{{d}}Accept or correct?</div>',
						  'ru'		 => '{{d}}Принять или исправить?</div>',
						  '_include'	 => [
						  'd' => '<div class="marked_message">'
						  ]
						  ]
						  ]) */T::out([
							'partner_has_completed_work' => [
								'en'		 => '{{partner}}<br/>has reporting completion',
								'ru'		 => '{{partner}}<br/>сообщает о выполнении',
								'_include'	 => [
									'partner' => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>'
								]
							]
						])/* . T::out([
				  'deal_completed_mark (inline)' => [
				  'en'		 => '{{d}}Completed</div>',
				  'ru'		 => '{{d}}Завершено</div>',
				  '_include'	 => [
				  'd'		 => '<div class="small_green_message">'
				  ]
				  ]
				  ]) */;
			} else {

				$counterparty = User::getBy([
							'id' => $this->get('user_id')
				]);

				return T::out([
							'wait contractor 2 (inline deal)' => [
								'en'		 => 'wait for {{name}}',
								'ru'		 => 'ждет {{name}}',
								'_include'	 => [
									'name' => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>'
								]
							]
						])/* . T::out([
							'deal_completed_mark (inline)' => [
								'en'		 => '{{d}}Completed</div>',
								'ru'		 => '{{d}}Завершено</div>',
								'_include'	 => [
									'date'	 => '<br/>' . ($this->get('autopay')
											? (new DateTime($this->get('autopay')))->format('Y-m-d H:m')
											: ''),
									'd'		 => '<div class="small_green_message">'
								]
							]
				])*/;
			}
		} elseif ($this->get('status') == 'disputed') {

			if ($this->get('seller_claim') && $this->get('customer_claim')) { //обоюдная жалоба - должна переехать в арбитраж
			} elseif (($this->get('seller_claim') && $this->get('role') == 'customer') ||
					($this->get('customer_claim') && $this->get('role') == 'seller')) { //self claim
				$counterparty = User::getBy([
							'id'		 => $this->get($this->get('find_counterparty')),
							'_notfound'	 => true
				]);

				return T::out([
							'should_respond_complaint' => [
								'en'		 => '{{partner}} should<br/>respond to the {{complaint}}complaint</b><br/>before {{date}}',
								'ru'		 => '{{partner}} должен<br/>ответить на {{complaint}}претензию</b><br/>до {{date}}',
								'_include'	 => [
									'complaint' => '<b style="color:red; text-decoration:underline;" class="cp deal_description id_'. $this->get('id') . '">',
									'partner'	 => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>',
									'date'		 => $this->get('Expired')->format('Y-m-d H:m')
								]
							]
				]);
			} elseif (($this->get('seller_claim') && $this->get('role') == 'seller') ||
					($this->get('customer_claim') && $this->get('role') == 'customer')) { //counter claim
				
				$counterparty = User::getBy([
					'id' => $this->get('role') == 'seller' ? $this->get('user_id') : $this->get('accepted_by')
				]);
				
				return T::out([
					'partner_has_a_claim_7' => [
						'en' => '{{partner}} has a {{complaint}}complaint</b>.{{d}}Without <b>your reaction</b> it will<br/>be admitted {{date}}</div>',
						'ru' => '{{partner}} выставил {{complaint}}претензию</b>.{{d}}Без <b>Вашего ответа</b> будет<br/>удовлетворена {{date}}</div>',
						'_include' => [
							'd' => '<div class="small_red_message ac ib" style="font-size:0.7rem; float:none;">',
							'partner'	 => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>',
							'complaint' => '<b style="color:red; text-decoration:underline;" class="cp deal_description id_'. $this->get('id') . '">',
							'date'		 => '<span style="color:white;">' . $this->get('Expired')->format('Y-m-d H:m') . '<span>'
						]
					]
				]);
				
				/*T::out([
							'admit_or_counter2 (inline deal)' => [
								'ru'		 => '{{d}}Признать жалобу?</div>',
								'en'		 => '{{d}}Admit claim or not?</div>',
								'_include'	 => [
									'd' => '<div class="marked_message">'
								]
							]
						]) . T::out([
							'deal_claim_mark 5 (inline)' => [
								'en'		 => '{{d}}Claim will be admitted {{date}}</div>',
								'ru'		 => '{{d}}Претензия будет удовлетворена {{date}}</div>',
								'_include'	 => [
									'date'	 => '<br/>' . ($this->get('autopay')
											? (new DateTime($this->get('autopay')))->format('Y-m-d H:m')
											: ''),
									'd'		 => '<div class="small_red_message deal_description id_' . $this->get('id') . '" style="float:left; max-width:110px; margin-bottom:10px;">'
								]
							]
				]);*/
				
			}
		} elseif ($this->get('status') == 'confirmed') { //not used in simple mode
			if ($this->get('role') == 'customer') {

				$counterparty = User::getBy([
							'id' => $this->get('user_id')
				]);

				return /* T::out([
						  'accept or correct (inline deal)' => [
						  'en'		 => '{{d}}Accept or correct?</div>',
						  'ru'		 => '{{d}}Принять или исправить?</div>',
						  '_include'	 => [
						  'd' => '<div class="marked_message">'
						  ]
						  ]
						  ]) */T::out([
							'partner_has_completed_work' => [
								'en'		 => '{{partner}}<br/>has reporting completion',
								'ru'		 => '{{partner}}<br/>сообщает о выполнении',
								'_include'	 => [
									'partner' => '<a class="viewUser id_' . $counterparty->get('id') . '">' . $counterparty->get('name') . '</a>'
								]
							]
						]) . T::out([
							'autopay_deal (inline)' => [
								'en'		 => '{{d}}Autopayment{{date}}</div>',
								'ru'		 => '{{d}}Автоплатеж{{date}}</div>',
								'_include'	 => [
									'date'	 => '<br/>' . ($this->get('autopay')
											? (new DateTime($this->get('autopay')))->format('Y-m-d H:m')
											: ''),
									'd'		 => '<div class="small_red_message">'
								]
							]
				]);
			} else {

				return T::out([
							'autopay_deal (inline)' => [
								'en'		 => '{{d}}Autopayment{{date}}</div>',
								'ru'		 => '{{d}}Автоплатеж{{date}}</div>',
								'_include'	 => [
									'date'	 => ' ' . ($this->get('autopay')
											? (new DateTime($this->get('autopay')))->format('Y-m-d H:m')
											: ''),
									'd'		 => '<div class="small_green_message">'
								]
							]
				]);
			}
		} elseif ($this->get('status') == 'draft') {
			return T::out([
						'draft (inline deal' => [
							'en' => 'draft',
							'ru' => 'черновик'
						]
			]);
		}

		return '';
	}

	public function uploadRestricted($image_id = null, $noexception = null) {

		if (($this->get('status') == 'draft' && $this->get('isCreator')) || //в статусе черновика можно
				($this->get('status') == 'waiting' && $this->get('isCreator') && (!$this->get('user_id') || !$this->get('accepted_by'))) //в статусе ожидания когда не установлен контрагент можно
		) {
			return $this;
		} elseif (($this->get('status') == 'completed' && $this->get('role') == 'customer') ||
				$this->get('status') == 'completed' || $this->get('status') == 'disputed' ||
				($this->get('status') == 'accepted' && ($this->get('isExpired') === true || $this->get('reconfirm')))) {

			if (empty($image_id) && $image_id !== 0) {
				return $this;
			} else {

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
		} else {
			if (empty($noexception)) {
				throw new Exception(T::out([
					'reloading or delteing restricted' => [
						'en' => 'Unable to reload or remove file because of current deal status',
						'ru' => 'Текущее состояние сделки не позволяет удалить или перезагрузить файл'
					]
				]));
			} else {
				return false;
			}
		}
	}

	public static function uploadSuccess($r, $obj) {

		if (!isset($r['image'])) { //reshow list
			if (!empty($r['chargeback'])) {

				M::jsonp([
					'parent.A.run' => [
						/* 'Balance' => [
						  'showChargeBackDialog' => [
						  'obj'		 => 'Deal',
						  'offer_id'	 => $r['chargeback']
						  ]
						  ], */
						'Balance'	 => [
							'chargeBackUploadedFiles' => [
								'obj'		 => 'Deal',
								'offer_id'	 => $r['chargeback'],
								'html'		 => join('', $obj->uploadedFilesHTML())
							]
						],
						'Site'		 => [
							'restoreFormParamters' => [
								'object' => 'Deal'
							]
						]
					]
				]);
			} else {

				M::jsonp([
					'parent.A.run' => [
						'Deal' => [
							'outputUploadedFiles' => [
								'html' => join('', $obj->uploadedFilesHTML())
							]
						]
					]
				]);
			}
		} else {//reshow current image
			$files = $obj->get('files');

			M::jsonp([
				'parent.A.run' => [
					'Site' => [
						'reloadImage' => [
							'image' => B::baseURL() . $files[$r['image'] * 1] . '?s=' . filectime('./' . $files[$r['image'] * 1])
						]
					]
				]
			]);
		}
	}

	public function get($what, $data = null) {

		if ($what == 'creator') { //создатель соглашения
			$upm = UserPaymentMethod::getBy([
						'id'	 => $this->get('method_id'),
						'mode'	 => 'deal'
			]);

			if (empty($upm)) {
				return 'no_deal';
			}

			return $upm->get('user_id') == $this->get('user_id')
					? 'customer'
					: 'seller';
		}

		//return the time while counterparty should react on deal completion
		if ($what == 'reaction_time') {

			return max($this->get('UserPaymentMethod')->d('wait') * 0.1, S::getBy([
						'key'		 => 'time_should_pay',
						'_notfound'	 => [
							'key'	 => 'time_should_pay',
							'val'	 => 8
						]
					])->d('val'));
		}

		if ($what == 'Expired') {
			return $this->get('autopay')
					? new DateTime($this->get('autopay'))
					: false;
		}

		if ($what == 'isExpired') {
			if (!$this->get('Expired')) {
				return false;
			};

			$remain = (new DateTime())->diff($this->get('Expired'));
			$sign = ($remain->format('%R') . '1') * 1;

			if ($sign < 0) {
				return true;
			} else {
				return $remain;
			}
		}

		if ($what == 'find_counterparty') {
			return $this->get('role') == 'customer'
					? 'accepted_by'
					: 'user_id';
		}

		if ($what == 'description') {
			return $this->get('UserPaymentMethod')->fget('description');
		}

		if ($what == 'title') {
			return mb_strlen($this->fget('description'), 'UTF-8') > 100
					? mb_substr($this->fget('description'), 0, 100, 'UTF-8') . ' <span style="color:blue; text-decoration:underline;">...</span>'
					: $this->fget('description');
		}

		if ($what == 'term') {
			return round($this->get('UserPaymentMethod')->d('wait') / 24);
		}

		if ($what == 'isCreator') {

			$user = !empty($data) && $data instanceof User
					? $data
					: User::logged();

			return $this->get('UserPaymentMethod')->get('user_id') == $user->get('id');
		}

		if ($what == 'role') {

			if (!empty($data) && $data instanceof User) {
				$user = $data;
			} else {
				$user = User::logged();
			}

			if ($this->get('user_id') == $user->get('id')) {
				return 'customer';
			} elseif ($this->get('accepted_by') == $user->get('id')) {
				return 'seller';
			} else {
				throw new Exception('not linked');
			}
		}

		if ($what == 'invitation') {

			if ($this->invitation) {
				return $this->invitation;
			} else {
				return $this->set([
							'invitation' => md5(microtime())
						])->get('invitation');
			}
		}

		return parent::get($what, $data);
	}

	public function outFooterButtons() {

		if ($this->get('status') === 'waiting' &&
				$this->get('UserPaymentMethod')->get('user_id') == User::logged()->get('id') &&
				$this->get('user_id') && $this->get('accepted_by')
		) {

			return H::getTemplate('pages/dialogs/line_button', [
						'title'		 => T::out([
							'withdraw_the_offer_2 (in deal list)' => [
								'en' => 'Temporary withdraw the offer',
								'ru' => 'Временно отозвать предложение'
							]
						]),
						'class'		 => 'reject_counterparty',
						'id'		 => $this->get('id'),
						'icon'		 => 'fa-pause',
						'background' => 'gold',
						'color'		 => 'white'
							], true);
		} elseif ($this->get('status') === 'accepted' && $this->get('role') == 'seller') {

			return H::getTemplate('pages/dialogs/line_button', [
						'title'		 => T::out([
							'deal_completed (in deal list)' => [
								'en' => 'Report completion',
								'ru' => 'Сообщить о завершении'
							]
						]),
						'class'		 => 'deal_completed',
						'id'		 => $this->get('id'),
						'icon'		 => 'fa-flag-checkered',
						'background' => 'mediumseagreen',
						'color'		 => 'white'
							], true);
		} elseif ($this->get('status') === 'waiting' && $this->get('UserPaymentMethod')->get('user_id') != User::logged()->get('id')) {
			return join('', [
				H::getTemplate('pages/dialogs/line_button', [
					'title'		 => T::out([
						'accept_the_deal (in deal list)' => [
							'en' => 'Accept conditions',
							'ru' => 'Принять условия'
						]
					]),
					'class'		 => 'accept_deal',
					'id'		 => $this->get('id'),
					'icon'		 => 'fa-handshake-o',
					'background' => 'mediumseagreen',
					'color'		 => 'white'
						], true),
			]);
		} elseif ($this->get('status') == 'completed' && $this->get('role') == 'seller') {

			return H::getTemplate('pages/dialogs/line_button', [
						'title'		 => T::out([
							'restart_work2 (in deal list)' => [
								'en' => 'Restart work',
								'ru' => 'Еще не закончено'
							]
						]),
						'class'		 => 'deal_restart',
						'id'		 => $this->get('id'),
						'icon'		 => 'fa-undo',
						'background' => 'gold',
						'color'		 => 'white'
							], true);
		} elseif (in_array($this->get('status'), ['accepted',
					'completed']) && $this->get('role') == 'customer') {

			return join('<div style="height:5px;"></div>', [
				H::getTemplate('pages/dialogs/line_button', [
					'title'		 => '<span style="font-size:' . (T::getLocale() == 'ru'
							? 0.7
							: 0.8) . 'rem;">' . T::out([
						'accept_results (in deal list)' => [
							'en' => 'Accept results and release deposit',
							'ru' => 'Принять результаты и выплатить депозит'
						]
					]) . '</span>',
					'class'		 => 'deal_ok',
					'id'		 => $this->get('id'),
					'icon'		 => 'fa-thumbs-o-up',
					'background' => 'mediumseagreen',
					'color'		 => 'white'
						], true),
				$this->get('status') == 'completed'
						?
						H::getTemplate('pages/dialogs/line_button', [
							'title'		 => T::out([
								'deal_correctionss (in deal list)' => [
									'en' => 'Need to correct',
									'ru' => 'Необходимо исправить'
								]
							]),
							'class'		 => 'deal_correct',
							'id'		 => $this->get('id'),
							'icon'		 => 'fa-thumbs-o-down',
							'background' => 'tomato',
							'color'		 => 'white'
								], true)
						: null
					]
			);
		} elseif ($this->get('status') == 'disputed' && (($this->get('seller_claim') && $this->get('role') == 'seller') ||
					($this->get('customer_claim') && $this->get('role') == 'customer'))) {
			
			return join('<div style="height:5px;"></div>', [
				H::getTemplate('pages/dialogs/line_button', [
							'title'		 => T::out([
								'admit_claim_2 (in deal list)' => [
									'en' => 'Admit the claim',
									'ru' => 'Признать жалобу'
								]
							]),
							'class'		 => 'deal_admit_claim',
							'id'		 => $this->get('id'),
							'icon'		 => 'fa-thumbs-o-up',
							'background' => 'mediumseagreen',
							'color'		 => 'white'
								], true),
				H::getTemplate('pages/dialogs/line_button', [
							'title'		 => T::out([
								'run_counter_claim_2 (in deal list)' => [
									'en' => 'Send a counter claim',
									'ru' => 'Встречная претензия'
								]
							]),
							'class'		 => 'deal_chargeback isCounter',
							'id'		 => $this->get('id'),
							'icon'		 => 'fa-gavel',
							'background' => 'tomato',
							'color'		 => 'white'
								], true)
			]);
			
		} elseif ($this->get('status') == 'disputed' && (($this->get('seller_claim') && $this->get('role') == 'customer') ||
					($this->get('customer_claim') && $this->get('role') == 'seller'))) {	
			
			return H::getTemplate('pages/dialogs/line_button', [
							'title'		 => T::out([
								'canel_claim (in deal list)' => [
									'en' => 'Cancel the claim',
									'ru' => 'Отменить жалобу'
								]
							]),
							'class'		 => 'deal_cancel_claim',
							'id'		 => $this->get('id'),
							'icon'		 => 'fa-ban',
							'background' => 'mediumseagreen',
							'color'		 => 'white'
								], true);
			
		} else {
			return '';
		}
	}

	public function outButtons($offer_id) {

		$h = [];

		if ($this->get('status') === 'waiting') {

			$h[] = H::getTemplate('pages/dialogs/button', [
						'title'		 => T::out([
							'cancel_deal (in deal list)' => [
								'en' => 'Cancel the deal',
								'ru' => 'Отменить сделку'
							]
						]),
						'class'		 => 'cancel_deal' . ($this->d('amount') == $this->d('price')
								? ' isFunded'
								: '') . ($this->get('isCreator')
								? ' isCreator'
								: ''),
						'id'		 => $offer_id,
						'icon'		 => 'fa-times',
						'background' => 'tomato',
						'color'		 => 'white'
							], true);

			/* $h[] = H::getTemplate('pages/deal/buttons/cancel', [
			  'offer_id' => $offer_id
			  ], true); */

			if ($this->get('UserPaymentMethod')->get('user_id') == User::logged()->get('id')) {

				if ($this->get('user_id') && $this->get('accepted_by')) {
					$h[] = H::getTemplate('pages/dialogs/button', [
								'title'		 => T::out([
									'deal_description (in deal list)' => [
										'en' => 'Detailed description',
										'ru' => 'Детальное описание'
									]
								]),
								'class'		 => 'deal_description',
								'id'		 => $offer_id,
								'icon'		 => 'fa-file-text-o',
								'background' => 'royalblue',
								'color'		 => 'white'
									], true);
				} else {
					$h[] = H::getTemplate('pages/deal/buttons/edit', [
								'offer_id' => $offer_id
									], true);
				}
			} else {
				/*
				  $h[] = H::getTemplate('pages/dialogs/button', [
				  'title'		 => T::out([
				  'accept_the_deal (in deal list)' => [
				  'en' => 'Accept conditions',
				  'ru' => 'Принять условия'
				  ]
				  ]),
				  'class'		 => 'accept_deal',
				  'id'		 => $offer_id,
				  'icon'		 => 'fa-check',
				  'background' => 'mediumseagreen',
				  'color'		 => 'white'
				  ], true); */

				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'deal_description (in deal list)' => [
									'en' => 'Detailed description',
									'ru' => 'Детальное описание'
								]
							]),
							'class'		 => 'deal_description',
							'id'		 => $offer_id,
							'icon'		 => 'fa-file-text-o',
							'background' => 'royalblue',
							'color'		 => 'white'
								], true);
			}
		} elseif ($this->get('status') === 'accepted') {

			if ($this->get('role') == 'seller') {

				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'cancel_deal (in deal list)' => [
									'en' => 'Cancel the deal',
									'ru' => 'Отменить сделку'
								]
							]),
							'class'		 => 'cancel_deal' . ($this->d('amount') == $this->d('price')
									? ' isFunded'
									: '') . ($this->get('isCreator')
									? ' isCreator'
									: ''),
							'id'		 => $offer_id,
							'icon'		 => 'fa-times',
							'background' => 'tomato',
							'color'		 => 'white'
								], true);

				if ($this->get('reconfirm')) {

					$h[] = H::getTemplate('pages/dialogs/button', [
								'title'		 => T::out([
									'run_claim (in deal list)' => [
										'en' => 'Send a claim',
										'ru' => 'Отправить претензию'
									]
								]),
								'class'		 => 'deal_chargeback',
								'id'		 => $offer_id,
								'icon'		 => 'fa-gavel',
								'background' => 'tomato',
								'color'		 => 'white'
									], true);
				}
			}

			if ($this->get('role') == 'customer' && $this->get('isExpired') === true) {

				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'run_claim (in deal list)' => [
									'en' => 'Send a claim',
									'ru' => 'Отправить претензию'
								]
							]),
							'class'		 => 'deal_chargeback',
							'id'		 => $offer_id,
							'icon'		 => 'fa-gavel',
							'background' => 'tomato',
							'color'		 => 'white'
								], true);
			}

			if ($this->get('role') == 'customer') {

				/* $h[] = H::getTemplate('pages/dialogs/button', [
				  'title'		 => T::out([
				  'accept_results (in deal list)' => [
				  'en' => 'Accept results and release deposit',
				  'ru' => 'Принять результаты и выплатить депозит'
				  ]
				  ]),
				  'class'		 => 'deal_ok',
				  'id'		 => $offer_id,
				  'icon'		 => 'fa-thumbs-o-up',
				  'background' => 'mediumseagreen',
				  'color'		 => 'white'
				  ], true); */
			}

			$h[] = H::getTemplate('pages/dialogs/button', [
						'title'		 => T::out([
							'deal_description (in deal list)' => [
								'en' => 'Detailed description',
								'ru' => 'Детальное описание'
							]
						]),
						'class'		 => 'deal_description',
						'id'		 => $offer_id,
						'icon'		 => 'fa-file-text-o',
						'background' => 'royalblue',
						'color'		 => 'white'
							], true);
		} elseif ($this->get('status') == 'completed') {

			if ($this->get('role') == 'customer') {

				if ($this->get('isExpired') === true || $this->get('reconfirm')) {

					$h[] = H::getTemplate('pages/dialogs/button', [
								'title'		 => T::out([
									'run_claim (in deal list)' => [
										'en' => 'Send a claim',
										'ru' => 'Отправить претензию'
									]
								]),
								'class'		 => 'deal_chargeback',
								'id'		 => $offer_id,
								'icon'		 => 'fa-gavel',
								'background' => 'tomato',
								'color'		 => 'white'
									], true);
				}
				/*
				  $h[] = H::getTemplate('pages/dialogs/button', [
				  'title'		 => T::out([
				  'accept_results (in deal list)' => [
				  'en' => 'Accept results and release deposit',
				  'ru' => 'Принять результаты и выплатить депозит'
				  ]
				  ]),
				  'class'		 => 'deal_ok',
				  'id'		 => $offer_id,
				  'icon'		 => 'fa-thumbs-o-up',
				  'background' => 'mediumseagreen',
				  'color'		 => 'white'
				  ], true); */
				/*
				  $h[] = H::getTemplate('pages/dialogs/button', [
				  'title'		 => T::out([
				  'deal_correctionss (in deal list)' => [
				  'en' => 'Need to correct',
				  'ru' => 'Необходимо исправить'
				  ]
				  ]),
				  'class'		 => 'deal_correct',
				  'id'		 => $offer_id,
				  'icon'		 => 'fa-thumbs-o-down',
				  'background' => 'tomato',
				  'color'		 => 'white'
				  ], true); */
			} else {


				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'cancel_deal (in deal list)' => [
									'en' => 'Cancel the deal',
									'ru' => 'Отменить сделку'
								]
							]),
							'class'		 => 'cancel_deal' . ($this->d('amount') == $this->d('price')
									? ' isFunded'
									: '') . ($this->get('isCreator')
									? ' isCreator'
									: ''),
							'id'		 => $offer_id,
							'icon'		 => 'fa-times',
							'background' => 'tomato',
							'color'		 => 'white'
								], true);

				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'run_claim (in deal list)' => [
									'en' => 'Send a claim',
									'ru' => 'Отправить претензию'
								]
							]),
							'class'		 => 'deal_chargeback',
							'id'		 => $offer_id,
							'icon'		 => 'fa-gavel',
							'background' => 'tomato',
							'color'		 => 'white'
								], true);
				/*
				  $h[] = H::getTemplate('pages/dialogs/button', [
				  'title'		 => T::out([
				  'restart_work2 (in deal list)' => [
				  'en' => 'Restart work',
				  'ru' => 'Еще не закончено'
				  ]
				  ]),
				  'class'		 => 'deal_restart',
				  'id'		 => $offer_id,
				  'icon'		 => 'fa-undo',
				  'background' => 'gold',
				  'color'		 => 'white'
				  ], true); */

				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'deal_description (in deal list)' => [
									'en' => 'Detailed description',
									'ru' => 'Детальное описание'
								]
							]),
							'class'		 => 'deal_description',
							'id'		 => $offer_id,
							'icon'		 => 'fa-file-text-o',
							'background' => 'royalblue',
							'color'		 => 'white'
								], true);
			}
		} elseif ($this->get('status') == 'disputed') {

			if ($this->get('seller_claim') && $this->get('customer_claim')) {
				//обоюдная жалоба - должна переехать в арбитраж
			} elseif (($this->get('seller_claim') && $this->get('role') == 'customer') ||
					($this->get('customer_claim') && $this->get('role') == 'seller')) {
				//self claim

				/*
				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'canel_claim (in deal list)' => [
									'en' => 'Cancel the claim',
									'ru' => 'Отменить жалобу'
								]
							]),
							'class'		 => 'deal_cancel_claim',
							'id'		 => $offer_id,
							'icon'		 => 'fa-ban',
							'background' => 'mediumseagreen',
							'color'		 => 'white'
								], true); */


				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'deal_description (in deal list)' => [
									'en' => 'Detailed description',
									'ru' => 'Детальное описание'
								]
							]),
							'class'		 => 'deal_description',
							'id'		 => $offer_id,
							'icon'		 => 'fa-file-text-o',
							'background' => 'royalblue',
							'color'		 => 'white'
								], true);
			} elseif (($this->get('seller_claim') && $this->get('role') == 'seller') ||
					($this->get('customer_claim') && $this->get('role') == 'customer')) {
/*
				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'admit_claim (in deal list)' => [
									'en' => 'Admit the claim',
									'ru' => 'Признать жалобу'
								]
							]),
							'class'		 => 'deal_admit_claim',
							'id'		 => $offer_id,
							'icon'		 => 'fa-thumbs-o-up',
							'background' => 'mediumseagreen',
							'color'		 => 'white'
								], true);

				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'run_counter_claim (in deal list)' => [
									'en' => 'Send a counter claim',
									'ru' => 'Отправить встречную претензию'
								]
							]),
							'class'		 => 'deal_chargeback isCounter',
							'id'		 => $offer_id,
							'icon'		 => 'fa-gavel',
							'background' => 'tomato',
							'color'		 => 'white'
								], true); */

				$h[] = H::getTemplate('pages/dialogs/button', [
							'title'		 => T::out([
								'deal_description (in deal list)' => [
									'en' => 'Detailed description',
									'ru' => 'Детальное описание'
								]
							]),
							'class'		 => 'deal_description',
							'id'		 => $offer_id,
							'icon'		 => 'fa-file-text-o',
							'background' => 'royalblue',
							'color'		 => 'white'
								], true);
			}
		}

		return join('', $h);
	}

	public function outDate() {
		$a = explode(':', $this->fget('changed'));
		unset($a[2]);
		return join(':', $a);
	}

	public static function getList($who) {

		//TODO: add pagination

		$user = User::logged();

		$list = self::getBy($who == 'customer'
								? [
							'currency'	 => '_deal',
							'user_id'	 => $user->get('id'),
							'status'	 => '!=draft',
							'_return'	 => [0 => 'object']
								]
								: [
							'currency'		 => '_deal',
							'accepted_by'	 => $user->get('id'),
							'status'		 => '!=draft',
							'_return'		 => [0 => 'object']
		]);

		$h = [
			'my'		 => [
				T::out([
					'my_offers (h2)' => [
						'en'		 => '{{h}}My offers{{/h}}',
						'ru'		 => '{{h}}Мои предложения{{/h}}',
						'_include'	 => [
							'h'	 => '<h2>',
							'/h' => '</h2>'
						]
					]
				])
			],
			'me'		 => [],
			'accepted'	 => [],
			'completed'	 => [],
			'disputed'	 => []
		];

		$template = H::getTemplate('pages/deal/inline', [], true);

		foreach ($list as $deal) {

			$upm = $deal->get('UserPaymentMethod');

			$footer_buttons = $deal->outFooterButtons();

			$html = self::parse($template, [
						'offer_id'			 => $deal->get('id'),
						'additionalClass'	 => 'add_' . $deal->get('status') . '_' . ($deal->get('UserPaymentMethod')->get('user_id') == $user->get('id')
								? 'my'
								: 'me'),
						//'description' => htmlspecialchars($upm->get('description')),
						'description'		 => '',
						'buttons'			 => $deal->outButtons($deal->get('id')),
						'term'				 => round($upm->d('wait') / 24), //TODO: outTerm
						'fundingColor'		 => $deal->outFundingColor(),
						'amount'			 => $deal->d('price'),
						'date'				 => $deal->outDate(),
						'fundingStatus'		 => $deal->outFundingStatus(),
						'status'			 => $deal->outStatus(),
						'title'				 => $deal->get('title'),
						'screenshots'		 => join('', $deal->uploadedFilesHTML()),
						'alignment'			 => in_array($deal->get('status'), [
							'accepted',
						]) || ($deal->get('status') == 'confirmed' && $deal->get('role') == 'seller') ||
						($deal->get('status') == 'disputed' && (
						($deal->get('seller_claim') && $deal->get('role') == 'customer') ||
						($deal->get('customer_claim') && $deal->get('role') == 'seller')
						))
								? 'al'
								: 'ac',
						'counterparty'		 => $deal->outCounterparty($user),
						'footer'			 => $footer_buttons,
						'show_footer'		 => empty($footer_buttons)
								? 'display:none;'
								: '',
						'stamp'				 => $deal->outStamp(),
						'termStyle'			 => in_array($deal->get('status'), [
							'accepted',
							'confirmed',
							'completed',
							'disputed'
						])
								? 'display:none;'
								: 'float:left;'
							], true);

			if ($deal->get('status') == 'waiting') {

				if ($deal->get('UserPaymentMethod')->get('user_id') == $user->get('id')) {
					$h['my'][] = $html;
				} else {
					if (empty($h['me'])) {
						$h['me'][] = T::out([
									'me_offers (h2)' => [
										'en'		 => '{{h}}Offers to me{{/h}}',
										'ru'		 => '{{h}}Предложения мне{{/h}}',
										'_include'	 => [
											'h'	 => '<h2>',
											'/h' => '</h2>'
										]
									]
						]);
					}

					$h['me'][] = $html;
				}
			} elseif (in_array($deal->get('status'), [
						'accepted'
					])) {

				if (empty($h['accepted'])) {
					$h['accepted'][] = T::out([
								'accepted (h2)' => [
									'en'		 => '{{h}}In operation{{/h}}',
									'ru'		 => '{{h}}В работе{{/h}}',
									'_include'	 => [
										'h'	 => '<h2>',
										'/h' => '</h2>'
									]
								]
					]);
				}

				if ($deal->get('status') === 'confirmed' && $deal->get('isExpired') === true) {
					//do not show if expired
				} else {
					$h['accepted'][] = $html;
				}
			} elseif ($deal->get('status') == 'completed') {

				if (empty($h['completed'])) {
					$h['completed'][] = T::out([
								'completed (h2)' => [
									'en'		 => '{{h}}Completed{{/h}}',
									'ru'		 => '{{h}}Выполнены{{/h}}',
									'_include'	 => [
										'h'	 => '<h2>',
										'/h' => '</h2>'
									]
								]
					]);
				}

				$h['completed'][] = $html;
			} elseif ($deal->get('status') == 'disputed') {

				if (empty($h['disputed'])) {
					$h['disputed'][] = T::out([
								'disputed (h2)' => [
									'en'		 => '{{h}}Disputed{{/h}}',
									'ru'		 => '{{h}}Спорные{{/h}}',
									'_include'	 => [
										'h'	 => '<h2>',
										'/h' => '</h2>'
									]
								]
					]);
				}

				$h['disputed'][] = $html;
			}
		} //foreach


		if (count($h['my']) > 1) {
			$l = true;
		}

		$h['my'][] = H::getTemplate('pages/deal/inline_help', [], true);

		if (!empty($l)) {
			$h['my'][] = H::getTemplate('pages/deal/inline_help_my', [], true);
		}

		if (count($h['me']) > 0) {
			$h['me'][] = H::getTemplate('pages/deal/inline_help_me', [], true);
		}

		if (count($h['accepted'])) {
			$h['accepted'][] = H::getTemplate('pages/deal/inline_help_accepted', [], true);
		}

		if (count($h['completed'])) {
			$h['completed'][] = H::getTemplate('pages/deal/inline_help_completed', [], true);
		}

		if (count($h['disputed'])) {
			$h['disputed'][] = H::getTemplate('pages/deal/inline_help_disputed', [], true);
		}

		return '<div class="my_444">' . join('', $h['my']) .
				'</div><div class="me_444">' . join('', $h['me']) .
				'</div><div class="accepted_444">' . join('', $h['accepted']) .
				'</div><div class="completed_444">' . join('', $h['completed']) .
				'</div><div class="disputed_444">' . join('', $h['disputed']);
	}

	/**
	 * automatic processor
	 */
	public static function auto() {

		$count = self::getBy([
					'status'	 => [
						'disputed'
					],
					'currency'	 => '_deal',
					'autopay'	 => [
						'_between' => [
							'0000-00-00 00:00',
							(new DateTime())->format('Y-m-d H:i:s')
						]
					],
					'_return'	 => 'count'
		]);

		if ($count > 0) {

			$deals = self::getBy([
						'status'	 => [
							'disputed'
						],
						'currency'	 => '_deal',
						'autopay'	 => [
							'_between' => [
								'0000-00-00 00:00',
								(new DateTime())->format('Y-m-d H:i:s')
							]
						],
						'_return'	 => [0 => 'object'],
						'_limit'	 => 10
			]);

			foreach ($deals as $deal) {
				if ($deal->get('status') == 'disputed') { //автоудовлетворение жалобы
					if ($deal->get('seller_claim') && $deal->get('customer_claim')) {
						//arbitrage mode no need to do anything
					} elseif ($deal->get('seller_claim') || $deal->get('customer_claim')) {
						$deal->removeAllExperts()
								->returnArbitrageDeposit($deal->get($deal->get('seller_claim')
														? 'accepted_by'
														: 'user_id'))
								->rateSeller($deal->get('seller_claim')
												? 'failure'
												: 'success')
								->returnFunding($deal->get($deal->get('seller_claim')
														? 'user_id'
														: 'accepted_by'))
								->setActions([
									'update_deals_list',
									'update_balance'], [
									$deal->get('user_id'),
									$deal->get('accepted_by')
								])
								->sendEmailNotification('expired', [
									$deal->get('user_id') => $deal->get('accepted_by')
								])
								->sendEmailNotification('expired', [
									$deal->get('accepted_by') => $deal->get('user_id')
								])
								->set([
									'status' => 'deleted'
						]);
					}
				}
			}
		}
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
