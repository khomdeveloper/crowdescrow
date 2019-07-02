<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Bug
 *
 * @author valera261104
 */
class Bug extends M {

	public static function action($r, $dontdie = false) {

		$com = empty($r[get_called_class()])
				? false
				: $r[get_called_class()];

		$author = User::logged();

		if ($com == 'send') {

			self::required([
				'description' => true
					], $r);

			//$present = self::presentObjects($r['description']);

			self::getBy([
				'id'		 => '_new',
				'_notfound'	 => [
					'author_id'		 => $author->get('id'),
					'description'	 => $r['description'],
					'normal'		 => self::normalize($r['description'], ' '),
					'status'		 => 'new',
					'created'		 => (new DateTime())->format('Y-m-d H:i:s')
				]
			]);

			return [
				'Bug' => [
					'out' => [
						'html' => self::getBugs([])
					]
				]
			];
		} elseif ($com == 'list') {
			return [
				'Bug' => [
					'out' => [
						'html' => self::getBugs($r)
					]
				]
			];
			
		} elseif ($com == 'paid') {
			
			if ($author->get('role') != 'admin') {
				throw new Exception('Only for admin');
			}
			
			$bug = self::getBy([
						'id'		 => $r['id'],
						'_notfound'	 => true
			]);
			
			if (in_array($bug->get('status'),['accepted','fixed'])){
				$bug->set([
					'paid' => (new DateTime())->format('Y-m-d H:i:s')
				]);
			}
			
			return [
				'Bug' => [
					'out' => [
						'html' => self::getBugs($r)
					]
				]
			];
			
		} elseif ($com == 'accept' || $com == 'reject' || $com == 'fixed') {
			if ($author->get('role') != 'admin') {
				throw new Exception('Only for admin');
			}

			$bug = self::getBy([
						'id'		 => $r['id'],
						'_notfound'	 => true
			]);

			$author = User::getBy([
						'id'		 => $bug->get('author_id'),
						'_notfound'	 => true
			]);

			if (in_array($com,['accept','fixed']) && in_array($bug->get('status'), ['new',
						'rejected',
						'spam',
						'reproduce'])) {
				$author->inc([
					'money' => 2
				]);
			} elseif ($com == 'reject' && in_array($bug->get('status'), ['accepted',
						'fixed']) && $author->d('money') >= 2 && !$bug->get('paid')) {
				$author->dec([
					'money' => 2
				]);
			}

			$bug->set([
				'status' => $com == 'accept'
						? 'accepted'
						: ($com == 'fixed' ? 'fixed' : 'rejected'),
				'price'	 => in_array($com,['accept','fixed'])
						? 2
						: 0
			]);

			return [
				'Bug' => [
					'out' => [
						'html' => self::getBugs($r)
					]
				]
			];
		}
	}

