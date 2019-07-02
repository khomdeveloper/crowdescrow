<?php

//require_once dirname(dirname(Yii::app()->request->scriptFile)) . '/facebook/autoload.php';
require_once Environment::get('vh2015'). '/autoload.php';

use Facebook\FacebookSession;
use Facebook\FacebookJavaScriptLoginHelper;
use Facebook\FacebookRequest;
use Facebook\GraphUser;
use Facebook\FacebookRequestException;

class UserController extends Controller {

	public function actionIndex() {
		$this->render('index');
	}

	public function actionMain() {
		$r = array_merge($_POST,$_GET);
		
		/*
		if (file_exists('.' . B::baseDIR() . 'protected/models/' . 'Offer.php')){
			die('stop');
		} else {
			die('mark');
		}*/
		
		foreach ($r as $key => $val) {
			if (ucfirst($key) == $key && $key[0] != '_' && (strtoupper($key) != $key || (strtoupper($key) == $key && strlen($key) == 1)) && class_exists($key)) {
				
				call_user_func([$key,
					'call'], $r);
			}
		}
	}

	//TODO: move it to separate class
	/*
	  just_check -> don`t load profile and friends
	  TODO: use session for this case
	 */
	public static function checkFBsession($uid, $just_check = false) {

		/*
		  $app_id = 277236325820557;
		  $app_secret = 'ad7b44ea1db4fd8ec20bd546597417af'; */

		$app_id = Yii::app()->params['app_id'];
		$app_secret = Yii::app()->params['app_secret'];

		FacebookSession::setDefaultApplication($app_id, $app_secret);

		$helper = new FacebookJavaScriptLoginHelper($app_id);
		try {
			$session = $helper->getSession();
		} catch (FacebookRequestException $ex) {
			M::e([
				'error'		 => 'login_error',
				'message'	 => $ex->getMessage()
			]);
		} catch (\Exception $ex) {
			M::e([
				'error'		 => 'login_error',
				'message'	 => $ex->getMessage()
			]);
		}

		if ($session) {
			try {

				if ($just_check) { //if only check user logged - just return true
					return true;
				}

				$user_profile = (new FacebookRequest(
						$session, 'GET', '/me'
						))->execute()->getGraphObject(GraphUser::className());

				if (empty($user_profile) || $uid != $user_profile->getId()) {
					M::e([
						'error'		 => 'fatal_login_error',
						'message'	 => [
							'empty_user_profile' => [
								'en' => 'Failed to get user profile',
								'ru' => 'Профиль пользователя не получен'
							]
						]
					]);
				}

				$user_profile = $user_profile->asArray();

				//TODO: it is necessary to cash all these parts for some times (a day f.e.)
				//here we shall try to get user friends and add them to user menu
				$invitable_friends = (new FacebookRequest(
						$session, 'GET', '/me/invitable_friends'
						))->execute()->getGraphObject();

				if (!empty($invitable_friends)) {
					$user_profile['invitable_friends'] = json_decode(json_encode($invitable_friends->asArray()['data']), true);
				}

				//and now get invited_friends

				$invited_friends = (new FacebookRequest(
						$session, 'GET', '/me/friends'
						))->execute()->getGraphObject();

				if (!empty($invited_friends)) {
					$user_profile['invited_friends'] = json_decode(json_encode($invited_friends->asArray()['data']), true);
				}

				return $user_profile; //-> this is success point
			} catch (FacebookRequestException $e) {
				M::e([
					'error'		 => 'fatal_login_error',
					'message'	 => $e->getMessage() . ' code: ' . $e->getCode()
				]);
			}
		} else {
			M::e([
				'error'		 => 'login_error',
				'message'	 => [
					'session_expired_or_lost' => [
						'en' => 'Session expired or lost!',
						'ru' => 'Сессия просрочена или утрачена!'
					]
				]
			]);
		}
	}

	public function actionTest() {

		T::w(['loading_user_data' => [
				'en' => 'Loading user data...',
				'ru' => 'Загружаются данные пользователя...'
			]], 'ru');
	}

