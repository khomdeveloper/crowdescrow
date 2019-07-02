<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Landing
 *
 * @author valera261104
 */
class Landing extends M {

	public static function action($r, $dontdie = false) {
		$com = empty($r[get_called_class()])
				? false
				: $r[get_called_class()];

		if ($com == 'create') {
			self::required([
				'email' => true
					], $r);

			$record = self::getBy([
				'email'		 => $r['email'],
				'_notfound'	 => [
					'email'		 => $r['email'],
					'message'	 => empty($r['message'])
							? null
							: $r['message'],
					'date' => (new DateTime())->format('Y-m-d H:i:s')
				]
			]);

			if ($record->get('status') !== 'new') {
				throw new Exception(T::out([
					'error_landing_already' => [
						'en' => 'You already left your email address. We will send you a notification when the service will be ready to work.',
						'ru' => 'Вы уже оставляли свой email. Мы обязательно отправим на него уведомление о готовности сервиса к работе.'
					]
				]));
			}
			
			$record->set([
				'status' => 'wait'
			]);
			
			return [
				'ok' => T::out([
					'error_landing_success' => [
						'en' => 'Thank you for your interest in our project! We will send you a notification when the service will be ready to work.',
						'ru' => 'Спасибо за интерес к нашему проекту! Мы обязательно отправим уведомление о готовности сервиса к работе.'
					]
				])
			];
			
		} elseif ($com == 'send') {
			
			self::required([
				'email' => true,
				'message' => true
			],$r);
			
			$user = User::logged();
			
			$email = filter_var($r['email'],FILTER_VALIDATE_EMAIL);
			
			if (empty($email)){
				throw new Exception(T::out([
					'email_not_recognized_as_correct2' => [
						'en' => '"{{email}}" is not recognized as valid email address',
						'ru' => '«{{email}}» не распознан как валидный адрес электронной почты',
						'_include' => [
							'email' => $r['email']
						]
					]
				]));
			}
			
			Mail::send([
				'to'		 => 'info@crowdescrow.biz',
				'from_name'	 => 'CrowdEscrow',
				'reply_to'	 => 'admin@crowdescrow.biz',
				'priority'	 => 1,
				'subject'	 => 'Crowdescrow.biz feedback',
				'html'		 => $r['message'] . '<p>from:' . $email . ', user_id:' . $user->get('id') . '</p>'
			]);
			
			return [
				'ok' => T::out([
					'tech_support_success' => [
						'en' => 'Thank you for your message. Technical support will call you if it will be necessary.',
						'ru' => 'Спасибо за сообщение. Специалист технической поддержки сервиса свяжется с вами, если это будет необходимо.'
					]
				])
			];
			
		}
	}

	/**
	 * check if service is available
	 */
	public static function isAvailable() {

		if (isset($_SESSION['developer']) && $_SESSION['developer'] == '1349'){
			return true;
		} elseif (isset($_REQUEST['developer']) && $_REQUEST['developer'] == '1349'){
			$_SESSION['developer'] = 1349;
			return true;
		}
		
		$now = new DateTime();

		$when = new DateTime(S::getBy([
					'key'		 => 'when_start_service',
					'_notfound'	 => [
						'key'	 => 'when_start_service',
						'val'	 => '2016-09-15 12:00:00'
					]
				])->get('val'));

		return $when->getTimeStamp() <= $now->getTimestamp()
				? true
				: false;
	}

	public static function f() {
		return [
			'title'	 => 'Pre user notification',
			'create' => [
				'email'		 => "tinytext default null comment 'Viewer email'",
				'message'	 => "text default null comment 'Message from viewer'",
				'status'	 => "enum ('new','wait','notificated') default 'new' comment 'Record status'",
				'date'		 => "datetime default null comment 'Date of register'",
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