	public static function getBugs($r) {

		$user = User::logged();

		$total = self::getBy(empty($r['filter'])
								? [
							'status'	 => '!=spam',
							'_return'	 => 'count'
								]
								: [
							'status'		 => '!=spam',
							'description'	 => '%%' . $r['filter'],
							'_return'		 => 'count'
		]);

		if (empty($total)) {
			if (!empty($r['filter'])) {
				throw new Exception(T::out([
					'no_such_bugs_registered' => [
						'en' => 'No such bugs described',
						'ru' => 'Таких багов не описано'
					]
				]));
			} else {
				return false;
			}
		}

		$page = empty($r['page'])
				? 0
				: $r['page'];

		//$page =5;

		$screen = S::getBy([
					'key'		 => 'records_on_the_bug_page',
					'_notfound'	 => [
						'key'	 => 'records_on_the_bug_page',
						'val'	 => 3
					]
				])->d('val');

		$pages = ceil($total / $screen);

		$bugs = self::getBy(empty($r['filter'])
								? [
							'status'	 => '!=spam',
							'_order'	 => '`created` DESC',
							'_limit'	 => [
								$page * $screen,
								$screen
							],
							'_return'	 => [0 => 'object']
								]
								: [
							'status'		 => '!=spam',
							'description'	 => '%%' . $r['filter'],
							'_order'		 => '`created` DESC',
							'_limit'		 => [$page * $screen,
								$screen],
							'_return'		 => [0 => 'object']
		]);

		$template = H::getTemplate('pages/bug/list_item.php', [], true);

		$h = [];

		foreach ($bugs as $bug) {

			$h[] = self::parse($template, [
						'id'			 => $bug->get('id'),
						'description'	 => $bug->getDescription(),
						'answer'		 => $bug->get('answer') && (($bug->get('status') == 'new' && $bug->get('author_id') == $user->get('id')) || ($bug->get('status') != 'new'))
								? $bug->fget('answer') . '<br/><a href="/contacts" style="color:dodgerblue;" onclick="Site.switchTo(' . "'contacts'''" . '); return false;">Answer</a>'
								: '',
						'status'		 => $bug->getStatus() . ($bug->get('status') == 'new' && $bug->get('author_id') == $user->get('id')
								? ' (' . T::out([
									'bug_is_visible_only_for_your_while_moderated' => [
										'en' => 'while moderated visible only for you',
										'ru' => 'до модерации видите только вы'
									]
								]) . ')'
								: ''),
						'buttons'		 => $bug->getButtons(),
						'date'			 => explode(' ', $bug->get('created'))[0]
							], true);
		}

		$ph = [];
		for ($i = 0; $i < $pages; $i++) {
			$ph[] = '<span class="goto_page present_bugs id_' . $i . ' ' . ($i == $page
							? 'current'
							: '') . '">' . ($i + 1) . '</span>';
		}

		$return = join('<div class="bug_separator"></div>', $h);

		return $return . '<div class="help ac">' . join('', $ph) . '</div>';
	}

	/**
	 * Нормализует текст для сравнения левинштейном
	 * @param type $text
	 */
	public static function normalize($text, $delimeter = ' ') {

		$text = preg_replace("/[^a-zA-Z0-9\s]/", '', str_replace(["\r\n",
			"\r",
			"\n"], '', preg_replace('/\s+/', ' ', strip_tags($text))));

		$a = explode(' ', $text);

		$b = [];

		foreach ($a as $e) {
			if (mb_check_encoding($e, 'ASCII') && strlen($e) > 3) {
				$b[] = $e;
			}
			/*
			  if (!mb_check_encoding($e, 'ASCII')) {
			  throw new Exception('Please use only ASCII symbols in message!');
			  } */
		}

		sort($b);

		return $delimeter === false
				? array_unique($b)
				: join(',', array_unique($b));

		//return substr(join('',$b), 0, 255);
	}

	/*
	  public static function presentObjects($text) {

	  $a = self::normalize($text, false);

	  $sql = [];

	  foreach ($a as $str) {
	  $sql[] = "`normal` like '% " . urlencode($str) . " %'";
	  }

	  $screen = S::getBy([
	  'key'		 => 'records_on_the_bug_page',
	  '_notfound'	 => [
	  'key'	 => 'records_on_the_bug_page',
	  'val'	 => 10
	  ]
	  ])->d('val');

	  $countSQL = "select count(*) as `count` from `escrow_bug` where " . join(' or ', $sql);

	  $db = Yii::app()->db->createCommand($countSQL)->query([]);

	  if (!empty($db)) {
	  while (($row = $db->read()) != false) {
	  $pages = ceil($row['count'] / $screen);
	  $count = $row['count'];
	  }
	  }

	  echo $count;
	  } */

	public function getButtons() {
		$user = User::logged();

		if ($user->get('role') == 'admin') {
			return join('', [
				H::getTemplate('pages/dialogs/button', [
					'title'		 => 'Accept the bug',
					'class'		 => 'accept_bug_button',
					'id'		 => $this->get('id'),
					'icon'		 => 'fa-check',
					'background' => 'mediumseagreen',
					'color'		 => 'white'
						], true),
				H::getTemplate('pages/dialogs/button', [
					'title'		 => 'Reject the bug',
					'class'		 => 'reject_bug_button',
					'id'		 => $this->get('id'),
					'icon'		 => 'fa-times',
					'background' => 'tomato',
					'color'		 => 'white'
						], true),
				H::getTemplate('pages/dialogs/button', [
					'title'		 => 'Paid bug',
					'class'		 => 'paid_bug_button',
					'id'		 => $this->get('id'),
					'icon'		 => 'fa-usd',
					'background' => 'gold',
					'color'		 => 'white'
						], true),
				H::getTemplate('pages/dialogs/button', [
					'title'		 => 'Fixed bug',
					'class'		 => 'fixed_bug_button',
					'id'		 => $this->get('id'),
					'icon'		 => 'fa-bug',
					'background' => 'dodgerblue',
					'color'		 => 'white'
						], true),
				'<div>author:' . $this->get('author_id') . '</div>'
			]);
		} else {
			return false;
		}
	}

