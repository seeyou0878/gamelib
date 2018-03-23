<?php

namespace Game;

class BooonGo
{
	public $api = array(
		'_url' => 'API網址',
		'_partner_id' => '代理用戶名',
		'_secret' => 'Secret Key',
		'_vendor_key' => 'Vendor Key',
	);

	function __construct()
	{
		$this->_lib = 'BooonGo';
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
			'partner_id' => $this->api['_partner_id'],
			'username' => $gcg_account,
			'password' => $gcg_password,
			'currency' => 'TWD',
		];
		$post['hash'] = @$this->getSignature($post);

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/game/user.html', json_encode($post));
		$result = json_decode($result, true);

		if(($result['error'] ?? null) === 0){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);

		$post = [
			'partner_id' => $this->api['_partner_id'],
			'username' => $gcg_account,
		];
		$post['hash'] = @$this->getSignature($post);

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/game/balance.html', json_encode($post));
		$result = json_decode($result, true);

		if(($result['error'] ?? null) === 0){
			return ['code' => 0, 'data' => $result['balance']];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function store_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);

		$post = [
			'partner_id' => $this->api['_partner_id'],
			'username' => $gcg_account,
			'currency' => 'TWD',
			'amount' => $credit,
			'ref_id' => $this->get_txn_id(),
		];
		$post['hash'] = @$this->getSignature($post);

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/game/credit.html', json_encode($post));
		$result = json_decode($result, true);

		if(($result['error'] ?? null) === 0){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function take_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);

		$post = [
			'partner_id' => $this->api['_partner_id'],
			'username' => $gcg_account,
			'currency' => 'TWD',
			'amount' => $credit,
			'ref_id' => $this->get_txn_id(),
		];
		$post['hash'] = @$this->getSignature($post);

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/game/debit.html', json_encode($post));
		$result = json_decode($result, true);

		if(($result['error'] ?? null) === 0){
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
			$arr_game = \Game\Report\BooonGo::get_game();
			$post = [
				'partner_id' => $this->api['_partner_id'],
				'token' => encrypt($gcg_account),
				'lang' => 'cn',
			];

			$url = $this->api['_url'] . '/open/' . ($arr_game[$type] ?? $arr_game[1]) . '?'. http_build_query($post);
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
		$username = decrypt(\Request::get('token')) ?? '';
		
		$result = [
			'error' => 0,
			'message' => 'Success',
			'username' => $username,
			'currency' => 'TWD',
			'ip' => '123.123.123.123',
		];

		return json_encode($result);
	}
	
	public function get_txn_id()
	{
		return date('YmdHis') . rand(100, 999);
	}

	function getSignature($post)
	{
		ksort($post);
		$arr = [];
		foreach($post as $k => $v){
		    $arr[] = $k . '=' . $v;
		}
		$hash = implode('&', $arr);		

		return md5($hash . $this->api['_secret']);
	}
}