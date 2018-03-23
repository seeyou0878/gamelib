<?php

namespace Game;

class Salon
{
	public $api = array(
		'_url' => 'API網址',
		'_lobby_url' => '客戶端加載器',
		'_lobby_code' => '大廳名稱',
		'_secret_key' => '密鑰',
	);

	function __construct()
	{
		$this->_lib = 'Salon';
		$this->_check_key = 'any'; //check key 任意
		$this->_des_key = 'g9G16nTs';
		$this->_md5_key = 'GgaIMaiNNtg';
		
		//正式
		// $this->api = array(
			// '_url' => 'http://api.sa-gaming.net/api/api.aspx',
			// '_lobby_url' => 'http:/bg.sa-api.com/app.aspx',
			// '_lobby_code' => 'A150',
			// '_secret_key' => '377043B1106A4C18874A028A8C602961',
		// );
		
		//測試
		// $this->api = array(
			// '_url' => 'http://api.sai.slgaming.net/api/api.aspx',
			// '_lobby_url' => 'http://www.sai.slgaming.net/app.aspx',
			// '_lobby_code' => 'A150',
			// '_secret_key' => '0E37C99AEDFF436E8ACA6530D5569877',
		// );
	}

	public function init($gcg_account = '')
	{
		$this->api = \App::make('Lib\Mix')->get_agent_api($this->_lib, $gcg_account);
		return $this;
	}

	/**
	 * 建立帳號
	 * @param string $gcg_account 遊戲帳號
	 * @param string $gcg_password 遊戲密碼
	 * @return mixed
	 * API免密碼
	 */
	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();
		$time = date('YmdHis');
		$key = $this->api['_secret_key'];
		
		$post = [
			'method' => 'RegUserInfo',
			'Key' => $key,
			'Time' => $time,
			'Checkkey' => $this->_check_key,
			'Username' => $gcg_account,
			'CurrencyType' => 'TWD', //台幣
		];
		
		$qs = http_build_query($post);
		$q = urlencode($this->_des_encrypt($qs, $this->_des_key));
		$s = md5($qs . $this->_md5_key . $time . $key);
		
		$url = $this->api['_url'] . '?q=' . $q . '&s=' . $s;
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		
		//xml to json
		$result = json_encode(simplexml_load_string($result));
		$result = json_decode($result, true);
		
		if(($result['ErrorMsgId'] ?? null) === '0'){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 查詢餘額
	 * @param $gcg_account 遊戲帳號
	 * 使用GET
	 */
	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);
		$time = date('YmdHis');
		$key = $this->api['_secret_key'];
		
		$post = [
			'method' => 'GetUserStatusDV',
			'Key' => $key,
			'Time' => $time,
			'Username' => $gcg_account,
		];
		
		$qs = http_build_query($post);
		$q = urlencode($this->_des_encrypt($qs, $this->_des_key));
		$s = md5($qs . $this->_md5_key . $time . $key);
		
		$url = $this->api['_url'] . '?q=' . $q . '&s=' . $s;
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		
		//xml to json
		$result = json_encode(simplexml_load_string($result));
		$result = json_decode($result, true);
		
		if(($result['ErrorMsgId'] ?? null) === '0'){
			return ['code' => 0, 'data' => $result['Balance'] ?? -1];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 存入(加點)額度
	 * @param $gcg_account 遊戲帳號
	 * @param $credit 額度
	 * 每人每秒限制1筆
	 */
	public function store_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);
		$time = date('YmdHis');
		$key = $this->api['_secret_key'];
		
		$post = [
			'method' => 'CreditBalanceDV',
			'Key' => $key,
			'Time' => $time,
			'Checkkey' => $this->_check_key,
			'Username' => $gcg_account,
			'OrderId' => 'IN' . $time . $gcg_account,
			'CreditAmount' => $credit,
		];
		
		$qs = http_build_query($post);
		$q = urlencode($this->_des_encrypt($qs, $this->_des_key));
		$s = md5($qs . $this->_md5_key . $time . $key);
		
		$url = $this->api['_url'] . '?q=' . $q . '&s=' . $s;
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		
		//xml to json
		$result = json_encode(simplexml_load_string($result));
		$result = json_decode($result, true);
		
		if(($result['ErrorMsgId'] ?? null) === '0'){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 提取(扣點)額度
	 * @param $gcg_account 遊戲帳號
	 * @param $credit 額度
	 * 每人每秒限制1筆
	 */
	public function take_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);
		$time = date('YmdHis');
		$key = $this->api['_secret_key'];
		
		$post = [
			'method' => 'DebitBalanceDV',
			'Key' => $key,
			'Time' => $time,
			'Checkkey' => $this->_check_key,
			'Username' => $gcg_account,
			'OrderId' => 'OUT' . $time . $gcg_account,
			'DebitAmount' => $credit,
		];
		
		$qs = http_build_query($post);
		$q = urlencode($this->_des_encrypt($qs, $this->_des_key));
		$s = md5($qs . $this->_md5_key . $time . $key);
		
		$url = $this->api['_url'] . '?q=' . $q . '&s=' . $s;
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		
		//xml to json
		$result = json_encode(simplexml_load_string($result));
		$result = json_decode($result, true);
		
		if(($result['ErrorMsgId'] ?? null) === '0'){
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
	 * API免密碼即可登入
	 */
	public function login_game($gcg_account, $gcg_password)
	{
		$this->init($gcg_account);
		$time = date('YmdHis');
		$key = $this->api['_secret_key'];
		
		$post = [
			'method' => 'LoginRequest',
			'Key' => $key,
			'Time' => $time,
			'Checkkey' => $this->_check_key,
			'Username' => $gcg_account,
			'CurrencyType' => 'TWD',
		];
		
		$qs = http_build_query($post);
		$q = urlencode($this->_des_encrypt($qs, $this->_des_key));
		$s = md5($qs . $this->_md5_key . $time . $key);
		
		$url = $this->api['_url'] . '?q=' . $q . '&s=' . $s;
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		
		//xml to json
		$result = json_encode(simplexml_load_string($result));
		$result = json_decode($result, true);
		
		$post = [
			'username' => $gcg_account,
			'token' => $result['Token'] ?? 0,
			'lobby' => $this->api['_lobby_code'],
			'lang' => 'zh_TW',
			//'returnurl' => '', 返回網址
			'mobile' => 'true',
			'url' => $this->api['_lobby_url'],
		];
		
		if(($result['ErrorMsgId'] ?? null) === '0'){
			return ['code' => 0, 'data' => $post];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	//SA DES加密
	protected function _des_encrypt($str, $key)
	{
		$cipher = MCRYPT_DES;
		$mode = MCRYPT_MODE_CBC;
		$blockSize = mcrypt_get_block_size($cipher, $mode);
		$pad = $blockSize - (strlen($str) % $blockSize);

		return base64_encode(mcrypt_encrypt(
			$cipher,
			$key,
			$str . str_repeat(chr($pad), $pad),
			$mode,
			$key
		));
	}
}