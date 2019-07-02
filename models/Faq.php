<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Faq
 *
 * @author valera261104
 */
class Faq extends M{
	
	public static function f(){
		return [
			'title' => 'Frequently Asked Questions',
			'datatype' => [
				'description' => [
					'T' => [
						'id' => true
					]
				],
				'group' => [
					'T' => [
						'id' => true
					]
				],
				'title'		 => [
					'T' => [
						'id' => true
					]
				]
			],
			'create' => [
				'key' => "tinytext default null comment 'Key access'",
				'title'		 => "bigint unsigned default null comment 'Link to title'",
				'description' => "bigint unsigned default null comment 'Link to description'",
				'group' => "bigint unsigned default null comment 'Group'",
				'data' => "text default null comment 'Data to parse'",
				'order' => "int default 0 comment 'Sort order'"
			]
		];
	}
	
	public static function export(){
		
		$all_faq = self::getBy([
			'_all' => [0 => 'object']
		]);
		
		$h = [];
		foreach ($all_faq as $faq){
			$h[] = [
				'key' => $faq->get('key'),
				'title' => T::getBy([
					'id' => $faq->get('title','raw')
				 ])->get('key'),
				'description' => T::getBy([
					'id' => $faq->get('description','raw')
				 ])->get('key'),
				'data' => $faq->get('data'),
				'order' => $faq->get('order')
			];
		}
		
		//echo ;
		
		file_put_contents(self::getExportFileName(), json_encode($h));
		
	}
	
	public static function getExportFileName(){
		return Environment::addTheSlash(Environment::get('site_root')) . 'images/faq.json';
	}
	
	public static function import(){
		
		if (file_exists(self::getExportFileName())){
			
			$data = json_decode(file_get_contents(self::getExportFileName()), true);
			
			//print_r($data);
			
			foreach ($data as $faq){
			
				//echo $faq['title'] . '<p>';
				
				Faq::getBy([
					'key' => empty($faq['key']) ? '_never' : $faq['key'],
					'_notfound' => [
						'key' => empty($faq['key']) ? md5(microtime()) : $faq['key'],
						'data' => empty($faq['data']) ? '' : json_encode($faq['data']),
						'order'=> $faq['order'],
						'title'=> T::getBy([
							'key' => $faq['title'],
							'_notfound' => [
								'key' => $faq['title'],
								'en' => 1,
								'ru' => 1
							]
						])->get('id'),
						'description'=> T::getBy([
							'key' => $faq['description'],
							'_notfound' => [
								'key' => $faq['description'],
								'en' => 1,
								'ru' => 1
							]
						])->get('id')
					]
				]);
				
			}		
					
		}
		
	}
	
	public function get($what, $data = false){
		if ($what == 'description' && $this->get('data')){
			$description = parent::get($what, $data);
			return self::parse($description,$this->get('data'),'addDelimeters');	
		}
		
		return parent::get($what, $data);
	}
	
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}
	
	public function setIfnull($what){
		if ($this->get(key($what))){
			return $this;
		} else {
			return $this->set($what);
		}
	}
}