	public function actionAdd() { //deprecated
		$r = $_REQUEST;

		U::checkRequired($r);
		M::session($r['_sid']);

		//print_r($_SESSION);

		$user_profile = self::checkFBsession($r['uid']);

		$r['invited_friends'] = !empty($user_profile['invited_friends'])
				? json_encode($user_profile['invited_friends'])
				: '';
		$r['invitable_friends'] = !empty($user_profile['invitable_friends'])
				? json_encode($user_profile['invitable_friends'])
				: '';
		$r['photo'] = '//graph.facebook.com/' . $r['uid'] . '/picture?type=large';

		$r['country'] = explode('_', $user_profile['locale'])[1];

		$user = ThwUser::getBy([
					'net'	 => $r['net'],
					'uid'	 => $r['uid']
				])->add($r);

		$_SESSION['user'] = [
			'id'	 => $user->id,
			'net'	 => $user->net,
			'uid'	 => $user->uid
		];

		//here try to get unread thanks              
		$unread = ThwThank::getBy([
					'read'			 => 0,
					'receiver_uid'	 => $user->uid,
					'receiver_net'	 => $user->net,
					'status'		 => '!=message',
					'_return'		 => 'array',
					'_order'		 => 'changed'
		]);

		//var_dump($unread);

		if (empty($unread)) {
			$result = 0;
		} else {
			$result = [];
			foreach ($unread as $key => $val) {
				$obj = $val->encode(false);
				$sender = ThwUser::getBy([
							'net'	 => $val['sender_net'],
							'uid'	 => $val['sender_uid']
				]);
				$obj['sender_name'] = $sender->first_name . ' ' . $sender->last_name;
				$result[] = $obj;
			}
		}

		die(json_encode([
			'user'			 => $user->encode(false),
			'unread'		 => $result,
			'locale_changed' => T::setLocale($user->locale),
			'locale'		 => $_SESSION['locale']
		]));
	}

	//piterpass

	public function actionPlace() {
		Place::call($_REQUEST);
	}

	public function actionAdmin() {
		A::action($_REQUEST);
	}

	public function actionReview() {
		Review::call($_REQUEST);
	}

	public function actionLogin() {	
		User::call($_REQUEST);
	}

	public function actionMailing() {
		Mailing::call($_REQUEST);
	}

	public function actionOrder() {
		Order::call($_REQUEST);
	}

	public function actionDownload() {
		$r = $_REQUEST;

		$email = empty($r['email'])
				? false
				: filter_var(trim($r['email']), FILTER_VALIDATE_EMAIL);

		if (empty($email) || empty($r['name'])) {
			M::jsonp([
				'parent.About.error' => [
					'error'		 => 'enter_valid_code2',
					'message'	 => T::out([
						'enter_valid_code' => [
							'en' => 'It is necessary to fill the name and valid email address to download the Guide.',
							'ru' => 'Необходимо заполнить имя и действительный адрес электронной почты чтобы загрузить Гид.'
						]
					])
				]
			]);
		}

		Subscription::getBy([
			'email'		 => $email,
			'_notfound'	 => [
				'email'	 => $email,
				'name'	 => $r['name'],
				'cancel' => md5(time() . md5('salt495830gh'))
			]
		]);

		$path = './protected/_files/piterpass_guide.pdf';

		if (file_exists($path)) {
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=piterpass_guide.pdf");
			readfile($path);
		} else {
			M::jsonp([
				'parent.About.error' => [
					'error'		 => 'file_not_found',
					'message'	 => T::out([
						'file_not_found' => [
							'en'		 => 'File "{{file}}" not found1',
							'ru'		 => 'Файл «{{file}}» не найден!',
							'_include'	 => [
								'file' => $path
							]
						]
					])
				]
			]);
		}
	}

