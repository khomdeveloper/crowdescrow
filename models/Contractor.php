<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Contractor
 *
 * @author valera261104
 */
class Contractor extends User {

	public function getAgreement($agreement_id) {

		return Agreement::getBy([
					'id'		 => $agreement_id,
					'status'	 => 'accepted',
					'_notfound'	 => true
		]);
	}

	public static function editCorrectionConditions($r) {

		self::required([
			'id'				 => true,
			'agreement_id'		 => true,
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

		$agreement = Agreement::getBy([
					'id'		 => $r['agreement_id'],
					'status'	 => 'accepted',
					'_notfound'	 => true
		]);

		$milestone = Milestone::getBy([
					'id'			 => $r['id'],
					'agreement_id'	 => $agreement->get('id'),
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

		if ($milestone->get('status') == $agreement->get('role') . 'Correction') {//edit self correction		
			$milestone->set([
				'conditions' => $r['correction_list'],
				'term'		 => $r['correction_term'],
				'expired'	 => (new DateTime())->modify('+' . min(1, Milestone::getBy([
									'id'		 => $milestone->get('milestone_id'),
									'_notfound'	 => true
								])->get('autopay')) . ' DAY')->format('Y-m-d H:i:s')
			])->sendEmailNotification('edit_correction_conditions', [
				'side' => $agreement->get('role')
			]);
		} elseif ($milestone->get('status') == $agreement->get('counterPartyRole') . 'Correction') {

			$milestone->set([
				'conditions' => $r['correction_list'],
				'term'		 => $r['correction_term'],
				'status'	 => $milestone->get('role') . 'Correction',
				'expired'	 => (new DateTime())->modify('+' . min(1, Milestone::getBy([
									'id'		 => $milestone->get('milestone_id'),
									'_notfound'	 => true
								])->get('autopay')) . ' DAY')->format('Y-m-d H:i:s')
			])->sendEmailNotification('edit_correction_conditions', [
				'side' => $agreement->get('role')
			]);
		} else {
			throw new Exception('Not expected status ' . $milestone->get('status'));
		}

		$agreement->updatePartnerMilestones();

		return [
			'action' => 'completed'
		];
	}

	public function acceptCorrectionConditions($r, $auto = false) {

		self::required([
			'id'			 => true,
			'agreement_id'	 => true,
				], $r);

		$agreement = $this->getAgreement($r['agreement_id']);

		if (empty($auto)) {
			$agreement->updatePartnerMilestones();
		}


		$milestone = Milestone::getBy([
					'id'			 => $r['id'],
					'agreement_id'	 => $agreement->get('id'),
					'status'		 => ['sellerCorrection',
						'customerCorrection'],
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

		$status = $milestone->get('status');

		$milestone->set([
			'status'	 => 'funded',
			'start'		 => (new DateTime())->format('Y-m-d H:i:s'),
			'deadline'	 => (new DateTime())->modify("+" . $milestone->get('term') . " DAY")->format('Y-m-d H:i:s'),
		])->sendEmailNotification('acceptCorrectionConditions', [
			'side'	 => $status == 'customerCorrection'
					? 'seller'
					: 'customer',
			'auto'	 => $auto
		])->sendEmailNotification(empty($auto)
						? false
						: 'acceptCorrectionConditions', [
			'side'	 => $status == 'customerCorrection'
					? 'customer'
					: 'seller',
			'auto'	 => $auto
		]);

		if (empty($auto)) {
			return Milestone::getList($r['agreement_id'], $agreement->get('role'));
		} else {
			return true;
		}
	}

	public static function satisfyChallenge($r, $auto = false) {

		self::required([
			'id'	 => true,
			'amount' => true
				], $r);

		$milestone = Milestone::getBy([
					'id'		 => $r['id'],
					'status'	 => 'disputed',
					'_notfound'	 => true
		]);

		$refund = min(max(0, $r['amount']), $milestone->d('funded'));

		if ($milestone->get('seller_claim') && (!empty($auto) || $milestone->get('iam') == 'customer')) { //release
			Customer::getBy([
				'id'		 => $milestone->get('Agreement')->get('customer_id'),
				'_notfound'	 => true
			])->releaseMilestone([
				'id'			 => $milestone->get('id'),
				'agreement_id'	 => $milestone->get('agreement_id'),
				'amount'		 => $refund
					], 'auto');
		} elseif ($milestone->get('customer_claim') && (!empty($auto) || $milestone->get('iam') == 'seller')) {
			Seller::getBy([
				'id'		 => $milestone->get('Agreement')->get('seller_id'),
				'_notfound'	 => true
			])->cancelWork([
				'id'			 => $milestone->get('id'),
				'agreement_id'	 => $milestone->get('agreement_id'),
				'amount'		 => $refund
					], $refund, 'auto');
		} else {
			throw new Exception('Wrong milestone claim status');
		}

		return [
			'claim'			 => 'partially_satisfied',
			'agreement_id'	 => $milestone->get('agreement_id')
		];
	}

	public static function runChallenge($r) {

		self::required([
			'id'	 => true,
			'reason' => true
				], $r);

		$user = User::logged();

		$milestone = Milestone::getBy([
					'id'		 => $r['id'],
					'status'	 => [
						'funded',
						'completed',
						'rejected',
						'disputed'
					],
					'_notfound'	 => true
		]);

		if ($milestone->get('isExpired') === true) {
			throw new Exception(T::out([
				'error_milestone_expired' => [
					'en' => 'Operation unavailable because milestone status is changing',
					'ru' => 'Операция недоступна потому что меняется статус этапа'
				]
			]));
		}

		$iam = $milestone->get('iam');

		if (!$milestone->get('customer_claim') && !$milestone->get('seller_claim')) { //new claim
			$milestone->set([
				$iam . '_claim'	 => $r['reason'],
				'status'		 => 'disputed',
				'expired'		 => (new DateTime())->modify('+' . min(1, $milestone->get('autopay')) . ' DAY')->format('Y-m-d H:i:s')
			])->get('Agreement')->updatePartnerDeals()->updatePartnerMilestones();

			return [
				'challenge'		 => 'set',
				'agreement_id'	 => $milestone->sendEmailNotification('challenge')->get('agreement_id'),
				'milestone_id'	 => $milestone->get('id')
			];
		} elseif ($milestone->get('customer_claim') && $milestone->get('seller_claim')) {
			throw new Exception([
		'challenge_mechanism_run' => [
			'en' => 'Challenge mechanism already run',
			'ru' => 'Механизм оспаривания уже запущен'
		]
			]);
		} elseif ($milestone->get($iam . '_claim')) { //edit self claim
			//TODO: it <- не совсем понятно откуда это будет вызываться
		} elseif (!$milestone->get($iam . '_claim')) { //counter claim
			$milestone->set([
				$iam . '_claim'	 => $r['reason'],
				'status'		 => 'arbitrage',
				'expired'		 => null
			])->get('Agreement')->updatePartnerDeals()->updatePartnerMilestones();

			return [
				'counter_claim'	 => 'set',
				'agreement_id'	 => $milestone->sendEmailNotification('counter_claim', [
					'side' => $iam
				])->get('agreement_id'),
				'milestone_id'	 => $milestone->get('id')
			];
		}
	}

	public static function cancelChallenge($r) {
		self::required([
			'id' => true
				], $r);

		$user = User::logged();

		$milestone = Milestone::getBy([
					'id'		 => $r['id'],
					'status'	 => 'disputed',
					'_notfound'	 => true
		]);

		if ($milestone->get('isExpired') === true) {
			throw new Exception(T::out([
				'error_milestone_expired' => [
					'en' => 'Operation unavailable because milestone status is changing',
					'ru' => 'Операция недоступна потому что меняется статус этапа'
				]
			]));
		}

		if ($milestone->get($milestone->get('iam') . '_claim') && !$milestone->get($milestone->get('contragentRole') . '_claim')) {
			$milestone->set([
				'status'							 => $milestone->get('hasCorrectionMilestones')
						? 'rejected'
						: ($milestone->get('completed') == 100
								? 'completed'
								: 'funded'),
				$milestone->get('iam') . '_claim'	 => null,
				'expired'							 => (new DateTime())->modify('+' . min(1, $milestone->get('autopay')) . ' DAY')->format('Y-m-d H:i:s')
			])->get('Agreement')->updatePartnerDeals()->updatePartnerMilestones();
		} else {
			throw new Exception(T::out([
				'unable_to_cancel_the_claim' => [
					'en' => 'Can not cancel the claim',
					'ru' => 'Невозможно отменить претензию'
				]
			]));
		}

		return [
			'challenge'		 => 'cancelled',
			'agreement_id'	 => $milestone->get('agreement_id'),
			'milestone_id'	 => $milestone->get('id')
		];
	}

	public static function showChallengeDialog($r) {

		self::required([
			'id' => true
				], $r);

		$milestone = Milestone::getBy([
					'id'		 => $r['id'],
					'status'	 => [
						'funded',
						'completed',
						'rejected',
						'disputed'
					],
					'_notfound'	 => true
		]);

		if ($milestone->get('isExpired') === true) {
			throw new Exception(T::out([
				'error_milestone_expired' => [
					'en' => 'Operation unavailable because milestone status is changing',
					'ru' => 'Операция недоступна потому что меняется статус этапа'
				]
			]));
		}

		$iam = $milestone->get('iam'); //customer or seller

		$user = User::logged();

		if ((!$milestone->get('customer_claim') && !$milestone->get('seller_claim')) ||
				(!$milestone->get($iam . '_claim') && !empty($r['counter_claim']))) { //new or counter claim
			return [
				'Milestone' => [
					'showChargeBackDialog' => [
						'milestone_id'	 => $r['id'],
						'agreement_id'	 => $milestone->get('agreement_id'),
						'html'			 => H::getTemplate('pages/customer/run_challenge', [
							'user_id'		 => $user->get('id'),
							'milestone_id'	 => $r['id'],
							'uploaded_files' => join('', $user->uploadedFilesHTML())
								], true)
					]
				]
			];
		} elseif ($milestone->get('customer_claim') && $milestone->get('seller_claim')) {
			throw new Exception([
		'challenge_mechanism_run' => [
			'en' => 'Challenge mechanism already run',
			'ru' => 'Механизм оспаривания уже запущен'
		]
			]);
		} elseif ($milestone->get($iam . '_claim')) { //edit self claim
			//TODO: it <- не совсем понятно откуда это будет вызываться
		} elseif (!$milestone->get($iam . '_claim')) { //counter claim
			return [
				'Milestone' => [
					'viewClaimDialog' => [
						'milestone_id'	 => $r['id'],
						'agreement_id'	 => $milestone->get('agreement_id'),
						'html'			 => H::getTemplate('pages/customer/view_claim', [
							'user_id'		 => $user->get('id'),
							'amount'		 => $milestone->get('funded'),
							'claim'			 => $milestone->fget(($iam === 'seller'
											? 'customer'
											: 'seller') . '_claim'),
							'remain'		 => ucfirst(strip_tags($milestone->fundedStatus())),
							'milestone_id'	 => $r['id'],
							'milestone'		 => $milestone->getShortDescription()
								], true)
					]
				]
			];
		}

		throw new Exception('Uncknown claim status');
	}

	public static function restartCanceled($r) {

		self::required([
			'id'			 => true,
			'agreement_id'	 => true
				], $r);

		$agreement = Agreement::getBy([
					'id'		 => $r['agreement_id'],
					'status'	 => 'accepted',
					'_notfound'	 => true
		]);

		$milestone = Milestone::getBy([
					'agreement_id'	 => $agreement->get('id'),
					'id'			 => $r['id'],
					'status'		 => 'canceled',
					'_notfound'		 => true
		]);

		$children = Milestone::getBy([
					'milestone_id'	 => $milestone->get('id'),
					'_return'		 => 'count'
		]);

		if (empty($children)) {
			$milestone->set([
				'status'	 => 'waiting',
				'completed'	 => $milestone->d('completed') == 100
						? 90
						: $milestone->d('completed')
			]);
		} else {
			$milestone->set([
				'status' => 'rejected'
			]);
		}

		//update milestones list when customer view
		$agreement->updatePartnerMilestones()->updatePartnerDeals();

		return [
			'work' => 'resumed'
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
