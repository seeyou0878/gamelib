<?php

namespace Game;

class NinetyNine
{
	public $api = array(
		'_url' => 'API網址',
		'_station' => '站台代號',
	);

	function __construct()
	{
		$this->_lib = 'NinetyNine';
	}

	public function init($gcg_account = '')
	{
		$this->api = \App::make('Lib\Mix')->get_agent_api($this->_lib, $gcg_account);
		return $this;
	}

	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();
		$parame = [
			'acc' => [
				[
					'acc' => $gcg_account,
					'name' => '',
					'lv' => 6,
					'extra' => [],
				]
			],
			'station' => $this->api['_station']
		];

		$post = [
			'code' => 'EXT_ADD',
			'parame' => json_encode($parame),
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'], $post);
		$result = json_decode($result, true);

		if(($result['status'] ?? null) === 1){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);

		$parame = [
			'station' => $this->api['_station'],
			'acc' => [$gcg_account],
			'lv' => 6,
		];

		$post = [
			'code' => 'EXT_GET_QUOTA',
			'parame' => json_encode($parame),
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'], $post);
		$result = json_decode($result, true);

		if(($result['status'] ?? null) === 1){
			return ['code' => 0, 'data' => $result['data'][$gcg_account] ?? -1];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function store_credit($gcg_account, $credit)
	{
		return $this->account_credit_transfer($gcg_account, 'in', $credit);
	}

	public function take_credit($gcg_account, $credit)
	{
		return $this->account_credit_transfer($gcg_account, 'out', $credit);
	}

	public function account_credit_transfer($gcg_account, $opeFlag, $credit)
	{
		$this->init($gcg_account);
		
		$parame = [
			'station' => $this->api['_station'],
			'acc' => $gcg_account,
			'gold' => $credit,
			'lv' => 6,
			'type' => $opeFlag,
		];

		$post = [
			'code' => 'EXT_BANK',
			'parame' => json_encode($parame),
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'], $post);
		$result = json_decode($result, true);

		if(($result['status'] ?? null) === 1){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function login_game($gcg_account, $gcg_password)
	{
		$this->init($gcg_account);

		$parame = [
			'acc' => $gcg_account,
			'lv' => '6',
			'station' => $this->api['_station'],
		];

		$post = [
			'code' => 'EXT_LOGIN',
			'parame' => json_encode($parame),
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'], $post);
		$result = json_decode($result, true);

		if(($result['status'] ?? null) === 1){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}
}