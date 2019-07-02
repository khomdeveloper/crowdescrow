<?php

/**
 * Description of Expert
 *
 * @author valera261104
 */
class Expert extends M {

	public static function checkInvitation() {
		if (isset($_REQUEST['expert_public_invite'])) {

			$expert = self::getBy([
						'invitation' => $_REQUEST['expert_public_invite'],
						'user_id'	 => 'is null'
			]);

			if (!empty($expert)) {
				$_SESSION['expert_public_invite'] = $_REQUEST['expert_public_invite'];
			}

			header('Location: https://crowdescrow.biz');
			exit;
		}

		$result = [
			'afterLogin'	 => false,
			'beforeLogin'	 => false
		];

		if (isset($_SESSION['expert_public_invite'])) {//expert public invite
			$expert = Expert::getBy([
						'invitation' => $_SESSION['expert_public_invite'],
						'user_id'	 => 'is null',
						'_return'	 => 'count'
			]);

			if (!empty($expert)) {
				$result['afterLogin'] = "A.w(['B'], function() {B.get({Expert: 'public_invite'});});";
			} else {
				unset($_SESSION['expert_public_invite']);
			}

			if (isset($_SESSION['expert_public_invite']) && !User::isLogged()) {
				$result['beforeLogin'] = "				D.show({
										title: '" .
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
										'as_an_expert (get_ref)' => [
											'en' => 'as an expert',
											'ru' => 'в качестве эксперта'
										]
									])
								]
							]
						]) . "',
										message: false,
										waitWhile : true,
										css: D.getCSS({type: 'norm'})
									});";
			}
		}
		return $result;
	}

	public static function action($r, $dontdie = false) {
		$com = empty($r[get_called_class()])
				? false
				: $r[get_called_class()];

		$user = User::logged();

		if ($com == 'invite') {

			self::required([
				'user_id'	 => true,
				'claim_id'	 => true
					], $r);

			$output = self::inviteExpert([
						'user_id'	 => $r['user_id'],
						'claim_id'	 => $r['claim_id']
			]);


			$claim = $output['claim'];
			$expert = $output['expert'];

			//send notification to invited expert

			Action::getBy([
				'key'		 => 'invite_as_expert',
				'_notfound'	 => [
					'key'	 => 'invite_as_expert',
					'action' => json_encode([
						'Arbitrage' => [
							'checkInvitation' => [
								'data' => 'none'
							]
						]
					])
				]
			])->setFor($expert->get('user_id'));

			//Update arbitrage list
			$claim->setActions([
				'update_arbitrage_list'
					], $claim->get('user_id'))->setActions([
				'update_arbitrage_list'
					], $claim->get('accepted_by'))->set([
				$claim->get('iam') . '_declines' => $claim->d($claim->get('iam') . '_declines') + 1
			]);

			//send email notification to expert

			$user = User::getBy([
						'id'		 => $expert->get('user_id'),
						'_notfound'	 => true
			]);

			$email = $user->get('confirmed_email');

			if (!empty($email)) {

				Mail::send([
					'to'		 => $email,
					'from_name'	 => 'CrowdEscrow.biz',
					'reply_to'	 => 'admin@crowdescrow.biz',
					'priority'	 => 0,
					'subject'	 => 'CrowdEscrow.biz ' . T::out([
						'are_you_agree_to_act_as_expert' => [
							'en' => 'Are you ready to act as expert on the disputed transaction?',
							'ru' => 'Вы готовы выступить экспертом по спорной сделке?'
						]
					]),
					'html'		 => H::getTemplate('email/expert_invitation', [
						'header'	 => T::out([
							'are_you_agree_to_act_as_expert' => [
								'en' => 'Are you ready to act as expert on the disputed transaction?',
								'ru' => 'Вы готовы выступить экспертом по спорной сделке?'
							]
						]),
						'name'		 => 'CrowdEscrow.biz',
						'message'	 => T::out([
							'invitation_as_an_expert' => [
								'en'		 => 'Dear {{name}}!<br/>We would like to invite You as an expert in disputed deal. Follow the link to get a detailes.',
								'ru'		 => 'Уважаемый {{name}}<br/>Мы хотим пригласить Вас в качестве эксперта в спорной сделке. Детали спора по ссылке.',
								'_include'	 => [
									'name' => $user->get('name')
								]
							]
						]),
						'link'		 => B::setProtocol('https:', B::baseURL() . 'arbitrage')
							], 'addDelimeters')
				]);
			}

			return [
				'Arbitrage' => [
					'updateList' => [
						'no' => 'data'
					]
				]
			];
		} elseif ($com == 'personal_invite') { //not used ?
			if (empty($_SESSION['expert_personal_invite'])) {
				return [
					'invitation' => 'empty'
				];
			}

			$expert = self::getBy([
						'invitation' => $_SESSION['expert_personal_invite'],
						'user_id'	 => 'is not null',
						'expert'	 => 'na',
						'seller'	 => 'na',
						'customer'	 => 'na',
						'status'	 => 'waiting'
			]);

			if (empty($expert)) {
				return [
					'invitation' => 'already'
				];
			} else {

				//TODO:
			}
		} elseif ($com == 'public_invite') {

			if (empty($_SESSION['expert_public_invite'])) {
				return [
					'invitation' => 'empty'
				];
			}

			try {


				$claim = Claim::getBy([
							'invitation' => $_SESSION['expert_public_invite'],
							'user_id'	 => 'is null'
				]);

				if (empty($claim)) {
					throw new Exception('Claim not found');
				}

				$output = self::inviteExpert([
							'user_id'	 => User::logged()->get('id'),
							'claim_id'	 => $claim->get('claim_id')
				]);

				return [
					'Arbitrage' => [
						'offerToExpert' => [
							'html'		 => $output['claim']->getHTML(H::getTemplate('pages/arbitrage/' . ($output['claim']->get('currency') == '_deal'
													? 'deal'
													: 'balance') . '_claim_short', [], true), 'noexperts'),
							'max'		 => round(S::getBy([
										'key'		 => 'max_expertise_royalty_percent',
										'_notfound'	 => [
											'key'	 => 'max_expertise_royalty_percent',
											'val'	 => 1
										]
									])->d('val') * $output['claim']->get('amount') / 100, 2),
							'claim_id'	 => $output['claim']->get('id'),
							'id'		 => $output['expert']->get('id')
						]
					]
				];
			} catch (Exception $e) {
				unset($_SESSION['expert_public_invite']);
				return [
					'invitation' => $e->getMessage()
				];
			}
		} elseif ($com == 'search') {

			self::required([
				'what'	 => true,
				'page'	 => true
					], $r);

			$restricted = [];

			$marked = [];

			if (!empty($r['claim_id'])) {
				$offer = Offer::getBy([
							'id'		 => $r['claim_id'],
							'_notfound'	 => true
				]);

				$restricted[] = $offer->get('user_id') == $user->get('id')
						? $offer->get('accepted_by')
						: $offer->get('user_id');

				//add as restricted users which was declined by contractor or expert

				$declinedExperts = Expert::getBy([
							'claim_id'	 => $r['claim_id'],
							'||'		 => [
								'expert'	 => 'no',
								'seller'	 => 'no',
								'customer'	 => 'no'
							],
							'_return'	 => [
								'user_id' => 'object'
							]
				]);

				if (!empty($declinedExperts)) {
					$restricted = array_merge($restricted);
				}

				$invitedExperts = self::getBy([
							'claim_id'	 => $r['claim_id'],
							'user_id'	 => '>>0',
							'customer'	 => ['ok',
								'na'],
							'expert'	 => ['ok',
								'na'],
							'seller'	 => ['ok',
								'na'],
							'_return'	 => [
								'user_id' => 'object'
							]
				]);

				$marked = array_merge(empty($declinedExperts)
								? []
								: array_keys($declinedExperts), empty($invitedExperts)
								? []
								: array_keys($invitedExperts));
			}


			return User::findUsers($r['what'], $r['page'], $restricted, $marked);
		} elseif ($com == 'vote') {

			self::required([
				'id'	 => true,
				'side'	 => true
					], $r);

			if (!in_array($r['side'], [
						'seller',
						'customer'
					])) {
				throw new Exception('Uncknown status');
			}

			//расставить статусы

			$expert = self::getBy([
						'id'		 => $r['id'],
						'seller'	 => 'ok',
						'customer'	 => 'ok',
						'expert'	 => 'ok',
						'result'	 => 'na',
						'_notfound'	 => true
					])->set([
						'result' => $r['side']
					])->checkVotationCompleted();

			return [
				'Arbitrage' => [
					'updateList' => [
						'data' => 'none'
					]
				]
			];
		} elseif ($com == 'accept_this_expert') {

			self::required([
				'id' => true
					], $r);

			$expert = Expert::getBy([
						'id'		 => $r['id'],
						'expert'	 => 'ok',
						'result'	 => 'na',
						'_notfound'	 => true
			]);

			$claim = Claim::getBy([
						'id'		 => $expert->get('claim_id'),
						'_notfound'	 => true
			]);

			$need = $expert->d('price');

			if ($need > $claim->d($claim->get('iam') . '_hold')) {

				throw new Exception(T::out([
					'expertise_budget_exhausted' => [
						'en' => 'The budget allocated for the expertise exhausted',
						'ru' => 'Бюджет выделенный на экспертизу исчерпан'
					]
				]));
			}

			//принятие со своей стороны

			$claim = $claim->set([
				$claim->get('iam') . '_hold' => $claim->get($claim->get('iam') . '_hold') - $need
			]);

			$expert = $expert->set([
				$claim->get('iam') . '_hold' => $need,
				$claim->get('iam')			 => 'ok'
			]);

			//counterparty

			if ($claim->d($claim->get('notme') . '_declines') == 0 && false) { //no possibility to decline for counterparty
				if ($need <= $claim->d($claim->get('notme') . '_hold')) {

					$claim = $claim->set([
						$claim->get('notme') . '_hold' => $claim->get($claim->get('notme') . '_hold') - $need
					]);

					$expert = $expert->set([
						$claim->get('notme') . '_hold'	 => $need,
						$claim->get('notme')			 => 'ok'
					]);
				} else {
					throw new Exception(T::out([
						'expertise_budget_exhausted_counterparty' => [
							'en' => 'The counterparty budget allocated for the expertise exhausted',
							'ru' => 'Бюджет контрагента выделенный на экспертизу исчерпан'
						]
					]));
				}
			}

			//акции

			$claim->setActions([
				'update_arbitrage_list'
					], $expert->get('user_id'));

			if ($claim->get('iam') == 'seller') {
				$claim->setActions([
					'update_arbitrage_list'
						], $claim->get('accepted_by'));
			} else {
				$claim->setActions([
					'update_arbitrage_list'
						], $claim->get('user_id'));
			}


			if ($expert->get('expert') == 'ok' && $expert->get('seller') == 'ok' && $expert->get('customer') == 'ok') {

				$emails = [
					$expert->get('confirmed_email'),
					User::getBy([
						'id'		 => $claim->get('user_id'),
						'_notfound'	 => true
					])->get('confirmed_email'),
					User::getBy([
						'id'		 => $claim->get('accepted_by'),
						'_notfound'	 => true
					])->get('confirmed_email')
				];

				foreach ($emails as $email) {
					if (!empty($email)) {

						Mail::send([
							'to'		 => $email,
							'from_name'	 => 'CrowdEscrow.biz',
							'reply_to'	 => 'admin@crowdescrow.biz',
							'priority'	 => 0,
							'subject'	 => 'CrowdEscrow.biz ' . T::out([
								'expert_accepted_by_sides' => [
									'en' => 'Expert accepted by dispute sides',
									'ru' => 'Эксперт признан сторонами спора'
								]
							]),
							'html'		 => H::getTemplate('email/expert_invitation', [
								'header'	 => T::out([
									'expert_accepted_by_sides' => [
										'en' => 'Expert accepted by dispute sides',
										'ru' => 'Эксперт признан сторонами спора'
									]
								]),
								'name'		 => 'CrowdEscrow.biz',
								'message'	 => T::out([
									'expert_accepted_by_sides_description2' => [
										'en'		 => '{{name}} accepted as expert by both sides of the dispute',
										'ru'		 => '{{name}} принят обеими сторонами спора в качестве эксперта',
										'_include'	 => [
											'name' => User::getBy([
												'id'		 => $expert->get('user_id'),
												'_notfound'	 => true
											])->get('name')
										]
									]
								]),
								'link'		 => B::setProtocol('https:', B::baseURL() . 'arbitarge')
									], 'addDelimeters')
						]);
					}
				}
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
		} elseif ($com == 'decline_this_expert') {//decline expert by one of the side
			self::required([
				'id' => true
					], $r);

			Expert::getBy([
				'id'		 => $r['id'],
				'result'	 => 'na',
				'_notfound'	 => true
			])->declineExpert();

			return [
				'User'		 => [
					'updateBalance' => [
						'data' => 'none'
					]
				],
				'Arbitrage'	 => [
					'updateList' => [
						'data' => 'none'
					]
				]
			];
		} elseif ($com == 'expert_disagree') {

			self::required([
				'id' => true
					], $r);

			$expert = Expert::getBy([
						'id'		 => $r['id'],
						'result'	 => 'na',
						'_notfound'	 => true
			]);

			$claim = Claim::getBy([
						'id'		 => $expert->get('claim_id'),
						'_notfound'	 => true
			]);


			$claim->setActions([
				'update_arbitrage_list'
					], $claim->get('user_id'))->setActions([
				'update_arbitrage_list'
					], $claim->get('accepted_by'))->set([
				'seller_hold'	 => $claim->d('seller_hold') + $expert->d('seller_hold'),
				'customer_hold'	 => $claim->d('customer_hold') + $expert->d('customer_hold')
			]);

			$expert->set([
				'seller_hold'	 => 0,
				'customer_hold'	 => 0,
				'status'		 => 'rejected',
				'expert'		 => 'no'
			]);

			//send email notification that disagree
			$emails = [User::getBy([
					'id'		 => $claim->get('user_id'),
					'_notfound'	 => true
				])->get('confirmed_email'),
				User::getBy([
					'id'		 => $claim->get('accepted_by'),
					'_notfound'	 => true
				])->get('confirmed_email')];


			foreach ($emails as $email) {
				if (!empty($email)) {

					Mail::send([
						'to'		 => $email,
						'from_name'	 => 'CrowdEscrow.biz',
						'reply_to'	 => 'admin@crowdescrow.biz',
						'priority'	 => 0,
						'subject'	 => 'CrowdEscrow.biz ' . T::out([
							'decline_expert_offer (in email)' => [
								'en' => 'Expert offer declined',
								'ru' => 'Предложение выступить экспертом отклонено'
							]
						]),
						'html'		 => H::getTemplate('email/expert_invitation', [
							'header'	 => T::out([
								'decline_expert_offer (in email)' => [
									'en' => 'Expert offer declined',
									'ru' => 'Предложение выступить экспертом отклонено'
								]
							]),
							'name'		 => 'CrowdEscrow.biz',
							'message'	 => T::out([
								'sorry_iamnot_interested' => [
									'en'		 => '{{name}} do not want to be an expert in your dispute',
									'ru'		 => '{{name}} не хочет выступать экспертом в вашем споре',
									'_include'	 => [
										'name' => User::getBy([
											'id'		 => $expert->get('user_id'),
											'_notfound'	 => true
										])->get('name')
									]
								]
							]),
							'link'		 => B::setProtocol('https:', B::baseURL() . 'arbitrage')
								], 'addDelimeters')
					]);
				}
			}

			return [
				'Arbitrage' => [
					'updateList' => [
						'data' => 'none'
					]
				]
			];
		} elseif ($com == 'showExpertFeeForm') {

			self::required([
				'id' => true
					], $r);

			$user = User::logged();

			$claim = Claim::getBy([
						'id'			 => self::getBy([
							'id'		 => $r['id'],
							'expert'	 => 'na',
							'seller'	 => 'na',
							'customer'	 => 'na',
							'status'	 => 'waiting',
							'_notfound'	 => true
						])->get('claim_id'),
						'status'		 => 'arbitrage',
						'user_id'		 => '!=' . $user->get('id'),
						'accepted_by'	 => '!=' . $user->get('id'),
						'_notfound'		 => true
			]);

			$max = round(S::getBy([
						'key'		 => 'max_expertise_royalty_percent',
						'_notfound'	 => [
							'key'	 => 'max_expertise_royalty_percent',
							'val'	 => 1
						]
					])->d('val') * $claim->get('amount') / 100, 2);

			return [
				'Arbitrage' => [
					'showExpertFeeForm' => [
						'id'	 => $r['id'],
						'max'	 => $max
					]
				]
			];
		} elseif ($com == 'expert_agree') {

			self::required([
				'id'	 => true,
				'price'	 => true
					], $r);

			$user = User::logged();

			$claim = Claim::getBy([
						'id'		 => Expert::getBy([
							'id'		 => $r['id'],
							'user_id'	 => $user->get('id'),
							'seller'	 => ['ok',
								'na'],
							'customer'	 => ['ok',
								'na'],
							'expert'	 => 'na',
							'result'	 => 'na',
							'_notfound'	 => true
						])->set([
							'expert' => 'ok',
							'price'	 => $r['price']
						])->get('claim_id'),
						'_notfound'	 => true
			]);

			$max = round(S::getBy([
						'key'		 => 'max_expertise_royalty_percent',
						'_notfound'	 => [
							'key'	 => 'max_expertise_royalty_percent',
							'val'	 => 1
						]
					])->d('val') * $claim->get('amount') / 100, 2);

			if ($r['price'] * 1 > $max || $r['price'] * 1 < 0) {
				throw new Exception('Price should be 0 - ' . $max);
			}

			$claim->setActions([
				'update_arbitrage_list'
					], $claim->get('user_id'))->setActions([
				'update_arbitrage_list'
					], $claim->get('accepted_by'));

			//send email notification that disagree
			$emails = [User::getBy([
					'id'		 => $claim->get('user_id'),
					'_notfound'	 => true
				])->get('confirmed_email'),
				User::getBy([
					'id'		 => $claim->get('accepted_by'),
					'_notfound'	 => true
				])->get('confirmed_email')];


			foreach ($emails as $email) {
				if (!empty($email)) {

					Mail::send([
						'to'		 => $email,
						'from_name'	 => 'CrowdEscrow.biz',
						'reply_to'	 => 'admin@crowdescrow.biz',
						'priority'	 => 0,
						'subject'	 => 'CrowdEscrow.biz ' . T::out([
							'accept_expert_offer (in email)' => [
								'en' => 'Expert offer accepted',
								'ru' => 'Предложение выступить экспертом принято'
							]
						]),
						'html'		 => H::getTemplate('email/expert_invitation', [
							'header'	 => T::out([
								'accept_expert_offer (in email)' => [
									'en' => 'Expert offer accepted',
									'ru' => 'Предложение выступить экспертом принято'
								]
							]),
							'name'		 => 'CrowdEscrow.biz',
							'message'	 => T::out([
								'ican_do_this_for' => [
									'en'		 => 'I can do this for {{price}}$. Under CrowdEscrow.biz rules each side should hold this amount. The winner will be refunded.',
									'ru'		 => 'Я согласен это сделать за {{price}}$. В соответствии с правилами CrowdEscrow.biz обе стороны должны заморозить эту сумму. Победителю будет сделан возврат.',
									'_include'	 => [
										'price' => $r['price']
									]
								]
							]),
							'link'		 => B::setProtocol('https:', B::baseURL() . 'arbitrage')
								], 'addDelimeters')
					]);
				}
			}

			//TODO: add section "Make expertise"

			return [
				'Site' => [
					'switchTo' => [
						'page' => 'arbitrage'
					]
				]
			];
		} elseif ($com == 'check_invitation') { //check expert invitation by UserAction call
			$user = User::logged();

			$expert = self::getBy([
						'user_id'	 => $user->get('id'),
						'result'	 => 'na',
						'seller'	 => ['ok',
							'na'],
						'customer'	 => ['ok',
							'na'],
						'expert'	 => 'na'
			]);

			if (empty($expert)) {
				return [
					'no' => 'response'
				];
			}

			$claim = Claim::getBy([
						'id'		 => $expert->get('claim_id'),
						'status'	 => 'arbitrage',
						'_notfound'	 => true
			]);

			//generate dispute details

			$html = $claim->getHTML(H::getTemplate('pages/arbitrage/' . ($claim->get('currency') == '_deal'
									? 'deal'
									: 'balance') . '_claim_short', [], true), 'noexperts');

			//max persent of royalty

			$max = round(S::getBy([
						'key'		 => 'max_expertise_royalty_percent',
						'_notfound'	 => [
							'key'	 => 'max_expertise_royalty_percent',
							'val'	 => 1
						]
					])->d('val') * $claim->get('amount') / 100, 2);

			return [
				'Arbitrage' => [
					'offerToExpert' => [
						'html'		 => $html,
						'max'		 => $max,
						'claim_id'	 => $expert->get('claim_id'),
						'id'		 => $expert->get('id')
					]
				]
			];
		} elseif ($com == 'create_invitation') { //Create public invitation link
			//TODO: this more careful
			self::required([
				'claim_id' => true
					], $r);

			return [
				'link' => Expert::getBy([
					'claim_id'	 => $r['claim_id'],
					'user_id'	 => 'is null',
					'_notfound'	 => [
						'claim_id'	 => $r['claim_id'],
						'user_id'	 => null,
						'invitation' => md5(microtime())
					]
				])->get('link')
			];
		}
	}

	public function getButtonsInLine() {

		if ($this->get('result') != 'na') { //votated
			return '';
		}

		$h = [
			'<div class="pr round_button_host" style="float:right; display:inline-block; margin-top:5px;">
					<div class="pa cp decline_this_expert id_' . $this->get('id') . ' round_button" style="background:tomato; margin:0px;">
						<i class="fa fa-times" style="margin-top:0px; font-size:1.2rem; color:white;"></i>
					</div>
				</div>'];

		$iam = Claim::getBy([
					'id'		 => $this->get('claim_id'),
					'_notfound'	 => true
				])->get('iam');

		if ($this->get($iam) != 'ok' && $this->get('expert') == 'ok') {
			$h[] = '<div class="pr round_button_host" style="float:right; display:inline-block; margin-top:5px;">
					<div class="pa cp accept_this_expert id_' . $this->get('id') . ' round_button" style="background:mediumseagreen; margin:0px;">
						<i class="fa fa-check" style="margin-top:0px; font-size:1.2rem; color:white;"></i>
					</div>
				</div>';
		}

		return join('', $h);
	}

	/**
	 * 
	 * @param type $r  = [
	 * 	'user_id' =>
	 * 	'claim_id' => 
	 *  'throw' => 
	 * ]
	 */
	public static function inviteExpert($r) {

		$expert = self::getBy([
					'user_id'	 => $r['user_id'],
					'claim_id'	 => $r['claim_id'],
					'_return'	 => 'count'
		]);

		if ($expert > 0) { //check if expert has already invited
			if (self::getBy([
						'user_id'	 => $r['user_id'],
						'claim_id'	 => $r['claim_id'],
						'_notfound'	 => true
					])->get('expert') == 'no') {
				throw new Exception(T::out([
					'Expert has declined purpose' => [
						'en' => 'Expert has declined purpose',
						'ru' => 'Эксперт отказался'
					]
				]));
			} else {
				throw new Exception(T::out([
					'Expert already invited' => [
						'en' => 'Expert already invited',
						'ru' => 'Эксперт уже приглашен'
					]
				]));
			}
		}

		return [
			'claim'	 => Claim::getBy([
				'id'			 => $r['claim_id'],
				'status'		 => 'arbitrage',
				'user_id'		 => '!=' . $r['user_id'],
				'accepted_by'	 => '!=' . $r['user_id'],
				'_notfound'		 => true
			]),
			'expert' => self::getBy([
				'id'		 => '_new',
				'_notfound'	 => [
					'user_id'	 => $r['user_id'],
					'claim_id'	 => $r['claim_id'],
					'invitation' => md5(microtime() . 'invitation')
				]
			])
		];
	}

	public function declineExpert($ignoreLimit = false) {

		if (!$this->get('user_id')) {
			return $this;
		}

		$user = User::logged();

		$claim = Claim::getBy([
					'id'		 => $this->get('claim_id'),
					'status'	 => 'arbitrage',
					'_notfound'	 => true
		]);

		$claim->set([
			'customer_hold'	 => $claim->d('customer_hold') + $this->d('customer_hold'),
			'seller_hold'	 => $claim->d('seller_hold') + $this->d('seller_hold')
		]);

		$customer = User::getBy([
					'id'		 => $claim->get('accepted_by'),
					'_notfound'	 => true
		]);


		$seller = User::getBy([
					'id'		 => $claim->get('user_id'),
					'_notfound'	 => true
		]);

		$expert = User::getBy([
					'id'		 => $this->get('user_id'),
					'_notfound'	 => true
		]);


		//email уведомления всем сторонам которые участники об отказе от услуг данного иксперта
		//шлет только если участник принимал условия эксперта

		$emails = [
			$this->get('expert') == 'ok'
					? $expert->get('confirmed_email')
					: false,
			$this->get('seler') == 'ok'
					? $seller->get('confirmed_email')
					: false,
			$this->get('customer') == 'ok'
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
						'decline_expertise' => [
							'en' => 'Waiver of examination',
							'ru' => 'Отказ от экспертизы'
						]
					]),
					'html'		 => H::getTemplate('email/expert_invitation', [
						'header'	 => T::out([
							'decline_expertise' => [
								'en' => 'Waiver of examination',
								'ru' => 'Отказ от экспертизы'
							]
						]),
						'name'		 => 'CrowdEscrow.biz',
						'message'	 => T::out([
							'one_of_the_side_has_declined_expertise_name_reason' => [
								'en'		 => 'One of the dispute sides has declined the expert {{name}}{{reason}}',
								'ru'		 => 'Одна из сторон спора дала отвод эксперту {{name}}{{reason}}',
								'_include'	 => [
									'name'	 => User::getBy([
										'id'		 => $this->get('user_id'),
										'_notfound'	 => true
									])->get('name'),
									'reason' => empty($ignoreLimit)
											? ''
											: $ignoreLimit
								]
							]
						]),
						'link'		 => B::setProtocol('https:', B::baseURL() . 'arbitarge')
							], 'addDelimeters')
				]);
			}
		}

		//удаленное обновление страниц с arbitrage

		if ($this->get('expert') == 'ok' && $user->get('id') != $this->get('user_id')) {
			$claim->setActions([
				'update_arbitrage_list'
					], $this->get('user_id'));
		}


		if ($claim->get('user_id') != $user->get('id') && $this->get('seller') != 'no') {
			$claim->setActions([
				'update_arbitrage_list'
					], $seller->get('id'));
		}


		if ($claim->get('accepted_by') != $user->get('id') && $this->get('customer') != 'no') {
			$claim->setActions([
				'update_arbitrage_list'
					], $customer->get('id'));
		}

		if (empty($ignoreLimit) && $this->get('expert') == 'ok' && $this->get($claim->get('notme')) == 'ok') {

			if ($this->d($claim->get('iam') . '_declines') == 0 && false) {
				throw new Exception(T::out([
					'decline_count_limited' => [
						'en' => 'Under CrowdEscrow.biz rules the possibility to decline experts offered by counterparty is limited. Invite expert by yourself to increase this limit.',
						'ru' => 'В соответствии с правилами CrowdEscrow.biz отказаться от предложенных экспертов можно ограниченное число раз. Пригласите эксперта самостоятельно, чтобы увеличить этот лимит.'
					]
				]));
			} else {

				return $this->set([
							'customer'						 => 'no',
							'seller'						 => 'no',
							'customer_hold'					 => 0,
							'seller_hold'					 => 0,
							'status'						 => 'rejected',
							$claim->get('iam') . '_declines' => max($this->d($claim->get('iam') . '_declines') - 1, 0)
				]);
			}
		} else {

			return $this->set([
						'customer'		 => 'no',
						'seller'		 => 'no',
						'customer_hold'	 => 0,
						'status'		 => 'rejected',
						'seller_hold'	 => 0
			]);
		}
	}

	/**
	 * Remove all experts records for selected claim_id
	 * @param type $claim_id
	 */
	public static function removeAllExperts($claim_id) {

		$experts = Expert::getBy([
					'claim_id'	 => $claim_id,
					'_return'	 => [0 => 'object']
		]);

		foreach ($experts as $expert) {

			if ($expert->get('user_id') && $expert->get('expert') != 'no') {
				$expert->declineExpert(T::out([
							'because the parties have reached agreement' => [
								'en' => ', because the parties have reached agreement',
								'ru' => ', потому что стороны достигли согласия'
							]
				]));
			}

			$expert->remove();
		}

		return Offer::getBy([
					'id' => $claim_id
		]);
	}

	public function getClaimStatus($claim = null) {

		if (empty($claim)) {
			$claim = Claim::getBy([
						'id' => $this->get('claim_id')
			]);
		}

		if ($this->get('result') != 'na') {
			return T::out([
						'you_have_already_vote' => [
							'en' => 'You have already vote',
							'ru' => 'Вы уже проголосовали'
						]
			]);
		}

		if ($claim->get('status') == 'arbitrage' && $this->get('expert') == 'ok' && $this->get('seller') == 'ok' && $this->get('customer') == 'ok') {

			return T::out([
						'please_check_claim_and_decide_who_is_write' => [
							'en' => 'Who do you think is right in a dispute?',
							'ru' => 'Кто по вашему мнению прав в споре?'
						]
			]);
		} elseif ($claim->get('status') == 'arbitrage' && $this->get('expert') == 'ok' && ($this->get('seller') == 'na' || $this->get('customer') == 'na')) {

			return T::out([
						'wait_side_decision' => [
							'en' => 'The parties have not yet accepted your offer',
							'ru' => 'Стороны пока не приняли ваше предложение'
						]
			]);
		} elseif ($claim->get('status') == 'arbitrage' && $this->get('expert') == 'na' && $this->get('seller') == 'na' && $this->get('customer') == 'na') {

			return T::out([
						'wait_for_your_decision' => [
							'en' => 'Would you like to act as an expert in this dispute?',
							'ru' => 'Хотите ли Вы быть экспертом в этом споре?'
						]
			]);
		} else {

			return T::out([
						'sides_declined_your_offer' => [
							'en' => 'The parties has declined your expertise',
							'ru' => 'Стороны отказались от вашей экспертизы'
						]
			]);
		}
	}

	public function checkVotationCompleted() {

		$claim = Claim::getBy([
					'id' => $this->get('claim_id')
		]);

		$total_votes = self::getBy([
					'claim_id'	 => $claim->get('id'),
					'expert'	 => 'ok',
					'seller'	 => 'ok',
					'customer'	 => 'ok',
					'result'	 => ['seller',
						'customer'],
					'_return'	 => 'count'
		]);

		$minimum_votes = S::getBy([
					'key'		 => 'minimum_necessary_votes',
					'_notfound'	 => [
						'key'	 => 'minimum_necessary_votes',
						'val'	 => 4
					]
				])->d('val');

		$decision_limit = S::getBy([
					'key'		 => 'decision_limit',
					'_notfound'	 => [
						'key'	 => 'decision_limit',
						'val'	 => 0.6
					]
				])->d('val');



		$seller_votes = self::getBy([
					'claim_id'	 => $claim->get('id'),
					'expert'	 => 'ok',
					'seller'	 => 'ok',
					'customer'	 => 'ok',
					'result'	 => 'seller',
					'_return'	 => 'count'
		]);


		if ($total_votes >= $minimum_votes || ($total_votes >= 3 && ($seller_votes === 0 || $seller_votes === $total_votes ))) {


			if ($seller_votes / $total_votes >= $decision_limit) { //seller win
				//список проголосовавших экспертов
				$votedExperts = self::getBy([
							'claim_id'	 => $claim->get('id'),
							'customer'	 => 'ok',
							'seller'	 => 'ok',
							'expert'	 => 'ok',
							'result'	 => ['seller',
								'customer'],
							'_return'	 => [
								0 => 'object'
							]
				]);

				if (!empty($votedExperts)) {
					foreach ($votedExperts as $expert) {

						//return seller money to claim balance
						$claim = $claim->inc([
							'seller_hold' => $expert->d('seller_hold')
						]);

						$user = User::getBy([
									'id'		 => $expert->get('user_id'),
									'_notfound'	 => true
								])->inc([
							'money' => $expert->d('customer_hold')
						]);

						//TODO: update Action for expert
						//zero votation balance
						$expert->set([
							'seller_hold'	 => 0,
							'customer_hold'	 => 0,
							'status'		 => 'completed'
						]);
					}
				}

				//return money for all other experts
				$otherExperts = self::getBy([
							'claim_id'	 => $claim->get('id'),
							'result'	 => 'na',
							'_return'	 => [
								0 => 'object'
							]
				]);

				if (!empty($otherExperts)) {
					foreach ($otherExperts as $expert) {
						$expert->declineExpert(T::out([
									'because the decision has already been made' => [
										'en' => ', because the decision has already been made',
										'ru' => ', потому что решение уже вынесено'
									]
						]));
					}
				}


				if ($claim->get('currency') == '_deal') { //for deal return money to customer (user_id)
					Deal::getBy([
								'id' => $claim->get('id')
							])->returnArbitrageDeposit($claim->get('user_id'))
							->rateSeller('failure')
							->returnFunding($claim->get('user_id'))
							->setActions([
								'update_deals_list',
								'update_balance'], [
								$claim->get('user_id'),
								$claim->get('accepted_by')
							])
							->sendEmailNotification('voted_customer', [
								$claim->get('user_id') => $claim->get('accepted_by')
							])
							->sendEmailNotification('voted_customer', [
								$claim->get('accepted_by') => $claim->get('user_id')
							])
							->set([
								'status' => 'deleted'
					]);
				} else {
					$claim->cancelByBuyer('seller_right');
				}

				return $this;
			} elseif ((1 - $seller_votes) / $total_votes >= $decision_limit) { //customer win
				//список проголосовавших экспертов
				$votedExperts = self::getBy([
							'claim_id'	 => $claim->get('id'),
							'customer'	 => 'ok',
							'seller'	 => 'ok',
							'expert'	 => 'ok',
							'result'	 => ['seller',
								'customer'],
							'_return'	 => [
								0 => 'object'
							]
				]);

				if (!empty($votedExperts)) {

					$actionsForExperts = [];
					$expertsEmails = [];

					foreach ($votedExperts as $expert) {

						//return seller money to claim balance
						$claim = $claim->inc([
							'customer_hold' => $expert->d('customer_hold')
						]);

						$expertsEmails[] = User::getBy([
									'id'		 => $expert->get('user_id'),
									'_notfound'	 => true
								])->inc([
									'money' => $expert->d('seller_hold')
								])->get('confirmed_email');


						//zero votation balance
						$actionsForExperts[] = $expert->set([
									'seller_hold'	 => 0,
									'customer_hold'	 => 0,
									'status'		 => 'completed'
								])->get('user_id');
					}

					if (!empty($actionsForExperts)) {
						$claim->setAction([
							'update_arbitrage_list',
							'update_balance'
								], $actionsForExperts);
					}

					foreach ($expertsEmails as $email) {
						if (!empty($email)) {
							Mail::send([
								'to'		 => $email,
								'from_name'	 => 'CrowdEscrow.biz',
								'reply_to'	 => 'admin@crowdescrow.biz',
								'priority'	 => 0,
								'subject'	 => 'CrowdEscrow.biz ' . T::out([
									'thank_you_for_expertise' => [
										'en' => 'Thank you for you vote',
										'ru' => 'Спасибо за ваш голос'
									]
								]),
								'html'		 => H::getTemplate('email/expert_invitation', [
									'header'	 => T::out([
										'thank_you_for_expertise' => [
											'en' => 'Thank you for you vote',
											'ru' => 'Спасибо за ваш голос'
										]
									]),
									'name'		 => 'CrowdEscrow.biz',
									'message'	 => T::out([
										'your_vote_accounted_get_money (in email)' => [
											'en'		 => 'The decision concerning a disputed deal "{{deal}}" has been made, the reward is credited to your balance in the system.',
											'ru'		 => 'Решение в рамках спора по сделке «{{deal}}» вынесено, вознаграждение зачислено на ваш баланс в системе.',
											'_include'	 => [
												'deal' => $claim->getShortDescription()
											]
										]
									]),
									'link'		 => B::setProtocol('https:', B::baseURL())
										], 'addDelimeters')
							]);
						}
					}
				}

				//return money for all other experts
				$otherExperts = self::getBy([
							'claim_id'	 => $claim->get('id'),
							'result'	 => 'na',
							'_return'	 => [
								0 => 'object'
							]
				]);

				if (!empty($otherExperts)) {
					foreach ($otherExperts as $expert) {
						$expert->declineExpert(T::out([
									'because the decision has already been made' => [
										'en' => ', because the decision has already been made',
										'ru' => ', потому что решение уже вынесено'
									]
						]));
					}
				}

				if ($claim->get('currency') == '_deal') { //for deal return money to seller (accepted_by)
					Deal::getBy([
								'id' => $claim->get('id')
							])->returnArbitrageDeposit($claim->get('accepted_by'))
							->rateSeller('success')
							->returnFunding($claim->get('accepted_by'))
							->setActions([
								'update_deals_list',
								'update_balance'], [
								$claim->get('user_id'),
								$claim->get('accepted_by')
							])
							->sendEmailNotification('voted_seller', [
								$claim->get('user_id') => $claim->get('accepted_by')
							])
							->sendEmailNotification('voted_seller', [
								$claim->get('accepted_by') => $claim->get('user_id')
							])
							->set([
								'status' => 'deleted'
					]);
				} else {
					$claim->releaseBySeller('customer_right');
				}

				return $this;
			}
		}

		//no decision has been made

		$claim->setActions([
			'update_arbitrage_list'
				], [
			'seller',
			'customer'
		]);

		return $this;
	}

	public function getVoteButtons($who, $claim = null) {
		if (empty($claim)) {
			$claim = Claim::getBy([
						'id' => $this->get('claim_id')
			]);
		}

		if ($claim->get('status') == 'arbitrage' &&
				$this->get('expert') == 'ok' &&
				$this->get('seller') == 'ok' &&
				$this->get('customer') == 'ok' &&
				$this->get('result') == 'na') {


			return '<div class="pr round_button_host" style="float:right; display:inline-block; margin-top:5px;">
					<div class="pa cp vote_dispute for_' . $who . ' id_' . $this->get('id') . ' round_button ' .
					($claim->get('currency') == '_deal'
							? 'isDeal'
							: '') . '" style="background:' . ($who == 'seller'
							? 'royalblue'
							: 'dodgerblue') . '; margin:0px;">
						<i class="fa ' . ($who == 'seller'
							? 'fa-arrow-left'
							: 'fa-arrow-right') . '" style="margin-top:0px; font-size:1.2rem; color:white;"></i>
					</div>
				</div>';
		} else {
			return '';
		}
	}

	public static function getClaimsOnExpertise() {

		$experts = self::getBy([
					'user_id'	 => User::logged()->get('id'),
					'expert'	 => ['ok',
						'na'],
					'seller'	 => ['ok',
						'na'],
					'customer'	 => ['ok',
						'na'],
					'status'	 => ['waiting',
						'processed'],
					'_return'	 => [
						0 => 'object'
					]
		]);

		$h = [];


		$template = [
			'balance'	 => H::getTemplate('pages/arbitrage/balance_claim', [], true),
			'deal'		 => H::getTemplate('pages/arbitrage/deal_claim', [], true)
		];

		foreach ($experts as $expert) {

			$claim = Claim::getBy([
						'id'	 => $expert->get('claim_id'),
						'status' => 'arbitrage'
			]);

			if (!empty($claim)) {

				$h[] = $claim->getHTML($template[$claim->get('currency') == '_deal'
								? 'deal'
								: 'balance'], $expert->get('id'));
			}
		}

		if (!empty($h)) {
			$h[] = H::getTemplate('pages/dialogs/inline_help', [
						'style'		 => 'padding:10px;',
						'content'	 => H::getTemplate('pages/arbitrage/expert_inline_help', [], true)
							], true);
		}

		return join('', $h);
	}

	public function get($what, $data = null) {

		if ($what == 'link') {
			return B::setProtocol('https:', B::baseURL() . 'expert_public_invite/' . $this->get('invitation'));
		}

		return parent::get($what, $data);
	}

	public static function f() {
		return [
			'title'		 => 'Expert',
			'datatype'	 => [
				'user_id'	 => [
					'User' => [
						'id' => ' ON DELETE CASCADE '
					]
				],
				'claim_id'	 => [
					'Offer' => [
						'id' => ' ON DELETE CASCADE '
					]
				]
			],
			'create'	 => [
				'invitation'	 => "tinytext default null comment 'Invitation code'",
				'user_id'		 => "bigint unsigned default null comment 'User'",
				'claim_id'		 => "bigint unsigned default null comment 'Offer'",
				'customer'		 => "enum('ok','no','na') default 'na' comment 'Customer position'",
				'seller'		 => "enum('ok','no','na') default 'na' comment 'Seller position'",
				'expert'		 => "enum('ok','no','na') default 'na' comment 'Expert position'",
				'result'		 => "enum('customer','seller','na') default 'na' comment 'Expert oppinion'",
				'price'			 => "float default 0 comment 'Expert royalty'",
				'customer_hold'	 => "float default 0 comment 'Holded amount'",
				'seller_hold'	 => "float default 0 comment 'Holded amount'",
				'status'		 => "enum('waiting','processed','completed', 'rejected') default 'waiting' comment 'Expert status wait/process/completed'"
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
