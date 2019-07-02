<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Payment
 *
 * @author valera261104
 */
class Payment extends M {

	public static function getPaymentListToFillUp() {

		//TODO: учет hidden offer

		$db = Yii::app()->db->createCommand("
				SELECT distinct `escrow_payment`.`id` as `id`
				FROM `escrow_offer` 
				LEFT JOIN `escrow_userpaymentmethod` 
				ON `escrow_userpaymentmethod`.`id` = `escrow_offer`.`method_id`
				LEFT JOIN `escrow_payment`
				ON `escrow_payment`.`id` = `escrow_userpaymentmethod`.`payment_id` 
				WHERE `escrow_offer`.`status` = 'waiting'
				AND `escrow_offer`.`currency` != '_deal'	
				AND `escrow_offer`.`user_id` != :user_id
		")->query([
			'user_id' => User::logged()->get('id')
		]);
		
		if (empty($db)){
			return [];
		}
		
		$ids = [];
		while (($row = $db->read()) != false) {
			$ids[] = $row['id'];
		}
		
		return self::getBy([
			'id' => $ids,
			'_return' => [
				0 => 'object'
			]
		]);
		
	}

	public function getRadioGroupHTML() {

		$currencies = $this->get('currencies');

		if (empty($currencies)) {
			throw new Exception('no currencies has found');
		}

		$h = [];

		$checked = empty($_SESSION['choosen_filter_currency'])
				? false
				: $_SESSION['choosen_filter_currency'];

		foreach ($currencies as $option) {
			$h[] = '<label><input type="radio" name="currency_nominal_filter" class="currency_nominal_filter" value="' . $option . '" ' . (empty($checked) || $checked === $option
							? 'checked="checked"'
							: '') . '/> ' . $option . '  </label>';
			if (empty($checked)) {
				$checked = true;
			}
		}

		return join('', $h);
	}

	public function get($what, $data = null) {

		if ($what === 'currencies') {

			if (!empty($data) && $data == 'throw' && !$this->get('currency')) {
				throw new Exception(T::out([
					'currencies not found' => [
						'en' => 'No currencies has been founded, payment method unavailable!',
						'ru' => 'Не найдены номиналы валюты, метод платежа недоступен!'
					]
				]));
			}

			return $this->get('currency')
					? explode(',', str_replace(' ', '', $this->get('currency')))
					: false;
		}

		return parent::get($what, $data);
	}

	public static function f() {
		return [
			'title'		 => 'Payment system',
			'datatype'	 => [
				'description'	 => [
					'T' => [
						'id' => true
					]
				],
				'title'			 => [
					'T' => [
						'id' => true
					]
				]
			],
			'create'	 => [
				'title'			 => "bigint unsigned default null comment 'Link to title'",
				'description'	 => "bigint unsigned default null comment 'Link to description'",
				'url'			 => "tinytext comment 'Payment system name'",
				'currency'		 => "tinytext default null comment 'Currency nominal'"
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
