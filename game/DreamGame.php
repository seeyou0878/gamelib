<?php

namespace Game;

class DreamGame
{
	public $api = [
		'_url' => 'API網址',
		'_agent' => '代理用戶名',
		'_api_key' => 'APIKey',
	];

	function __construct()
	{
		$this->_lib = 'DreamGame';
	}

	public function init($gcg_account = '')
	{
		$this->api = \App::make('Lib\Mix')->get_agent_api($this->_lib, $gcg_account);
		return $this;
	}

	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();
		$acc = $this->api['_agent'];
		$key = md5($acc . $this->api['_api_key']);
		
		$post = [
			'token' => $key,
			'data' => 'D',
			'member' => [
				'username' => $gcg_account,
				'password' => md5($gcg_password),
				'currencyName' => 'TWD', //台幣
				'winLimit' => 0,
			]
		];
		$url = $this->api['_url'] . "/user/signup/{$acc}/";
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, json_encode($post));
		$result = json_decode($result, true);

		if(($result['codeId'] ?? false) === 0){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'data' => ''];
		}
	}

	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);
		$acc = $this->api['_agent'];
		$key = md5($acc . $this->api['_api_key']);
		
		$post = [
			'token' => $key,
			'member' => [
				'username' => $gcg_account,
			]
		];
		
		$url = $this->api['_url'] . "/user/getBalance/{$acc}";
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, json_encode($post));
		$result = json_decode($result, true);

		if(($result['codeId'] ?? false) === 0){
			return ['code' => 0, 'data' => $result['member']['balance'] ?? []];
		}else{
			return ['code' => 7, 'data' => ''];
		}
	}

	public function store_credit($gcg_account, $credit)
	{
		return $this->alter_credit($gcg_account, abs($credit));
	}

	public function take_credit($gcg_account, $credit)
	{
		return $this->alter_credit($gcg_account, abs($credit) * -1);
	}

	private function alter_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);
		$acc = $this->api['_agent'];
		$key = md5($acc . $this->api['_api_key']);
		$serial = $this->_get_serial();
		
		$post = [
			'token' => $key,
			'data' => $serial,
			'member' => [
				'username' => $gcg_account,
				'amount' => $credit,
			]
		];
		
		$url = $this->api['_url'] . "/account/transfer/{$acc}";
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, json_encode($post));
		$result = json_decode($result, true);

		if(($result['codeId'] ?? false) === 0){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'data' => ''];
		}
	}

	public function login_game($gcg_account, $gcg_password)
	{
		$this->init($gcg_account);
		$acc = $this->api['_agent'];
		$key = md5($acc . $this->api['_api_key']);
		
		$post = [
			'token' => $key,
			'lang' => 'tw',
			'member' => [
				'username' => $gcg_account,
				'password' => $gcg_password,
			]
		];

		$url = $this->api['_url'] . "/user/login/{$acc}";
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, json_encode($post));
		$result = json_decode($result, true);

		if(($result['codeId'] ?? false) === 0){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'data' => ''];
		}
	}

	//取得不可重複隨機編號
	protected function _get_serial()
	{
		return '88bk' . date('YmdHis') . rand(100, 999);
	}
}