<?php

/* */

class Action extends M {

	public static function f() {
		return [
			'title'	 => 'Possible actions on observe',
			'create' => [
				'action' => "text comment 'Action description in JSON'",
				'remove' => "enum ('auto','read') deafult 'auto' comment 'Remove mode'",
				'key'	 => "tinytext comment 'Action shortkey'"
			]
		];
	}

	public static function tryToRead($input, $user) {

		$actions = Action::getBy([
					'key'		 => $input,
					'_return'	 => [
						0 => 'object'
					],
					'_notfound'	 => true
		]);

		if (!empty($actions)) {
			foreach ($actions as $action) {
				$action->readBy($user);
			}
		}
	}

	public function readBy($user) {
		if (UserAction::getBy([
					'user_id'	 => $user->get('id'),
					'action_id'	 => $this->get('id'),
					'_return'	 => 'count'
				]) > 0) {
			UserAction::getBy([
				'user_id'	 => $user->get('id'),
				'action_id'	 => $this->get('id')
			])->remove();
		}
	}

	public function setFor($user_id) {

		if (empty($user_id)) {
			return $this;
		}

		if (is_array($user_id)) {

			foreach ($user_id as $id) {
				UserAction::getBy([
					'user_id'	 => $id,
					'action_id'	 => $this->get('id'),
					'_notfound'	 => [
						'user_id'	 => $id,
						'action_id'	 => $this->get('id')
					]
				]);
			}
		} else {

			UserAction::getBy([
				'user_id'	 => $user_id,
				'action_id'	 => $this->get('id'),
				'_notfound'	 => [
					'user_id'	 => $user_id,
					'action_id'	 => $this->get('id')
				]
			]);
		}

		return $this;
	}

	public static function standart($key) {


		if ($key == 'update_milestones') {
			return Action::getBy([
						'key'		 => 'update_milestones',
						'_notfound'	 => [
							'key'	 => 'update_milestones',
							'action' => json_encode([
								'Milestone' => [
									'updateList' => [
										'data' => 'none'
									]
								]
							])
						]
			]);
		} elseif ($key == 'update_deals') {
			return Action::getBy([
						'key'		 => 'update_deals',
						'_notfound'	 => [
							'key'	 => 'update_deals',
							'action' => json_encode([
								'Agreement' => [
									'list' => [
										'data' => 'none'
									]
								]
							])
						]
			]);
		} elseif ($key == 'update_balance') {
			return Action::getBy([
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
			]);
		}
	}

	public function get($what, $data = null) {
		if ($what == 'decodeAction') {

			$action = $this->get('action');

			if (empty($action)) {
				return [];
			}

			if (is_array($action)) {
				return $action;
			}

			return json_decode($this->get('action'), true);
		}

		return parent::get($what, $data);
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
