<?php

namespace Game;

/**
 *  CB電子
 * 重點：參數欄位務必按照手冊順序
 *             密碼長度需 >= 8
 */
class ComeBets
{
	public $api = array(
		'_url' => 'http://login.api.g8game.net/',
		'_account_id' => '78257461655944',
		'_security_code' => 'd81f11911bf8bbc373ad55334095f4ac',
	);
	
	function __construct()
	{
		//正式
		$this->_url = 'http://login.api.g8game.net/';
		$this->_account_id = '78257461655944';
		$this->_security_code = 'd81f11911bf8bbc373ad55334095f4ac';
		
		//測試
		// $this->_url = 'http://api.test.comebets.net/';
		// $this->_account_id = '54421971778391';
		// $this->_security_code = 'd93fb2c1775c062ba782d8584ce5641c';
	}
	
	public function init($gcg_account = '')
	{
		return $this;
	}

	/**
	 * 取得 check_code
	 * @param $data
	 * @return string
	 */
	protected function _get_check_code($data)
	{
		if(isset($data['check_code'])){
			unset($data['check_code']);
		}
		if(isset($data['session_guid'])){
			unset($data['session_guid']);
		}
		$data = array_merge(['security_code' => $this->_security_code], $data);

		$str = urldecode(http_build_query($data));
		
		return md5($str);
	}

	/**
	 * 取得sessionGUID
	 * @return mixed
	 */
	protected function _get_sessionGUID()
	{
		$post['account_id'] = $this->_account_id;
		$post['check_code'] = $this->_get_check_code($post);

		$this->_get_check_code($post);
		$result = \App::make('Lib\Curl')->curl($this->_url . '/v1/api/sessions', $post);
		$result = json_decode($result, true);

		if(($result['status'] ?? null) === true){
			return $result['session'];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 建立CB帳號
	 * @param string $gcg_account 遊戲帳號
	 * @param string $gcg_password 遊戲密碼
	 * @return mixed
	 */
	public function add_account($gcg_account, $gcg_password)
	{
		$post = [
			'account_id' => $this->_account_id,
			'username' => $gcg_account,
			'password' => $gcg_password,
			'session_guid' => $this->_get_sessionGUID(),
		];
		$post['check_code'] = $this->_get_check_code($post);

		$result = \App::make('Lib\Curl')->curl($this->_url . '/v1/api/users', $post);
		$result = json_decode($result, true);

		if(($result['status'] ?? null) === true){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 查詢餘額
	 * @param $gcg_account 遊戲帳號
	 */
	public function get_balance($gcg_account)
	{
		$post = [
			'account_id' => $this->_account_id,
			'username' => $gcg_account,
			'session_guid' => $this->_get_sessionGUID(),
		];
		$post['check_code'] = $this->_get_check_code($post);

		$result = \App::make('Lib\Curl')->curl($this->_url . '/v1/api/balances', $post);
		$result = json_decode($result, true);
		
		if(($result['status'] ?? null) === true){
			return ['code' => 0, 'data' => $result['credit']];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 存入(加點)額度
	 * @param $gcg_account 遊戲帳號
	 * @param $credit 額度
	 */
	public function store_credit($gcg_account, $credit)
	{
		$post = [
			'account_id' => $this->_account_id,
			'username' => $gcg_account,
			'credit' => $credit,
			'session_guid' => $this->_get_sessionGUID(),
		];
		$post['check_code'] = $this->_get_check_code($post);

		$result = \App::make('Lib\Curl')->curl($this->_url . '/v1/api/deposits', $post);
		$result = json_decode($result, true);

		if(($result['status'] ?? null) === true){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 提取(扣點)額度
	 * @param $gcg_account 遊戲帳號
	 * @param $credit 額度
	 */
	public function take_credit($gcg_account, $credit)
	{
		$post = [
			'account_id' => $this->_account_id,
			'username' => $gcg_account,
			'credit' => $credit,
			'session_guid' => $this->_get_sessionGUID(),
		];
		$post['check_code'] = $this->_get_check_code($post);

		$result = \App::make('Lib\Curl')->curl($this->_url . '/v1/api/cashouts', $post);
		$result = json_decode($result, true);

		if(($result['status'] ?? null) === true){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
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
		$post = [
			'account_id' => $this->_account_id,
			'username' => $gcg_account,
			'password' => $gcg_password,
			'locale' => 'zh-TW',
			'session_guid' => $this->_get_sessionGUID(),
		];
		$post['check_code'] = $this->_get_check_code($post);

		$result = \App::make('Lib\Curl')->curl($this->_url . '/v1/api/lobby', $post);
		$result = json_decode($result, true);

		if(($result['status'] ?? null) === true){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}
}