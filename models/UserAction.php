<?php

/**
  This class works around which method is it necessary to run when observe user
 */
class UserAction extends M {

	public static function f() {
		return [
			'title'		 => 'Action on observe for specific user',
			'datatype'	 => [
				'user_id'	 => [
					'User' => [
						'id' => ' ON DELETE CASCADE '
					]
				],
				'action_id'	 => [
					'Action' => [
						'id' => ' ON DELETE CASCADE '
					]
				]
			],
			'create'	 => [
				'user_id'	 => "bigint unsigned default null comment 'Link to user'",
				'action_id'	 => "bigint unsigned default null comment 'Link to action'",
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
