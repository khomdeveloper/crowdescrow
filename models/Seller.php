<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Seller
 *
 * @author valera261104
 */
class Seller extends Contractor {

	public static function getTable() {
		return 'escrow_user';
	}

	public function completeMilestone($r) {

		self::required([
			'id'			 => true,
			'agreement_id'	 => true,
			'iam'			 => true,
			'value'			 => true
				], $r);

		$completed = min(100, max(0, $r['value'] * 1));

		$milestone = Milestone::getBy([
					'id'			 => $r['id'],
					'agreement_id'	 => $this->getAgreement($r['agreement_id'])->updatePartnerMilestones()->get('id'),
					'status'		 => 'funded',
					'_notfound'		 => true
		]);

		if ($milestone->get('parentIsNotRejected')) {
			throw new Exception('Can change completion status only for rejected milestone!');
		}

		$milestone->set([
			'completed'	 => $completed,
			'status'	 => $completed == 100
					? 'completed'
					: 'funded'
		]);

		if ($completed == 100) {
			$milestone->set([
				'finish'	 => (new DateTime())->format('Y-m-d H:i:s'),
				'expired'	 => (new DateTime())->modify('+' . min(1, $milestone->get('autopay')) . ' DAY')->format('Y-m-d H:i:s')
			])->sendEmailNotification('completed');
		}

		return Milestone::getList($r['agreement_id'], 'seller');
	}

	public function cancelCompletion($r) {

		self::required([
			'id'			 => true,
			'agreement_id'	 => true,
			'iam'			 => true
				], $r);


		$milestone = Milestone::getBy([
					'agreement_id'	 => $this->getAgreement($r['agreement_id'])->updatePartnerMilestones()->updatePartnerDeals()->get('id'),
					'status'		 => 'completed',
					'_notfound'		 => true
		]);

		if ($milestone->get('isExpired') === true) {
			throw new Exception(T::out([
				'error_milestone_expired' => [
					'en' => 'Operation unavailable because milestone status is changing',
					'ru' => 'Операция недоступна потому что меняется статус этапа'
				]
			]));
		}

		$milestone->set([
			'status'	 => 'funded',
			'completed'	 => 90
		]);

		return [
			'ok' => true
		];
	}

	public function cancelWork($r, $returnMoney = false, $auto = false) {

		self::required([
			'id'			 => true,
			'agreement_id'	 => true,
			'amount'		 => true
				], $r);

		$agreement = $this->getAgreement($r['agreement_id']);

		$milestone = Milestone::getBy([
					'id'			 => $r['id'],
					'agreement_id'	 => $agreement->get('id'),
					'status'		 => [
						'funded',
						'completed',
						'rejected',
						'disputed'
					],
					'_notfound'		 => true
		]);

		if (empty($auto) && $milestone->get('isExpired') === true) {
			throw new Exception(T::out([
				'error_milestone_expired' => [
					'en' => 'Operation unavailable because milestone status is changing',
					'ru' => 'Операция недоступна потому что меняется статус этапа'
				]
			]));
		}

		if (empty($returnMoney)) { //закрыть не оплаченную сделку
			if ($milestone->d('funded')) {
				throw new Exception('However milestone was funded!');
			}

			$milestone->set([
				'status' => 'canceled'
			]);
		} else {

			if ($r['amount'] * 1 <= 0 || $r['amount'] * 1 > $milestone->d('funded')) {

				throw new Exception(T::out([
					'refund_amount_error_2' => [
						'en' => 'The amount of refunding should be more than zero and not more than funded amount',
						'ru' => 'Сумма рефинансирования должна быть больше нуля и не больше суммы финансирования этапа'
					]
				]));
			}

			$contractor = User::getBy([
						'id'		 => $agreement->get('customer_id'),
						'_notfound'	 => true
			]);

			$new_balance = $contractor->d('money') + max(0, $r['amount'] * 1);

			$contractor->set([
				'money' => $new_balance
			]);

			$new_fund = $milestone->d('funded') - $r['amount'] * 1;

			if ($new_fund == 0) { //completely remove it
				$milestone->set([
					'funded' => $new_fund,
					'status' => 'canceled'
				])->sendEmailNotification('refund', [
					'cancel' => true,
					'auto'	 => $auto,
					'side'	 => 'seller', //продавец отказался
					'amount' => $r['amount']
				])->sendEmailNotification(!empty($auto)
								? 'refund'
								: false, [
					'cancel' => true,
					'auto'	 => true,
					'side'	 => 'customer', //дополнительное уведомление продавцу
					'amount' => $r['amount']
				]);
			} else { //just reduce funded amount and amount which need
				$milestone->set([
					'amount' => $new_fund,
					'funded' => $new_fund
				])->sendEmailNotification('refund', [
					'amount' => $r['amount'],
					'side'	 => 'seller', //продавец отказался
					'auto'	 => $auto
				])->sendeEmailNotification(!empty($auto) ? 'refund' : false,[
					'amount' => $r['amount'],
					'side'	 => 'customer', //доплнительное уведомление продавцу
					'auto'	 => $auto
				]);
			}
		}

		//update milestones list when customer view
		if (empty($auto)) {
			$agreement->updatePartnerMilestones()->updatePartnerDeals();

			if (!empty($returnMoney)) {
				//add update balance action
				Action::getBy([
					'key'		 => 'update_balance',
					'_notfound'	 => [
						'key'	 => 'update_balance',
						'action' => json_encode([
							'User' => [
								'updateBalance' => [
									'data' => 'none'
								]
							]
						])
					]
				])->setFor($agreement->get('customer_id'));
			}
		}

		return [
			'agreement' => $agreement->checkTermination()->get('status')
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