	public function actionSubscribe2() { //deprecated
		//TODO: move code to U class
		//TODO: combine with register
		M::session($r['_sid']);

		U::required([
			'name'	 => 1,
			'email'	 => 1
				], [
			'name'	 => trim($_REQUEST['name']),
			'email'	 => trim($_REQUEST['email'])
		]);

		//check global auth status

		$user = U::getBy([
					'email'		 => $_REQUEST['email'],
					'_notfound'	 => [
						'email'	 => trim($_REQUEST['email']),
						'name'	 => '_new'
					]
		]);

		if ($user->g('login') && $user->g('pass')) { //add social network status
			//this email used by rigestered user
		}

		if ($user->get('name') == '_new') { //new user
			$message = [
				'status'	 => T::out([
					'new_subscription' => [
						'en' => 'You have successfully subscribed to the newsletter.',
						'ru' => 'Вы успешно подписались на рассылку новостей.'
					]
				]),
				'address'	 => T::out([
					'name_email' => [
						'en'		 => 'Messages will be sent to the email address {{email}}',
						'ru'		 => 'Сообщения будут приходить на адрес электронной почты «{{email}}»',
						'_include'	 => [
							'email' => $user->g('email'),
						]
					]
				]),
				'name'		 => T::out([
					'receiver_name' => [
						'en'		 => ' for {{name}}.',
						'ru'		 => ' для {{name}}.',
						'_include'	 => [
							'name' => $_REQUEST['name']
						]
					]
				])
			];

			$user = $user->set([
				'unsubscribed'	 => 0,
				'name'			 => $_REQUEST['name']
			]);
		} else {

			$message = [
				$user->get('unsubscribed')
						? T::out([
							're_subscription' => [
								'en' => 'You re-subscribe to the newsletter. ',
								'ru' => 'Вы повторно подписались на рассылку новостей. '
							]
						])
						: '',
				T::out([
					'name_email' => [
						'en'		 => 'Messages will be sent to the email address {{email}}',
						'ru'		 => 'Сообщения будут приходить на адрес электронной почты «{{email}}»',
						'_include'	 => [
							'email' => $user->g('email')
						]
					]
				]),
				$user->g('name') != $_REQUEST['name']
						? T::out([
							'receiver_name_changed' => [
								'en'		 => '. Receiver name has been changed from {{from}} to {{to}}.',
								'ru'		 => '. Имя получателя изменено c {{from}} на {{to}}.',
								'_include'	 => [
									'from'	 => $user->g('name'),
									'to'	 => $_REQUEST['name']
								]
							]
						])
						: T::out([
							'receiver_name' => [
								'en'		 => ' for {{name}}.',
								'ru'		 => ' для {{name}}.',
								'_include'	 => [
									'name' => $_REQUEST['name']
								]
							]
						])
			];

			if ($user->get('unsubscribed') || $user->g('name') != $_REQUEST['name']) {
				$user = $user->set([
					'unsubscribed'	 => 0,
					'name'			 => $_REQUEST['name']
				]);
			} else {
				M::e([
					'error'		 => 'nothing to change',
					'message'	 => [
						'subscribed_already' => [
							'en' => 'You have been already subscribed!',
							'ru' => 'Вы уже подписаны!'
						]
					]
				]);
			}
		}

		M::ok([
			'Dialog' => [
				'show' => [
					'title'		 => T::out(['success' => [
							'en' => 'GREAT',
							'ru' => 'ОТЛИЧНО'
						]
					]),
					'message'	 => join(' ', $message),
					'css'		 => [
						'Application' => [
							'getCss' => [
								'type' => 'ok'
							]
						]
					]
				]
			]
		]);
	}

	//return all translates for current locale
	public function actionTranslates() {
		die(json_encode(T::all()));
	}

	public function actionTranslate() {

		T::required([
			'key' => 1
		]);

		try {

			$t = T::getBy([
						'key'		 => $_REQUEST['key'],
						'_notfound'	 => false
			]);

			if (empty($t)) {
				M::e([
					'error'		 => 'translate_error',
					'key'		 => $_REQUEST['key'],
					'request'	 => $_REQUEST
				]);
			}

			die(json_encode(
							$t->encode(false)
			));
		} catch (Exception $e) {
			die($e->getMessage());
		}
	}

	public function actionUnsubscribe() {

		ThwUser::getBy([
			'id' => ThwUser::getCurrent()['id']
		])->set([
			'unsubscribed' => 1
		]);

		die(json_encode([
			'message' => T::out([
				'unsubscribed' => [
					'en' => 'You will not receive e-mail notifications any more.',
					'ru' => 'Вы больше не будете получать уведомления по электронной почте.'
				]
			])
		]));
	}

	// Uncomment the following methods and override them if needed
	/*
	  public function filters()
	  {
	  // return the filter configuration for this controller, e.g.:
	  return array(
	  'inlineFilterName',
	  array(
	  'class'=>'path.to.FilterClass',
	  'propertyName'=>'propertyValue',
	  ),
	  );
	  }

	  public function actions()
	  {
	  // return external action classes, e.g.:
	  return array(
	  'action1'=>'path.to.ActionClass',
	  'action2'=>array(
	  'class'=>'path.to.AnotherActionClass',
	  'propertyName'=>'propertyValue',
	  ),
	  );
	  }
	 */
}
