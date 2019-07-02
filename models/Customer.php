<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Customer
 *
 * @author valera261104
 */
class Customer extends Contractor {

	public static function getTable() {
		return 'escrow_user';
	}

	public function rejectMilestone($r) {

		self::required([
			'id'				 => true,
			'agreement_id'		 => true,
			'iam'				 => true,
			'correction_term'	 => true,
			'correction_list'	 => true
				], $r);

		if (empty($r['correction_term']) || empty($r['correction_list'])) {
			throw new Exception(T::out([
				'please_fill_up_correction_term_and_list' => [
					'en' => 'Please fill up the corrections list and required realisation terms',
					'ru' => 'Пожалуйста заполните список правок и требуемые сроки их реализации'
				]
			]));
		}

		$milestone = Milestone::getBy([
					'id'			 => $r['id'],
					'agreement_id'	 => $this->getAgreement($r['agreement_id'])->updatePartnerMilestones()->updatePartnerDeals()->get('id'),
					'status'		 => [
						'completed',
						'rejected'
					],
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
			'status'	 => 'rejected',
			'expired'	 => null
		]);

		$correction = Milestone::getBy([
					'id'		 => '_new',
					'_notfound'	 => [
						'milestone_id'	 => $milestone->get('id'),
						'agreement_id'	 => $milestone->get('agreement_id'),
						'title'			 => 'Correction list', //TODO: make it as part of description
						'status'		 => 'customerCorrection',
						'expired'		 => (new DateTime())->modify('+' . min(1, $milestone->get('autopay')) . ' DAY')->format('Y-m-d H:i:s'),
						'conditions'	 => $r['correction_list'],
						'term'			 => $r['correction_term']
					]
				])->sendEmailNotification('rejected');

		return [
			'action' => 'completed'
		];
	}

	public function fundMilestone($r) {

		self::required([
			'id'			 => true,
			'agreement_id'	 => true
				], $r);

		$milestone = Milestone::getBy([
					'id'			 => $r['id'],
					'agreement_id'	 => $this->getAgreement($r['agreement_id'])->updatePartnerMilestones()->updatePartnerDeals()->get('id'),
					'status'		 => [
						'waiting',
						'rejected'
					],
					'_notfound'		 => true
		]);

		$need = max(0, $milestone->d('amount'));

		$has = $this->d('money');

		if ($has < $need) {
			return [
				'warning' => 'no_money'
			];
		}

		//вызов user, потому что именно его надо в кеш загнать
		$user = User::getBy([
					'id' => $this->get('id')
				])->set([
					'money' => max(0, $this->d('money') - $need)
				])->cash();

		$milestone->set([
			'status'	 => $milestone->get('status') == 'rejected'
					? 'rejected' //??? when we get this
					: 'funded',
			'start'		 => (new DateTime())->format('Y-m-d H:i:s'),
			'deadline'	 => (new DateTime())->modify("+" . $milestone->get('term') . " DAY")->format('Y-m-d H:i:s'),
			'funded'	 => $milestone->get('amount') //TODO: частичное финансирование
		])->sendEmailNotification('funded');

		return array_merge([
			'balance' => $user->d('money')
				], Milestone::getList($r['agreement_id'], 'customer'));
	}

	public function releaseMilestone($r, $auto = false) {

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
						'completed',
						'funded',
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

		$contractor = User::getBy([
					'id'		 => $agreement->get('seller_id'),
					'_notfound'	 => true
		]);

		if ($milestone->d('funded') > $r['amount'] * 1) {

			$new_balance = $contractor->d('money') + max(0, $r['amount']);

			$milestone->set([
				'funded' => $milestone->d('funded') - $r['amount'] * 1,
				'amount' => $milestone->d('amount') - $r['amount'] * 1
			]);
		} else {
			$milestone->set([
				'status' => 'released'
			]);

			$agreement = $agreement->checkCompletion();

			$new_balance = $contractor->d('money') + max(0, $milestone->d('funded'));
		}

		$contractor = $contractor->set([
			'money' => $new_balance
		]);

		//add update balance action
		if (empty($auto)) {
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
			])->setFor($agreement->get('seller_id'));

