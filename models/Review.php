<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Review
 *
 * @author valera261104
 */
class Review extends M {

	public static function f() {
		return [
			'title'		 => 'Review',
			'datatype'	 => [
				'user_id'	 => [
					'User' => [
						'id' => ' ON DELETE CASCADE '
					]
				],
				'offer_id'	 => [
					'Offer' => [
						'id' => ' ON DELETE RESTRICTED '
					]
				],
			],
			'create'	 => [
				'user_id'	 => "bigint unsigned default null comment 'User who make a review'",
				'offer_id'	 => "bigint unsigned default null comment 'Linked offer'",
				'amount'	 => "float unsigned not null comment 'Amount of offer'",
				'rating'	 => "tinyint default 0 comment 'Rating'",
				'message'	 => "text default null comment 'Review message'",
				'type'		 => "enum('deal','balance') default 'deal' comment 'Type of offer'"
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
