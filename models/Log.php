<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Log
 *
 * @author valera261104
 */
class Log extends M {

	public static function f() {
		return [
			'title'	 => 'User activity in offers',
			'create' => [
				'ip'		 => "tinytext default null comment 'Ip address'",
				'useragent'	 => "text default null comment 'Useragent data'",
				'date'		 => "datetime default null comment 'Date of event'",
				'data'		 => "text default null comment 'Data storage'",
				'event'		 => "tinytext default null comment 'Logged event'",
				'offer_id'	 => "bigint unsigned default null comment 'Link to offer'",
				'user_id'	 => "bigint unsigned default null comment 'Link to user'"
			]
		];
	}

	public function html() {
		return H::getTemplate('pages/deal/correction_string', [
					'partner'	 => $this->get('user_id')
							? User::getBy([
								'id' => $this->get('user_id')
							])->get('name')
							: 'System',
					'date'		 => $this->get('date'),
					'message'	 => $this->get('event')
						], true);
	}
	
	//TODO: добавить расшифровку данных

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