	public function getDescription() {

		$user = User::logged();

		$result = $this->fget('description');

		/*

		  $maxwide = 300;

		  if (mb_strwidth($str) > $maxwide) {
		  $hidden = mb_strimwidth($str, $maxwide, mb_strwidth($str) - $maxwide, '');
		  $result = mb_strimwidth($str, 0, $maxwide, '') . '<span class="show_hidden"> ...</span>';
		  $result .= '<span style="display:none;" class="hidden_part">' . $hidden . '</span>';
		  } else {
		  $result = $str;
		  }

		 */

		return trim($this->get('status') == 'new' && $this->get('author_id') != $user->get('id') && $user->get('role') != 'admin'
						? '<div style="color:lightyellow;" class="ac">' . T::out([
							'bug_not_yet_moderated' => [
								'en' => 'Will be visible after moderation',
								'ru' => 'Будет доступен после модерации'
							]
						]) . '</div>'
						: nl2br($result));
	}

	public function getStatus() {

		switch ($this->get('status')) {
			case 'new':
			case 'sent':
				return '<span style="color:white;">' . T::out([
							'new (bug_status)' => [
								'en' => 'New bug',
								'ru' => 'Новый баг'
							]
						]) . '</span>';
				break;
			case 'accepted':
			case 'paid':
				return '<span style="color:springgreen;">' . T::out([
							'acccepted (bug_status)' => [
								'en'		 => 'Accepted ({{amount}})',
								'ru'		 => 'Признан ({{amount}})',
								'_include'	 => [
									'amount' => '<span style="color:gold;">' . $this->get('price') . '$</span>' .
									($this->get('paid')
											? '<span style="color:springgreen;"> (paid)</span>'
											: '')
								]
							]
						]) . '</span>';
				break;
			case 'rejected':
				return '<span style="color:tomato;">' . T::out([
							'not the bug (bug_status)' => [
								'en' => 'This is not a bug',
								'ru' => 'Это не баг'
							]
						]) . '</span>';
				break;
			case 'fixed':
				return '<span style="color:dodgerblue;">' . T::out([
							'fixed (bug_status)' => [
								'en'		 => 'Fixed ({{amount}})',
								'ru'		 => 'Исправлен ({{amount}})',
								'_include'	 => [
									'amount' => '<span style="color:gold;">' . $this->get('price') . '$</span>' .
									($this->get('paid')
											? '<span style="color:springgreen;"> (paid)</span>'
											: '')
								]
							]
						]) . '</span>';
				break;
			case 'reproduce':
				return '<span style="color:yellow;">' . T::out([
							'can_t reproduce (bug_status)' => [
								'en' => 'We can not reproduce',
								'ru' => 'Не можем воспроизвести'
							]
						]) . '</span>';
				break;
		};
		//'fixed'
	}

	public static function f() {
		return [
			'title'		 => 'Bug report',
			'datatype'	 => [
				'author_id' => [
					'User' => [
						'id' => ' ON DELETE CASCADE '
					]
				]
			],
			'create'	 => [
				'author_id'		 => "bigint unsigned default null comment 'Link to finder'",
				'description'	 => "text comment 'Bug description'",
				'answer'		 => "text comment 'Answer'",
				'normal'		 => "text comment 'Normalized text'",
				'created'		 => "datetime default null comment 'Date of bag created'",
				'price'			 => "float default 0 comment 'Price of report'",
				'paid'			 => "datetime default null comment 'Paid date'",
				'status'		 => "enum('new', 'sent', 'accepted', 'rejected', 'repeated', 'fixed', 'spam', 'reproduce') default 'new' comment 'Report status'"
			]
		];
	}

	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
