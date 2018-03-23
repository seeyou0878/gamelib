<?php

namespace Game;

class XinXin
{
	public $api = [
		'_account_id' => 'AT7656',
		'_api_key' => 'test|BK',
		'_url' => 'http://kym.sxb168.com',
	];

	function __construct()
	{
		//正式
		// $this->_url = 'http://kym.sxb168.com/api';
		// $this->_api_key = 'test|BK';
		// $this->_account_id = 'AT7656';
		// $this->_security_code = $this->get_header_key();
		
		//測試
		$this->_lib = 'XinXin';
	}

	public function init($gcg_account = '')
	{
		$this->api = \App::make('Lib\Mix')->get_agent_api($this->_lib, $gcg_account);
		$this->_security_code = $this->get_header_key();
		return $this;
	}


	/**
	 * 建立XinXin帳號
	 * @param string $gcg_account 遊戲帳號
	 * @param string $gcg_password 遊戲密碼
	 * @return mixed
	 */
	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();
		$post = [
			'username' => $gcg_account,
			'pwd' => $gcg_password,
			'alias' => $gcg_account,
			'top' => $this->api['_account_id'],
		];

		$result = \App::make('Lib\Curl')->set_header('api_key: ' . $this->_security_code)
			->curl($this->api['_url'] . '/api/createMem.php', $post);

		$result = json_decode($result, true);

		if(($result['code'] ?? null) === '001'){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => $result['msg'] ?? 'fail'];
		}
	}

	/**
	 * 查詢餘額
	 * @param $gcg_account 遊戲帳號
	 */
	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);

		$post = [
			'username' => $gcg_account,
		];
		
		$result = \App::make('Lib\Curl')->set_header('api_key: ' . $this->_security_code)
			->curl($this->api['_url'] . '/api/memGetMoney.php', $post);
		
		$result = json_decode($result, true);

		if(($result['code'] ?? null) === '001'){
			return ['code' => 0, 'data' => $result['money']];
		}else{
			return ['code' => 7, 'text' => $result['msg'] ?? 'fail'];
		}
	}

	/**
	 * 存入(加點)額度
	 * @param $gcg_account 遊戲帳號
	 * @param $credit 額度
	 */
	public function store_credit($gcg_account, $credit)
	{
		return $this->alter_credits($gcg_account, abs($credit));
	}

	/**
	 * 提取(扣點)額度
	 * @param $gcg_account 遊戲帳號
	 * @param $credit 額度
	 */
	public function take_credit($gcg_account, $credit)
	{
		return $this->alter_credits($gcg_account, abs($credit) * -1);
	}

	private function alter_credits($gcg_account, $credit){
		$this->init($gcg_account);
		$post = [
			'username' => $gcg_account,
			'money' => $credit,
		];

		$result = \App::make('Lib\Curl')->set_header('api_key: ' . $this->_security_code)
			->curl($this->api['_url'] . '/api/memTransfer.php', $post);

		$result = json_decode($result, true);

		if(($result['code'] ?? null) === '001'){
			return ['code' => 0, 'data' => $result['money']];
		}else{
			return ['code' => 7, 'text' => $result['msg'] ?? 'fail'];
		}
	}

	/**
	 * 登入遊戲
	 * @param string $gcg_account 遊戲帳號
	 * @param string $gcg_password 遊戲密碼
	 * @return mixed
	 */
	public function login_game($gcg_account, $gcg_password)
	{
		$this->init($gcg_account);
		$post = [
			'username' => $gcg_account,
			'pwd' => $gcg_password,
		];

		$result = \App::make('Lib\Curl')->set_header('api_key: ' . $this->_security_code)
			->curl($this->api['_url'] . '/api/memLogin.php', $post);

		$result = json_decode($result, true);
		$result['url'] = $this->api['_url'] . '/app/m_index.php?lid=' . ($result['lid'] ?? '');
		$result['h5'] = $this->api['_url'] . '/m/spt_index.php?lid=' . ($result['lid'] ?? '');

		if(($result['code'] ?? null) === '001'){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => $result['msg'] ?? 'fail'];
		}
	}

	protected function get_header_key(){
		/*
			api_key= md5(md5(年+月+$api_key)))
			範例: $api_key = test|代理碼
			年月 = 201605
			md5(md5(“201605test|代理碼”)) = “92b534fe1028d6a90c2b88ac326ced55”
		*/
		$date = date('Ym');
		return md5(md5($date . $this->api['_api_key']));
	}
}