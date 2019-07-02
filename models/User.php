<?php

/**
 * User on the field (projection of U into the game
 */
class User extends U {

	public static function tryToConfirmEmail() {
		if (!empty($_REQUEST['email_confirm'])) {
			$_SESSION['email_confirm'] = $_REQUEST['email_confirm'];
			header('Location: https://crowdescrow.biz'); //change location if token expired
			exit;
		}

		$result = [
			'beforeLogin'	 => false,
			'afterLogin'	 => false
		];

		if (isset($_SESSION['email_confirm'])) {

			$user = $_SESSION['email_confirm'] == 'ok'
					? false
					: self::getBy([
						'confirmed'	 => $_SESSION['email_confirm'],
						'_return'	 => 'count'
			]);

			if (!empty($user)) {
				$result['afterLogin'] = "A.w(['B'], function() { B.post({User: 'confirm_email'});});";
			} else {
				unset($_SESSION['email_confirm']);
			}

			if (isset($_SESSION['email_confirm']) && !User::isLogged()) {
				$result['beforeLogin'] = "D.show({ title: '" . T::out([
							'enger_to_confirm_email (get_ref)' => [
								'en' => 'Please sign in to confirm email',
								'ru' => 'Пожалуйста войдите чтобы подтвердить email'
							]
						]) . "', message: false, waitWhile : true, css: D.getCSS({type: 'norm'})});";
			}
		}

		return $result;
	}

	public static function logged() {

		$user = self::getBy();

		if (empty($user)) {
			/* M::ok([
			  'U' => [
			  'login' => []
			  ]
			  ]); */
			M::ok([
				'User' => [
					'reload' => [
						'data' => true
					]
				]
			]);
		}

		return $user;
	}

	public static function isLogged() {
		$user = self::getBy();
		if (empty($user)) {
			return false;
		} else {
			return true;
		}
	}

