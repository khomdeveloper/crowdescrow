<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Spam
 *
 * @author valera261104
 */
class Spam extends M {

	public static function action($r, $dontdie = false) {

		$com = empty($r[get_called_class()])
				? false
				: $r[get_called_class()];

		if ($com == 'send') {

			$count = S::getBy([
						'key'		 => 'spam_in_one_iteration',
						'_notfound'	 => [
							'key'	 => 'spam_in_one_iteration',
							'val'	 => 10
						]
					])->d('val');

			$list = self::getBy([
						'sent'		 => 'is null',
						'_limit'	 => $count,
						'_return'	 => [0 => 'object']
			]);

			if (empty($list)) {
				return [
					'sent' => 0
				];
			} else {

				$broadcasts = [];

				$sent = 0;

				foreach ($list as $record) {

					if (empty($broadcasts[$record->get('broadcast_id')])) {

						$broadcast = Broadcast::getBy([
									'id'	 => $record->get('broadcast_id'),
									'begin'	 => [
										'_between' => [
											'0000-00-00 00:00',
											(new DateTime())->format('Y-m-d H:i:s')
										]
									]
						]);


						if (empty($broadcast)) { //указанное сообщение не найдено
							continue;
						}

						$broadcasts[$record->get('broadcast_id')] = $broadcast;
					}

					$title = $broadcasts[$record->get('broadcast_id')]->get('title');
					$message = $broadcasts[$record->get('broadcast_id')]->get('description');
					$email = $record->get('email');

					$locale = T::getLocale();
					T::setLocale('en');
					
					Mail::send([
						'to'		 => $email,
						'from_name'	 => 'CrowdEscrow.biz',
						'reply_to'	 => 'admin@crowdescrow.biz',
						'priority'	 => 0,
						'subject'	 => $title,
						'html'		 => H::getTemplate('email/expert_invitation', [
							'header'	 => $title,
							'name'		 => 'CrowdEscrow.biz',
							'message'	 => $message,
							'link'		 => B::setProtocol('https:', B::baseURL() . 'witdraw')
								], 'addDelimeters')
					]);
					
					$record->set([
						'sent' => (new DateTime())->format('Y-m-d H:i:s')
					]);
					
					T::setLocale($locale);
					
					$sent++;
				}

				return [
					'sent' => $sent
				];
			}
		}
	}

	public static function f() {
		return [
			'title'		 => 'Spam mail controller',
			'datatype'	 => [
				'broadcast_id' => [
					'Broadcast' => [
						'id' => true
					]
				]
			],
			'create'	 => [
				'email'			 => "tinytext comment 'Email to send'",
				'broadcast_id'	 => "bigint unsigned default null comment 'Link to message'",
				'sent'			 => "datetime default null comment 'Fact of sending'"
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
