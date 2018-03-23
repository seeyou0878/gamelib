<?php

namespace Game;

class PlayStar
{
	public $api = array(
		'_url' => 'API網址',
		'_host_id' => 'HostID',
	);

	function __construct()
	{
		$this->_lib = 'PlayStar';
	}

	public function init($gcg_account = '')
	{
		$this->api = \App::make('Lib\Mix')->get_agent_api($this->_lib, $gcg_account);
		return $this;
	}

	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();

		$post = [
			'host_id' => $this->api['_host_id'],
			'member_id' => $gcg_account,
		];

		$url = $this->api['_url'] . '/funds/createplayer/?' . http_build_query($post);
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		$result = json_decode($result, true);

		if(($result['status_code'] ?? null) === 0){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);

		$post = [
			'host_id' => $this->api['_host_id'],
			'member_id' => $gcg_account,
		];

		$url = $this->api['_url'] . '/funds/getbalance/?' . http_build_query($post);
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['status_code'] ?? null) === 0){
			return ['code' => 0, 'data' => ($result['balance'] ?? -1)/100];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function store_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);

		$post = [
			'host_id' => $this->api['_host_id'],
			'member_id' => $gcg_account,
			'txn_id' => $this->get_txn_id(),
			'amount' => $credit*100,
		];

		$url = $this->api['_url'] . '/funds/deposit/?' . http_build_query($post);
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		$result = json_decode($result, true);

		if(($result['status_code'] ?? null) === 0){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function take_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);

		$post = [
			'host_id' => $this->api['_host_id'],
			'member_id' => $gcg_account,
			'txn_id' => $this->get_txn_id(),
			'amount' => $credit*100,
		];

		$url = $this->api['_url'] . '/funds/withdraw/?' . http_build_query($post);
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		$result = json_decode($result, true);

		if(($result['status_code'] ?? null) === 0){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function login_game($gcg_account, $gcg_password)
	{
		$this->init($gcg_account);
		$type = \Request::get('type') ?? 0;
		
		$result = ['code' => 0];
		if($type){
			$arr_game = \Game\Report\PlayStar::get_game();
			$post = [
				'host_id' => $this->api['_host_id'],
				'game_id' => $arr_game[$type],
				'subgame_id' => 0,
				'lang' => 'zh-CN',
				'access_token' => encrypt($gcg_account),
			];

			$url = $this->api['_url'] . '/launch/?' . http_build_query($post);
			$result = @$this->get_balance($gcg_account);

			if(($result['code'] ?? null) === 0){

			}else{ //錯誤回傳
				return ['code' => 7, 'text' => ''];
			}
		}
		
		$result = [
			'url' => $url ?? '',
			'gameId' => $type,
		];

		return ['code' => 0, 'data' => $result];
	}

	public function login_check()
	{
		$member_id = decrypt(\Request::get('access_token')) ?? '';
		
		return [
			'status_code' => 0,
			'member_id' => $member_id,
		];
	}	
	
	public function get_txn_id()
	{
		return date('YmdHis') . rand(100, 999);
	}
}
