<?php

namespace Game;

class SuperSport
{
	public $api = array(
		'_url' => 'API網址',
		'_agent' => '代理用戶名',
		'_aes_key' => 'AES key',
		'_aes_iv' => 'AES iv',
	);

	function __construct()
	{
		$this->_lib = 'SuperSport';
		
		//正式站
		// $this->api = array(
			// '_url' => 'http://apiball.king588.net/';
			// '_agent' => 'GTKAgency51';
		// );
	}

	public function init($gcg_account = '')
	{
		$this->api = \App::make('Lib\Mix')->get_agent_api($this->_lib, $gcg_account);
		return $this;
	}

	/**
	 * 新增帳號, 區分代理與會員
	 * @param string $gcg_account 遊戲帳號
	 * @param string $gcg_password 遊戲密碼
	 * @return mixed
	 */
	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();
		$post = [
			'act' => 'add',
			'account' => $this->_aes_encrypt($gcg_account, $this->api['_aes_key'], $this->api['_aes_iv']),
			'passwd' => $this->_aes_encrypt($gcg_password, $this->api['_aes_key'], $this->api['_aes_iv']),
			'nickname' => $gcg_account,
			'level' => 1,
			'up_account' => $this->api['_agent'],
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/api/account', $post);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 999){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 取得帳號餘額
	 * @param string $gcg_account 遊戲帳號
	 * @return mixed
	 */
	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);
		$post = [
			'act' => 'search',
			'account' => $this->_aes_encrypt($gcg_account, $this->api['_aes_key'], $this->api['_aes_iv']),
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/api/points', $post);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 999){
			return ['code' => 0, 'data' => $result['data']['point'] ?? -1];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 儲值
	 * @param string $gcg_account 遊戲帳號
	 * @param int $credit 額度
	 */
	public function store_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);
		$post = [
			'act' => 'add',
			'account' => $this->_aes_encrypt($gcg_account, $this->api['_aes_key'], $this->api['_aes_iv']),
			'point' => $credit,
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/api/points', $post);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 999){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 提款
	 * @param string $gcg_account 遊戲帳號
	 * @param int $credit 額度
	 */
	public function take_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);
		$post = [
			'act' => 'sub',
			'account' => $this->_aes_encrypt($gcg_account, $this->api['_aes_key'], $this->api['_aes_iv']),
			'point' => $credit,
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/api/points', $post);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 999){
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
		$this->init($gcg_account);
		
		$post = [
			'account' => $this->_aes_encrypt($gcg_account, $this->api['_aes_key'], $this->api['_aes_iv']),
			'passwd' => $this->_aes_encrypt($gcg_password, $this->api['_aes_key'], $this->api['_aes_iv']),
			'responseFormat' => 'json',
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/api/login', $post);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 999){
			$result = $post;
			$result['url'] = $this->api['_url'] . '/api/login';
			
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	//AES加密
	protected function _aes_encrypt($str, $key, $iv)
	{
		$cipher = MCRYPT_RIJNDAEL_128;
		$mode = MCRYPT_MODE_CBC;
		$blockSize = mcrypt_get_block_size($cipher, $mode);
		$pad = $blockSize - (strlen($str) % $blockSize);

		return base64_encode(mcrypt_encrypt(
				$cipher,
				$key,
				$str . str_repeat(chr($pad), $pad),
				$mode,
				$iv
		));
	}
}
