<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Visit
 *
 * @author valera261104
 */
class Visit extends M{
	
	public static function view(){
		
		$visit = self::getBy([
			'ip' => $_SERVER['REMOTE_ADDR'],
			'_notfound' => [
				'ip' => $_SERVER['REMOTE_ADDR'],
				'useragent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'uncknown',
				'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'uncknown',
				'count' => 0
			]
		]);
		
		$visit->set([
			'count' => $visit->get('count')*1 + 1,
			'visit' => (new DateTime())->format('Y-m-d H:i:s'),
			'from' => json_encode($_GET)
		]);
		
	}
	
	public static function f() {
		return [
			'title'	 => 'Pre user notification',
			'create' => [
				'ip'		 => "tinytext default null comment 'Ip address'",
				'useragent'	 => "text default null comment 'Useragent data'",
				'visit'	 => "datetime default null comment 'Date of visit'",
				'count' => "int default 0 comment 'Visit counter'",
				'referer' => "tinytext default null comment 'Referer'",
				'from' => "text default null comment 'From variable'",
				'click'		 => "datetime default null comment 'Date of click'",
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}
	
}