			if ($agreement->get('status') == 'completed') { //push to reshow agreements list 
				Action::getBy([
					'key'		 => 'hide_milestones',
					'_notfound'	 => [
						'key'	 => 'hide_milestones',
						'action' => json_encode([
							'Milestone' => [
								'hideList' => [
									'data' => 'none'
								]
							]
						])
					]
				])->setFor($agreement->get('seller_id'));

				$agreement->updatePartnerDeals();
			} else {
				$agreement->updatePartnerMilestones();
			}
		} //of manual mode

		$milestone->sendEmailNotification('release', [
			'amount' => $r['amount'],
			'auto'	 => $auto,
			'side'	 => 'customer'
		])->sendeEmailNotification(!empty($auto)
						? 'release'
						: false, [
			'amount' => $r['amount'],
			'auto'	 => $auto,
			'side'	 => 'seller' //second email to customer
		]);

		return [
			'agreementStatus' => $agreement->get('status')
		];
	}

	/**
	 * 
	 * Accept corrections results
	 * 
	 * @param type $r
	 * @param type $auto
	 * @return type
	 * @throws Exception
	 */
	public function acceptCorrection($r, $auto = false) {

		self::required([
			'id'			 => true,
			'agreement_id'	 => true,
				], $r);

		$agreement = $this->getAgreement($r['agreement_id']);

		$toRemove = Milestone::getBy([
					'agreement_id'	 => $agreement->get('id'),
					'id'			 => $r['id'],
					'milestone_id'	 => 'is not null',
					'status'		 => [
						'customerCorrection',
						'sellerCorrection',
						'funded',
						'completed'
					],
					'_notfound'		 => true
		]);

		if (empty($auto) && $toRemove->get('isExpired') === true) { //выключено для автоматических операций
			throw new Exception(T::out([
				'error_milestone_expired' => [
					'en' => 'Operation unavailable because milestone status is changing',
					'ru' => 'Операция недоступна потому что меняется статус этапа'
				]
			]));
		}

		//checking if parent id should stay as rejected

		$parent_id = $toRemove->get('milestone_id');

		$toRemove->remove();

		$correctionsCount = Milestone::getBy([
					'agreement_id'	 => $agreement->get('id'),
					'milestone_id'	 => $parent_id,
					'_return'		 => 'count'
		]);

		if ($correctionsCount * 1 == 0) { //need to remove rejected status of parent
			$milestone = Milestone::getBy([
						'id'			 => $parent_id,
						'agreement_id'	 => $agreement->get('id'),
						'_notfound'		 => true
			]);

			//set parent milestone as completed
			$milestone->set([
				'status'	 => 'completed',
				'expired'	 => (new DateTime())->modify('+' . min(1, $milestone->get('autopay')) . ' DAY')->format('Y-m-d H:i:s')
			])->sendEmailNotification('completed');
			
			//TODO: send confirmation notification also to seller
			
		}

		if (empty($auto)) {
			$agreement->updatePartnerMilestones();
		}

		return [
			'action' => 'completed'
		];
	}

	public function rejectCorrection($r) {

		self::required([
			'id'				 => true,
			'agreement_id'		 => true,
			'iam'				 => true,
			'correction_term'	 => true,
			'correction_list'	 => true
				], $r);


		if (empty($r['correction_term']) || empty($r['correction_list'])) {
			throw new Exception(T::out([
				'please_fill_up_correction_term_and_list' => [
					'en' => 'Please fill up the corrections list and required realisation terms',
					'ru' => 'Пожалуйста заполните список правок и требуемые сроки их реализации'
				]
			]));
		}

		$agreement = $this->getAgreement($r['agreement_id']);

		$toRemove = Milestone::getBy([
					'id'			 => $r['id'],
					'agreement_id'	 => $agreement->get('id'),
					'status'		 => 'completed',
					'milestone_id'	 => 'is not null',
					'_notfound'		 => true
		]);

		if ($toRemove->get('isExpired') === true) {
			throw new Exception(T::out([
				'error_milestone_expired' => [
					'en' => 'Operation unavailable because milestone status is changing',
					'ru' => 'Операция недоступна потому что меняется статус этапа'
				]
			]));
		}

		$parent = Milestone::getBy([
					'id'			 => $toRemove->get('milestone_id'),
					'agreement_id'	 => $agreement->get('id'),
					'status'		 => 'rejected',
					'_notfound'		 => true
		]);

		$toRemove->remove(); //remove old one 

		$correction = Milestone::getBy([
					'id'		 => '_new',
					'_notfound'	 => [
						'milestone_id'	 => $parent->get('id'),
						'agreement_id'	 => $parent->get('agreement_id'),
						'title'			 => 'Correction list',
						'status'		 => 'customerCorrection',
						'expired'		 => (new DateTime())->modify('+' . min(1, $parent->get('autopay')) . ' DAY')->format('Y-m-d H:i:s'),
						'conditions'	 => $r['correction_list'],
						'term'			 => $r['correction_term']
					]
				])->sendEmailNotification('rejected');

		$agreement->updatePartnerMilestones();

		return [
			'action' => 'completed'
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