	public static function action($r, $dontdie = false) {

		$com = empty($r[get_called_class()])
				? false
				: $r[get_called_class()];

		if (!empty($r['login'])) {
			$r['login'] = strtolower($r['login']);
		}

		if (!empty($r['email'])) {
			$r['email'] = strtolower($r['email']);
		}

		if ($com == 'isOnline') {

			$user = self::getBy();

			if (empty($user)) {
				return [
					'O'		 => [
						'stop' => []
					],
					//reload page if lost login session - rude but effective
					'User'	 => [
						'reload' => []
					]
						/*
						 * U.login - will soft variant - will show login dialog
						 */
				];
			} else {

				//!!! не могу напрямую $user потому что тогда YII пытается insert

				self::getBy([
					'id' => $user->get('id')
				])->set([
					'online' => time()
				]);

				//here we check which is necessary to Run
				$userActions = UserAction::getBy([
							'user_id'	 => $user->get('id'),
							'_return'	 => [0 => 'object']
				]);

				$actions = [
					'O' => [
						'reStart'	 => [],
						'ping'		 => [],
						'set'		 => [
							'frequency' => S::getBy([
								'key'		 => 'Frequency of checking if user is online',
								'_notfound'	 => [
									'key'	 => 'Frequency of checking if user is online',
									'val'	 => '7000'
								]
							])->get('val')
						],
					]
				];

				if (!empty($userActions)) {
					foreach ($userActions as $userAction) {

						$a = Action::getBy([
									'id'		 => $userAction->get('action_id'),
									'_notfound'	 => true
						]);

						$actions = array_merge($actions, $a->get('decodeAction'));

						if ($a->get('remove') == 'auto') {
							$userAction->remove();
						}
					}
				}

				return $actions;
			}
		} elseif ($com === 'view') {//view profile
			self::required([
				'user_id' => true
					], $r);

			$userB = User::getBy([
						'id' => $r['user_id']
			]);

			$isParticipant = false;

			if (isset($r['offer_id'])) {

				$user = self::getBy();

				$offer = Offer::getBy([
							'id'		 => $r['offer_id'],
							'_notfound'	 => true
				]);

				if (in_array($userB->get('id'), [
							$offer->get('user_id'),
							$offer->get('accepted_by')]) &&
						in_array($user->get('id'), [
							$offer->get('user_id'),
							$offer->get('accepted_by')])) {
					$isParticipant = true;
				}

				if ($offer->get('status') == 'arbitrage' && Expert::getBy([
							'user_id'	 => $user->get('id'),
							'claim_id'	 => $offer->get('id'),
							'_return'	 => 'count'
						]) > 0) {
					$isParticipant = true;
				}
			}

			return [
				'User' => [
					'showUserProfile' =>
					[
						'name'	 => $userB->get('name'),
						'html'	 => H::getTemplate('pages/user/view', [
							'photo'			 => $userB->get('photo')
									? $userB->get('photo')
									: 'images/user_logo.png',
							'name'			 => $userB->get('name'),
							'onlyDeals'		 => isset($r['heIs'])
									? 'display:none;'
									: 'display:inline-block;',
							'onlyBalance'	 => isset($r['heIs']) && $r['heIs'] == 'seller'
									? 'display:inline-block;'
									: 'display:none;',
							'rating'		 => '<span style="color:' . $userB->get('ratingColor') . ';">' . $userB->d('rating') . '%</span>',
							'total'			 => '<span style="color:white;">' . $userB->d('success') . '$</span>',
							'balance_rating' => '<span style="color:' . $userB->get('balanceRatingColor') . ';">' . $userB->d('balance_rating') . '%</span>',
							'balance_total'	 => '<span style="color:white;">' . $userB->d('balance_success') . '$</span>',
							'email'			 => $userB->get('confirmed_email'),
							'forExperts'	 => empty($isParticipant)
									? 'display:none;'
									: '',
							'screenshots'	 => empty($isParticipant)
									? ''
									: join('', $userB->uploadedFilesHTML())
								], true)
					]
				]
			];
		} elseif ($com === 'set_email') {

			self::required([
				'email' => true
					], $r);

			$email = filter_var(trim($r['email']), FILTER_VALIDATE_EMAIL);

			$user = self::getBy([
						'id' => self::logged()->get('id'),
			]);


			if (empty($email)) {
				M::ok([
					'User' => [
						'error' => [
							'message'	 => T::out([
								'email_expected_WER2' => [
									'en'		 => 'Email address expected instead {{what}}',
									'ru'		 => 'Ожидается адрес электронной почты вместо {{what}}',
									'_include'	 => [
										'what' => htmlspecialchars($r['email'])
									]
								]
							]),
							'callback'	 => [
								'setConfirmed' => [
									'sent_to_email' => $user->get('email')
								]
							]
						]
					]
				]);
			}

			if ($user->get('confirmed_email') == $email) {
				throw new Exception(T::out([
					'email_already_confirmed' => [
						'en' => 'Email already confirmed',
						'ru' => 'Email уже подтвержден'
					]
				]));
			}

			$code = md5(microtime() . 'salt_ypvjh');

			$user->set([
				'email' => $email
			])->set([
				'confirmed' => $code
			])->sendEmailConfirmation($code)->cash();

			//send the same code to another email

			return [
				'User' => [
					'setConfirmed' => [
						'sent_to_email' => $email
					]
				]
			];
		} elseif ($com == 'search') {
			/*
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

			  $user = User::logged();

			  $restricted[] = $offer->get('user_id') == $user->get('id')
			  ? $offer->get('accepted_by')
			  : $offer->get('user_id');

			  //add as restricted users which was declined by contractor or expert

			  $declinedExperts = Expert::getBy([
			  'claim_id'	 => $r['claim_id'],
			  '_or'		 => [
			  'expert'	 => 'no',
			  'seller'	 => 'no',
			  'customer'	 => 'no'
			  ],
			  '_return'	 => [
			  'user_id' => 'object'
			  ]
			  ]);

			  if (!empty($declinedExperts)) {
			  $restricted = array_merge($restricted, array_keys($declinedExperts));
			  }

			  $invitedExperts = Expert::getBy([
			  'claim_id'	 => $r['claim_id'],
			  'user_id' => '>>0',
			  'customer' => ['ok','na'],
			  'expert' => ['ok','na'],
			  'seller'	 => ['ok','na'],
			  '_return'	 => [
			  'user_id' => 'object'
			  ]
			  ]);

			  if (!empty($invitedExperts)) {
			  $marked = array_keys($invitedExperts);
			  }
			  }

			  return self::findUsers($r['what'], $r['page'], $restricted, $marked);

			 */
		} elseif ($com == 'set') {

			$user = self::getBy([
						'id' => self::logged()->get('id')
			]);

			if (!empty($r['email'])) {

				$email = filter_var(trim($r['email']), FILTER_VALIDATE_EMAIL);

				if (empty($email)) {
					throw new Exception(T::out([
						'email_expected_WER2' => [
							'en'		 => 'Email address expected instead {{what}}',
							'ru'		 => 'Ожидается адрес электронной почты вместо {{what}}',
							'_include'	 => [
								'what' => $r['email']
							]
						]
					]));
				}

				if ($user->get('confirmed_email') == $email) {
					throw new Exception(T::out([
						'email_already_confirmed' => [
							'en' => 'Email already confirmed',
							'ru' => 'Email уже подтвержден'
						]
					]));
				}

				$code = md5(microtime() . 'salt_ypvjh');

				$user->set([
					'email'		 => $email,
					'confirmed'	 => $code
				])->sendEmailConfirmation($code)->cash();

				//send the same code to another email

				return [
					'User' => [
						'setConfirmed'	 => [
							'sent_to_email' => $email
						],
						'profileInit'	 => [
							'name'	 => $user->fget('name'),
							'email'	 => $user->fget('email'),
							'files'	 => join('', $user->uploadedFilesHTML())
						]
					]
				];
			} elseif (!empty($r['name'])) {
				$user = $user->set([
					'name' => $r['name']
				]);
			} elseif (!empty($r['pass'])) {

				$md5 = S::getBy([
							'key'		 => 'md5 pass',
							'_notfound'	 => [
								'key'		 => 'md5 pass',
								'val'		 => 1,
								'comment'	 => 'Store pass as md5 (0,1)'
							]
						])->d('val');

				$user = $user->set([
					'pass' => empty($md5)
							? $r['pass']
							: md5($r['pass'] . $md5)
				]);

				return [
					'User'	 => [
						'cleanNewPass' => [
							'data' => 'none'
						]
					],
					'D'		 => [
						'show' => [
							'title'	 => T::out([
								'your_pass_changed' => [
									'en' => 'Your pass has been changed successfully!',
									'ru' => 'Ваш пароль был успешно изменен!'
								]
							]),
							'css'	 => [
								'A' => [
									'getCss' => [
										'type' => 'ok'
									]
								]
							]
						]
					]
				];
			}

			return [
				'User' => [
					'profileInit' => [
						'id'	 => $user->get('id'),
						'name'	 => $user->fget('name'),
						'email'	 => $user->fget('email'),
						'files'	 => join('', $user->uploadedFilesHTML())
					]
				]
			];
		} elseif ($com === 'profile') {

			$user = self::getBy([
						'id' => self::logged()->get('id')
			]);

			return [
				'User' => [
					'profileInit' => [
						'id'	 => $user->get('id'),
						'name'	 => $user->fget('name'),
						'email'	 => $user->fget('email'),
						'files'	 => join('', $user->uploadedFilesHTML())
					]
				]
			];
		} elseif ($com === 'confirm_email') {

			if (empty($_SESSION['email_confirm'])) {
				self::required([
					'code' => true
						], $r);
			} else {
				$r['code'] = $_SESSION['email_confirm'];
				unset($_SESSION['email_confirm']);
			}

			$user = self::getBy([
						'id'		 => self::logged()->get('id'),
						'confirmed'	 => $r['code'] !== 'ok'
								? $r['code']
								: '_never'
			]);

			if (!empty($user)) {

				return [
					'D'		 => [
						'show' => [
							'title'	 => T::out([
								'email_confirmed (onstart)' => [
									'en'		 => 'Email "{{email}}" confirmed for notifications.',
									'ru'		 => 'Электронная почта «{{email}}» подтверждена для уведомлений.',
									'_include'	 => [
										'email' => $user->set([
											'confirmed' => 'ok'
										])->get('confirmed_email')
									]
								]
							]),
							'css'	 => [
								'A' => [
									'getCss' => [
										'type' => 'ok'
									]
								]
							]
						]
					],
					'Site'	 => [
						'switchTo' => [
							'page' => 'user'
						]
					],
					'User'	 => [
						'markEmailAsConfirmed' => [
							'data' => 'none'
						]
					]
				];
			} else {

				return empty($r['error'])
						? [
					'email' => 'notconfirmed'
						]
						: [
					'D'		 => [
						'show' => [
							'title'	 => T::out([
								'confirm_email (error)' => [
									'en' => 'Wrong or expired confirmation code',
									'ru' => 'Неправильный или устаревший код подтверждения'
								]
							]),
							'css'	 => [
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
							'page' => 'user'
						]
					]
				];
			}
		} elseif ($com === 'check_confirmed') {

			$user = self::getBy([
						'id' => self::logged()->get('id')
			]);

			$confirmed_email = $user->get('confirmed_email');

			if ($confirmed_email) { //email present
				return [
					'User' => [
						'setConfirmed' => [
							'confirmed_email' => $confirmed_email
						]
					]
				];
			} elseif ($user->get('email') && (!$user->get('confirmed') || $user->get('confirmed') == '0')) { //need to send
				$code = md5(microtime() . 'salt_ypvjh');

				$user->set([
					'confirmed' => $code
				])->sendEmailConfirmation($code);

				//return confirmation dialog
				return [
					'User' => [
						'setConfirmed' => [
							'sent_to_email' => $user->get('email')
						]
					]
				];
			} elseif ($user->get('email') && $user->get('confirmed')) { //already sent
				return [
					'User' => [
						'setConfirmed' => [
							'sent_to_email' => $user->get('email')
						]
					]
				];
			} elseif (!$user->get('email')) {

				return [
					'User' => [
						'setConfirmed' => [
							'need_email' => $user->get('email')
						]
					]
				];
			}
		} elseif ($com == 'recall') {

			return parent::action($r);
		} elseif ($com === 'delete_uploaded') {

			self::required([
				'image_id' => true
					], $r);

			$user = self::getBy([
						'id' => self::logged()->get('id')
			]);

			F::removeSimilar($user->get('folder'), $r['image_id']);

			if ($user->custom_photo === $r['image_id'] . '') { //если удалили изображение, которое аватарка, то перегружаем аватарку
				return [
					'User' => [
						'onDelete'		 => [
							'id' => $user->get('id')
						],
						'changeAvatar'	 => [
							'image' => $user->set([
								'custom_photo' => ''
							])->cash()->get('photo')
						]
					]
				];
			} else {

				return [
					'User' => [
						'onDelete' => [
							'id' => $user->get('id')
						]
					]
				];
			}
		} elseif ($com === 'update_balance') {
			$user = User::getBy([
						'id' => User::logged()->get('id')
					])->cash();

			return [
				'User' => [
					'showBalance' => [
						'balance' => $user->get('money')
					]
				]
			];
		} elseif ($com === 'choose_avatar') {
			return [
				'User' => [
					'chooseAvatar' => [
						'files' => join('', User::getBy([
									'id' => self::logged()->get('id')
								])->uploadedFilesHTML())
					]
				]
			];
		} elseif ($com === 'set_avatar') {

			self::required([
				'image_id' => true
					], $r);

			$user = User::getBy([
						'id' => User::logged()->get('id')
			]);

			return [
				'User' => [
					'changeAvatar' => [
						'image' => $user->set([
							'custom_photo' => $r['image_id']
						])->cash()->get('photo')
					]
				]
			];
		} elseif ($com === 'no_avatar') {

			$user = User::getBy([
						'id' => self::logged()->get('id')
			]);

			return [
				'User' => [
					'changeAvatar' => [
						'image' => $user->set(strpos($user->get('photo'), 'images/User') !== false
										? [
									'custom_photo'	 => '',
									'photo'			 => ''
										]
										: [
									'custom_photo' => ''
								])->cash()->get('photo')
					]
				]
			];
		} elseif ($com === 'check_email') {
			$user = self::logged();
		} elseif ($com === 'upload') {
			self::upload($r, 'multi');
		}

		return parent::action($r, $dontdie);
	}
	

	public function uploadRestricted($image_id = null, $noexception = false){
		
		if (empty($image_id) && $image_id !== 0){
				
			$maxUserFiles = S::getBy([
				'key' => 'Max files uploaded in user profile',
				'_notfound' => [
					'key' => 'Max files uploaded in user profile',
					'val' => 11
				]
			])->d('val');
			
			if (F::countFilesInFolder($this->get('folder')) < $maxUserFiles){
				return $this;
			}
			
			throw new Exception(T::out([
				'max_files_11' => [
					'en' => 'Only {{num}} files can be loaded!',
					'ru' => 'Только {{num}} файлов может быть загружено!',
					'_include' => [
						'num' => $maxUserFiles
					]
				]
			]));
		} else {
			return $this;
		}
		
	}
	
	
	public static function uploadSuccess($r, $obj) {

		if (!isset($r['image'])) { //reshow list
			if (!empty($r['raw'])) {
				M::jsonp([
					'parent.A.run' => [
						'Site' => [
							'onUpload' => [
								'html' => join('', $obj->uploadedFilesHTML())
							]
						]
					]
				]);
			} else {

				M::jsonp([
					'parent.A.run' => empty($r['chargeback'])
							? [
						'User' => [
							'outputUploadedFiles' => [
								'html' => join('', $obj->uploadedFilesHTML())
							]
						]
							]
							: [
						'Balance' => [
							'chargeBackUploadedFiles' => [
								'html'		 => join('', $obj->uploadedFilesHTML()),
								'offer_id'	 => $r['chargeback']
							]
						]
							]
				]);
			}
		} else {//reshow current image
			$files = $obj->get('files');
			$file_changed = filectime('./' . $files[$r['image'] * 1]);
			$rnd = '?s=' . $file_changed;


			if (!empty($r['raw'])) {
				M::jsonp([
					'parent.A.run' => [
						'Site' => [
							'onUpload' => [
								'id'		 => $obj->get('id'),
								'image'		 => $files[$r['image'] * 1],
								'image_id'	 => $r['image'],
								'html'		 => join('', $obj->uploadedFilesHTML())
							]
						]
					]
				]);
			} else {

				M::jsonp([
					'parent.A.run' => empty($r['chargeback'])
							? [
						'User' => [
							'showImage'		 =>
							[
								'obj'		 => get_called_class(),
								'image'		 => $files[$r['image'] * 1],
								'id'		 => $obj->get('id'),
								'image_id'	 => $r['image']
							],
							'changeAvatar'	 => [ //immediatelly change avatar view
								'image' => $files[$r['image'] * 1] . $rnd
							]
						]
							]
							: [
						'Balance' => [
							'chargeBackShowImage' => [
								'obj'		 => get_called_class(),
								'image'		 => $files[$r['image'] * 1],
								'id'		 => $obj->get('id'),
								'image_id'	 => $r['image'],
								'get'		 => [
									'chargeback' => $r['chargeback']
								]
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

	public function getConfirmURL() {
		return B::setProtocol('https:', B::baseURL()) . '?email_confirm=' . $this->get('confirmed');
	}

	public function getRecallTemplate() {
		return 'email/recall';
	}

	public function getAccessURL() {
		return B::setProtocol('https:', B::baseURL()) . '?token=' . $this->getAccessToken();
	}

	public function sendEmailConfirmation($code) {

		Mail::send([
			'to'		 => $this->get('email'),
			'from_name'	 => 'CrowdEscrow.biz',
			'reply_to'	 => 'admin@crowdescrow.biz',
			'priority'	 => 0,
			'subject'	 => T::out([
				'email_confirmation_subject' => [
					'en' => 'Email confirmation on CrowdEscrow.biz',
					'ru' => 'Подтверждение адреса электронной почты на CrowdEscrow.biz'
				]
			]),
			'html'		 => H::getTemplate('email/confirmation', [
				'name'	 => 'CrowdEscrow.biz',
				'code'	 => $code,
				'link'	 => $this->getConfirmURL()
					], 'addDelimeters')
		]);

		return $this;
	}

	public static function confirmEmail($code) { //deprecated
		$user = User::getBy([
					'confirmed'	 => $code,
					'id'		 => User::logged()->get('id')
		]);

		return empty($user)
				? false
				: $user->set([
					'confirmed' => 'ok'
		]);
	}

	public static function findUsers($what, $page = 0, $restricted = [], $marked = [], $rating = false) {

		$restricted = array_merge($restricted, [User::logged()->get('id')]);

		if (strpos($what, '@') !== false) {
			$findBy = "
				AND (
					`login` like :what 
					OR
					`email` like :what
				)
				";
		} else { //by name
			$findBy = "
				AND (
					concat(`first_name`,' ',`last_name`) like :what
					OR 
					`name` like :what
					OR 
					`email` like :what
					OR
					`login` like :what
				)
				";
		}

		//pagination

		$size = S::getBy([
					'key'		 => 'pagination_screen',
					'_notfound'	 => [
						'key'	 => 'pagination_screen',
						'val'	 => 3
					]
				])->d('val');

		$start = $page * $size;

		$db = Yii::app()->db->createCommand("
				SELECT	count(*) as `count`
				FROM `escrow_user`
				WHERE `id` not in (" . join(',', $restricted) . ")
				" . $findBy)->query([
			'what' => '%' . $what . '%'
		]);

		while (($row = $db->read()) != false) {
			$count = $row['count'];
		}

		if ($count > 0) { //try with filter via email
			$db = Yii::app()->db->createCommand("
				SELECT	`id`
				FROM `escrow_user`
				WHERE `id` not in (" . join(',', $restricted) . ")
				" . $findBy . "
				LIMIT " . urlencode($start) . "," . $size)->query([
				'what' => '%' . $what . '%'
			]);
		}

		$html = false;

		if (!empty($db)) {

			$h = [];

			$template = H::getTemplate('pages/user/list_user_profile', [], true);
			while (($row = $db->read()) != false) {
				$user = User::getBy([
							'id' => $row['id']
				]);
				$h[] = self::parse($template, [
							'class'			 => in_array($row['id'], $marked)
									? 'invited_expert list_user'
									: 'list_user',
							'id'			 => $user->get('id'),
							'name'			 => $user->get('name'),
							'image'			 => !$user->get('photo')
									? 'images/user_logo.png'
									: $user->get('photo'),
							'showRating'	 => empty($rating)
									? 'display:none;'
									: ($rating == 'deal'
											? $user->get('ratingFontSize')
											: ''),
							'ratingColor'	 => $user->get('ratingColor'),
							'rating'		 => empty($rating)
									? ''
									: ($rating == 'deal'
											? $user->get('rating') . '%'
											: '')
								], true);
			}

			$html = join('', $h);
		}

		//get pagination html

		$pages = ceil($count / $size);



		$h0 = [];

		if ($pages > 0) {
			for ($i = 0; $i < $pages; $i++) {
				$h0[] = '<span class="pagination id_' . $i . ($i == $page
								? ' current_page'
								: '') . '">' . ($i + 1) . '</span>';
			}
		}



		return [
			'html'		 => $html,
			'pagination' => join('', $h0)
		];
	}

	public function get($what, $data = null) {

		if ($what == 'payment_key') {
			return md5($this->get('id') . 'payment_key_rr');
		}

		if ($what == 'custom_photo') {

			if (!$this->custom_photo && $this->custom_photo != '0') {
				return false;
			} else {
				return $this->get([
							'image' => $this->custom_photo
								], [
							'w'		 => 80,
							'h'		 => 80,
							'date'	 => true
				]);
			}
		}

		if ($what == 'photo') {

			$photo = $this->get('custom_photo')
					? $this->get('custom_photo')
					: parent::get('photo');

			if (empty($photo)){
				return 'images/user_logo.png';
			}
			
			if (strpos($photo, 'images') === 0) {
				return B::baseURL() . $photo;
			}

			//вырезаем протокол
			return strpos($photo, '//') !== false
					? '//' . explode('//', $photo)[1]
					: $photo;
		}

		if ($what == 'rating' || $what == 'balance_rating') {
			$total = $this->d(($what == 'balance_rating'
									? 'balance_'
									: '') . 'success') + $this->d(($what == 'balance_rating'
									? 'balance_'
									: '') . 'failure');

			return $total == 0
					? 0
					: round(100 * $this->d(($what == 'balance_rating'
											? 'balance_'
											: '') . 'success') / $total);
		}

		if ($what == 'ratingColor' || $what == 'balanceRatingColor') {

			$total = $this->d(($what == 'balanceRatingColor'
									? 'balance_'
									: '') . 'success') + $this->d(($what == 'balanceRatingColor'
									? 'balance_'
									: '') . 'failure');

			if ($total == 0) {
				return 'white;';
			} else {
				$r = $this->d(($what == 'balanceRatingColor'
										? 'balance_'
										: '') . 'success') / $total;
				if ($r <= 0.7) {
					return 'rgb(255,' . round($r * 364, 0) . ',0);';
				} else {
					return 'rgb(' . round(255 - ($r - 0.7) * 364, 0) . ', 255, 0);';
				}
			}
		}

		if ($what == 'ratingFontSize') {
			$r = $this->d('success');

			if ($r <= 100) {
				return 'font-size:8px;';
			} elseif ($r <= 1000) {
				return 'font-size:10px;';
			} elseif ($r <= 10000) {
				return 'font-size:15px;';
			} elseif ($r <= 100000) {
				return 'font-size:20px;';
			} else {
				return 'font-size:25px;';
			}
		}

		return parent::get($what, $data);
	}

	public function getUserData() {
		return [
			'status' => $this->get('status'),
			'money'	 => $this->get('money'),
			'online' => $this->get('online'),
			'id'	 => $this->get('id'),
			'net'	 => $this->fget('net'),
			'uid'	 => $this->d('uid'),
			'photo'	 => $this->get('photo'),
			'name'	 => $this->fget('name'),
			'role'	 => $this->get('role')
		];
	}

	public static function f() {
		return M::extend(parent::f(), [
					'title'	 => 'User',
					'create' => [
						'status'			 => "tinytext comment 'User status'",
						'money'				 => "float default 10 comment 'User money'",
						'online'			 => "bigint unsigned default null comment 'Last user visit timestamp'",
						'role'				 => "enum('admin','user') default 'user' comment 'User role in system'",
						'success'			 => "float default 0 comment 'Completed deals'",
						'failure'			 => "float default 0 comment 'Failed deals'",
						'balance_success'	 => "float default 0 comment 'Completed balance withdraw'",
						'balance_failure'	 => "float default 0 comment 'Failed balance withdraw'",
						'custom_photo'		 => "tinytext comment 'Custom photo'",
					]
		]);
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
