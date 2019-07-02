<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Hide
 *
 * @author valera261104
 */
class Hide extends M{

	public static function action($r, $dontdie = false) {

		$com = empty($r[get_called_class()])
				? false
				: $r[get_called_class()];
		
		$user = User::logged();
		
		if ($com == 'hide'){
			
			self::required([
				'offer_id' => true
			],$r);
			
			self::getBy([
				'user_id' => $user->get('id'),
				'offer_id' => $r['offer_id'],
				'_notfound' => [
					'user_id' => $user->get('id'),
					'offer_id' => $r['offer_id'],
					'description' => $r['description']
				]
			]);
			
			//calculate if enough spam reports
			$max_spam = S::getBy([
				'key' => 'max_spam_reports_for_offer',
				'_notfound' => [
					'key' => 'max_spam_reports_for_offer',
					'val' => 1
				]
			])->d('val');
			
			if (self::getBy([
				'offer_id' => $r['offer_id'],
				'_return' => 'count'
			]) > $max_spam || $user->get('role') == 'admin'){
				
				$offer = Offer::getBy([
					'id' => $r['offer_id'],
					'status' => 'waiting',
					'currency' => '!=_deal',
					'_notfound' => true
				]);
				
				//восстанавливаем средства с заказа
				$owner = $offer->get('Seller')->inc([
					'money' => $offer->d('amount')
				]);
				
				$offer->addEvent('Cancelled because of spam report', $owner)->setActions([
					'update_withdraw_orders',
					'update_balance'
				],[
					$owner->get('id')
				])->sendEmailNotification('spam_reported')->remove(); 
				
			}
			
			return [
				'Balance' => [
					'findOffers' => [
						'page' => 0
					]
				]
			];
			
		}	
		
	}	
	
	public static function f() {
		return [
			'title'		 => 'Hidden spam offers',
			'datatype'	 => [
				'user_id' => [
					'User' => [
						'id' => true
					]
				],
				'offer_id' => [
					'Offer' => [
						'id' => true
					]
				]
			],
			'create'	 => [
				'user_id'		 => "bigint unsigned default null comment 'Link to user (who hide)'",
				'offer_id'	 => "bigint unsigned default null comment 'Link to offer (hidden offer)'",
				'description'	 => "text comment 'Reason of hide'"
			]
		];
	}
	
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}
	
}
