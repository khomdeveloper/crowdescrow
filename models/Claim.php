<?php

/**
 * Description of Claim
 *
 * @author valera261104
 */
class Claim extends Offer {

	public static function getTable() {
		return 'escrow_offer';
	}

	function tableName() {
		return 'escrow_offer';
	}

	public function getExperts() {

		$experts = Expert::getBy([
					'claim_id'	 => $this->get('id'),
					'user_id'	 => '!=0',
					'seller'	 => ['ok',
						'na'],
					'customer'	 => ['ok',
						'na'],
					'expert'	 => ['ok',
						'na'],
					'_return'	 => [
						0 => 'object'
					]
		]);

		if (empty($experts)) {

			return '<div style="margin:10px 0px;">' . T::out([
						'please_invite_experts' => [
							'en'		 => 'Press {{button}} to invite experts',
							'ru'		 => 'Нажмите {{button}} чтобы пригласить экспертов',
							'_include'	 => [
								'button' => '<i class="fa fa-user-plus" aria-hidden="true" style="font-size:1rem;"></i>'
							]
						]
					]) . '</div>';
		}

		$t = H::getTemplate('pages/arbitrage/expert_line.php', [], true);

		$h = ['<table>'];

		foreach ($experts as $expert) {

			$h[] = H::parse($t, [
						'style'		 => '',
						'class'		 => '',
						'name'		 => '<a href="a" onclick="return false;">' . User::getBy([
							'id'		 => $expert->get('user_id'),
							'_notfound'	 => true
						])->get('name') . '</a>',
						'price'		 => ($expert->get('expert') == 'ok'
								? ($expert->get('seller') == 'ok' && $expert->get('customer') == 'ok'
										? (
										$expert->get('result') == 'na'
												?
												T::out([
													'expert_is_ready (in_expert_list)' => [
														'en' => 'ready for expertise',
														'ru' => 'готов к экспертизе'
													]
												])
												: T::out([
													'expert_voted (in_expert_list)' => [
														'en' => 'voted',
														'ru' => 'проголосовал'
													]
												])
										)
										: T::out([
											'expert_vote_price (inline)' => [
												'en'		 => 'price {{price}}$',
												'ru'		 => 'цена {{price}}$',
												'_include'	 => [
													'price' => round($expert->d('price'), 2)
												]
											]
										]))
								: T::out([
									'wait_for_decision (inline)' => [
										'en' => 'not agree yet',
										'ru' => 'пока не согласился'
									]
								])),
						'buttons'	 => $expert->getButtonsInLine()
							], true);
		}

		$h[] = '</table>';

		return join('', $h);
	}

	public function getHTML($template = null, $noexperts = false) {

		if (empty($template)) {
			$template = H::getTemplate('pages/arbitrage/' . ($this->get('currency') == '_deal'
									? 'deal'
									: 'balance') . '_claim', [], true);
		}

		$obj = $this->get('currency') == '_deal'
				? Deal::getBy([
					'id' => $this->get('id')
				])
				: Offer::getBy([
					'id' => $this->get('id')
		]);

		$paymentMethod = $this->get('UserPaymentMethod');

		if (!empty($noexperts) && is_numeric($noexperts)) {
			$expert = Expert::getBy([
						'id'		 => $noexperts,
						'_notfound'	 => true
			]);
		} else {
			$expert = false;
		}

		$max = round(S::getBy([
					'key'		 => 'max_expertise_royalty_percent',
					'_notfound'	 => [
						'key'	 => 'max_expertise_royalty_percent',
						'val'	 => 2
					]
				])->d('val') * $this->get('amount') / 100, 2);

		$expert_number = empty($max)
				? 0
				: floor(min($this->d('seller_hold'), $this->d('customer_hold')) / $max);

		$seller = User::getBy([
					'id'		 => $this->get('user_id'),
					'_notfound'	 => true
		]);

		$customer = User::getBy([
					'id'		 => $this->get('accepted_by'),
					'_notfound'	 => true
		]);

		return H::parse($template, [
					'header'				 => empty($noexperts)
							? H::getTemplate('pages/arbitrage/claim_header_sides', [
								'offer_id'	 => $this->get('id'),
								'class'		 => $this->get('currency') == '_deal'
										? 'deal_admit_claim'
										: 'cancel_chargeback',
									], true)
							: (is_numeric($noexperts)
									? H::getTemplate($expert->get('expert') == 'na'
													? 'pages/arbitrage/claim_header_wait'
													: 'pages/arbitrage/claim_header_expert', [
										'status'	 => $expert->getClaimStatus($this),
										'expert_id'	 => $expert->get('id')
											], true)
									: ''),
					'new_experts_count'		 => empty($expert_number)
							? H::getTemplate('pages/arbitrage/no_more_experts', [], true)
							: H::getTemplate('pages/arbitrage/new_experts_count', [
								'offer_id'		 => $this->get('id'),
								'new_experts'	 => $expert_number
									], true),
					'offer_id'				 => $this->get('id'),
					'title'					 => $obj->getShortDescription() . ($this->get('currency') == '_deal' && $this->get('reconfirm')
							? H::getTemplate('pages/deal/balance_claim_corrections', [
								'corrections' => $obj->chat()
									], true)
							: ''),
					'seller'				 => $seller->get('name'),
					'seller_id'				 => $seller->get('id'),
					'date'					 => $this->get('changed'),
					'seller_claim'			 => $this->get('seller_claim'),
					'customer'				 => $customer->get('name'),
					'customer_id'			 => $customer->get('id'),
					'customer_claim'		 => $this->get('customer_claim'),
					'details'				 => '<div class="al payment_detales">' . nl2br($paymentMethod->fget('description')) . '</div>',
					'price'					 => $this->get('price') . '$',
					'mode'					 => $paymentMethod->get('mode'),
					'screenshots'			 => join('', $obj->uploadedFilesHTML()),
					'seller_vote_button'	 => ( empty($expert)
							? ''
							: $expert->getVoteButtons('seller', $this)),
					'customer_vote_button'	 => empty($expert)
							? ''
							: $expert->getVoteButtons('customer', $this),
					'experts_header'		 => is_numeric($noexperts) && $expert->get('expert') == 'na'
							? ''
							: T::out([
								'invited_experts2' => [
									'en' => 'Invited experts',
									'ru' => 'Приглашенные эксперты'
								]
							]),
					'logs'					 => $this->outputEvents(),
					'experts'				 => empty($noexperts)
							? $this->getExperts()
							: (is_numeric($noexperts) && $expert->get('expert') != 'na'
									? ($expert->get('result') == 'na'
											? '<table>' . H::getTemplate('pages/arbitrage/expert_line.php', [
												'style'		 => '',
												'class'		 => '',
												'name'		 => T::out([
													'you_agree_for (in_exepertise_list2)' => [
														'en' => 'You agreed for',
														'ru' => 'Вы согласились за',
													]
												]),
												'price'		 => Expert::getBy([
													'id'		 => $noexperts,
													'_notfound'	 => true
												])->get('price') . '$',
												'buttons'	 => '<div class="pr round_button_host" style="float:right; display:inline-block; margin-top:5px;">
					<div class="pa cp decline_this_expert id_' . $noexperts . ' round_button" style="background:tomato; margin:0px;">
						<i class="fa fa-times" style="margin-top:0px; font-size:1.2rem; color:white;"></i>
					</div>
				</div>'
													], true) . '</table>'
											: T::out([
												'you_have_already_vote' => [
													'en' => 'You have already vote',
													'ru' => 'Вы уже проголосовали'
												]
											])
									)
									: '')
						], true);
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
