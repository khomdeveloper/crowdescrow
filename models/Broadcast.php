<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Broadcast
 *
 * @author valera261104
 */
class Broadcast extends M{
	
	public static function f() {
		return [
			'title'		 => 'Broadcast messages',
			'create'	 => [
				'title' => "tinytext comment 'Message title'",
				'description'	 => "text comment 'Message description'",
				'begin' => "datetime default null comment 'When to start'"
			]
		];
	}
	
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}
	
}
