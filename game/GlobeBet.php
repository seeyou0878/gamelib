<?php

namespace Game;

class GlobeBet
{
	public $api = array(
		'_url' => 'API網址',
		'_generalkey' => 'GeneralKey',
		'_tpcode' => 'TPCode',
		'_secretkey' => 'SecretKey',
	);

	function __construct()
	{
		$this->_lib = 'GlobeBet';
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
			'GB' => [
				'Method' => 'CreateMember',
				'TPCode' => $this->api['_tpcode'],
				'AuthKey' => $this->api['_generalkey'],
				'Params' => [
					'MemberID' => $gcg_account,
					'FirstName' => $gcg_account,
					'LastName' => $gcg_account,
					'Nickname' => $gcg_account,
					'Gender' => '0',
					'Birthdate' => '1989-01-01',
					'CyCode' => 'CN',
					'CurCode' => 'GBT',
					'LangCode' => 'zh-cn',
					'TPUniqueID' => 'new',
				],
			],
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'], json_encode($post));
		$result = json_decode($result, true);

		if (($result['GB']['Result']['Success'] ?? null) === 1){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
		
	}

	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);
		$post = [
			'GB' => array(
				'Method' => 'GetBalance',
				'TPCode' => $this->api['_tpcode'],
				'AuthKey' => $this->api['_generalkey'],
				'Params' => array(
					'MemberID' => $gcg_account,
					'CurCode' => 'GBT',
				),
			),
		];
		$result = \App::make('Lib\Curl')->curl($this->api['_url'], json_encode($post));
		$result = json_decode($result, true);

		if(($result['GB']['Result']['Success'] ?? null) === 1){
			return ['code' => 0, 'data' => ($result['GB']['Result']['ReturnSet']['Balance'] ?? -1)/100];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function store_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);
		$this->update_tpuniqueid($gcg_account);
		$post = [
			'GB' => [
				'Method' => 'Deposit',
				'TPCode' => $this->api['_tpcode'],
				'AuthKey' => $this->api['_secretkey'],
				'Params' => [
					'MemberID' => $gcg_account,
					'CurCode' => 'GBT',
					'Amount' => $credit*100,
					'ExTransID' => $this->_get_serial(),
					'TPUniqueID' => $this->_lib,
				],
			],
		];
		$result = \App::make('Lib\Curl')->curl($this->api['_url'], json_encode($post));
		$result = json_decode($result, true);
		
		if(($result['GB']['Result']['Success'] ?? null) === 1){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function take_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);
		$this->update_tpuniqueid($gcg_account);
		$post = [
			'GB' => [
				'Method' => 'Withdrawal',
				'TPCode' => $this->api['_tpcode'],
				'AuthKey' => $this->api['_secretkey'],
				'Params' => [
					'MemberID' => $gcg_account,
					'CurCode' => 'GBT',
					'Amount' => $credit*100,
					'ExTransID' => $this->_get_serial(),
					'TPUniqueID' => $this->_lib,
				],
			],
		];
		$result = \App::make('Lib\Curl')->curl($this->api['_url'], json_encode($post));
		$result = json_decode($result, true);

		if(($result['GB']['Result']['Success'] ?? null) === 1){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	// TPUniqueID
	public function update_tpuniqueid($gcg_account)
	{
		$this->init($gcg_account);
		$post = [
			'GB' => [
				'Method' => 'UpdateTPUniqueID',
				'TPCode' => $this->api['_tpcode'],
				'AuthKey' => $this->api['_generalkey'],
				'Params' => [
					'MemberID' => $gcg_account,
					'TPUniqueID' => $this->_lib,
				],
			],
		];
		$result = \App::make('Lib\Curl')->curl($this->api['_url'], json_encode($post));
		$result = json_decode($result, true);

		if(($result['GB']['Result']['Success'] ?? null) === 1){
			return ['code' => 0, 'data' => $result['GB']['Result']['ReturnSet']['GBSN']];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	// 登入遊戲
	public function login_game($gcg_account, $gcg_password)
	{
		$result = $this->update_tpuniqueid($gcg_account);
		
		if($result['code']){
			// fail
			return ['code' => 7, 'text' => ''];
		}else{
			$url = 'http://gb.88bx.net/';
			$type = \Request::get('type');
			$time = time();
			
			$game = [
				1 => 'pk10',
				2 => 'lotto',
				3 => 'keno',
				4 => 'ssc',
			];
			
			$cks = [
				'uid=' . $result['data'],
				'sid=' . strtolower($this->_lib),
				'tryit=n',
				'ts=' . $time,
			];

			$token = [
				'uid=' . $result['data'],
				'sid=' . $this->_lib,
				'tryit=n',
				'ts=' . $time,
				'ck=' . md5(implode('&', $cks)),
			];
			
			$token = $this->_aes_encrypt(implode('&', $token));
			
			$result = [
				'url' => $url . ($game[$type?: 1]) . '/default.aspx?tpid=' . $this->api['_tpcode'] . '&token=' . $token . '&languagecode=zh-tw',
				'h5'  => $url . 'mobile/' . ($game[$type?: 1]) . '/index.aspx?tpid=' . $this->api['_tpcode'] . '&token=' . $token . '&languagecode=zh-tw',
				'gameId' => $type,
			];
			
			return ['code' => 0, 'data' => $result];
		}
	}
	
	//取得不可重複隨機編號
	protected function _get_serial()
	{
		return date('YmdHis') . rand(100, 999);
	}
	
	protected function _aes_encrypt($str)
	{
		// encrypt
		$sSalt = base64_decode(sprintf('%s%s', str_repeat('A',171), '='));
		$hash = hash_pbkdf2('sha1', $this->api['_generalkey'], $sSalt, 1000, 256/8, true);
		$key = substr($hash, 0, 16);
		$iv = substr($hash, 16, 16);
		$str = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $str, MCRYPT_MODE_CBC, $iv);
		
		// getHex
		$hex = '';
		for($i = 0; $i < strlen($str); $i++){
			$hex .= sprintf('%02s', dechex(ord($str[$i])));
		}
		
		return $hex;
	}
}