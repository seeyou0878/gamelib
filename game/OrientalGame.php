<?php

namespace Game;

class OrientalGame
{
	public $api = array(
		'_url' => 'API網址',
		'_cagent'  => 'cagent',
		'_des_key' => 'DES key',
		'_md5_key' => 'MD5 key',
	);

	function __construct()
	{
		$this->_lib = 'OrientalGame';
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
			'loginName' => $gcg_account,
			'password' => $gcg_password,
			'currency' => 'NTD',
			'acctType' => 1,  //正式
			'gamePlatform' => 'OG', 
		];
		
		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		$params = urlencode($params);
		
		$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')
			->set_header('KK: WEB_KK_GI_' . $this->api['_cagent'])
			->set_header('METHOD: WEB_KK_MD_GB')
			->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 0){
			return ['code' => 0, 'data' => $result['result']];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);
		$post = [
			'loginName' => $gcg_account,
			'password' => \App::make('Lib\Mix')->get_password($this->_lib, $gcg_account),
			'currency' => 'NTD',
			'acctType' => 1,  //正式
			'gamePlatform' => 'OG', 
		];
		
		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		$params = urlencode($params);
		
		$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')
			->set_header('KK: WEB_KK_GI_' . $this->api['_cagent'])
			->set_header('METHOD: WEB_KK_MD_GB')
			->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 0){
			return ['code' => 0, 'data' => $result['result']];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	public function store_credit($gcg_account, $credit)
	{
		$this->alter_credit($gcg_account, abs($credit));
	}

	public function take_credit($gcg_account, $credit)
	{
		$this->alter_credit($gcg_account, abs($credit) * -1);
	}

	public function alter_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);
		$post = [
			'loginName' => $gcg_account,
			'password' => \App::make('Lib\Mix')->get_password($this->_lib, $gcg_account),
			'currency' => 'NTD',
			'acctType' => 1,  //正式
			'gamePlatform' => 'OG', 
			'type' => ($credit < 0)? 'D' : 'W', 
			'billno' => $this->_get_billno(), 
			'credit' => abs($credit), 
		];
		
		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		$params = urlencode($params);
		
		$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')
			->set_header('KK: WEB_KK_GI_' . $this->api['_cagent'])
			->set_header('METHOD: WEB_KK_MD_TC')
			->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 0){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	public function login_game($gcg_account, $gcg_password)
	{
		$this->init($gcg_account);
		$post = [
			'loginName' => $gcg_account,
			'password' => $gcg_password,
			'currency' => 'NTD',
			'locale' => 'zh-tw',
			'acctType' => 1,  //正式
			'gamePlatform' => 'OG', 
			'returnUrl' => 'javascript:history.go(-1)', 
			'limit' => '12||4', 
			'gameType' => 0, 
		];

		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		$params = urlencode($params);
		
		$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')
			->set_header('KK: WEB_KK_GI_' . $this->api['_cagent'])
			->set_header('METHOD: WEB_KK_MD_FW')
			->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 0){
			$url = $result['result'];
			return ['code' => 0, 'data' => $url];
		}else{
			return ['code' => 7, 'text' => 'fail'];
		}
	}

	//DES加密
	protected function _des_encrypt($str, $key)
	{
		$blocksize = mcrypt_get_block_size(MCRYPT_DES, MCRYPT_MODE_ECB);
		$pad = $blocksize - (strlen($str) % $blocksize);
		$str = $str . str_repeat(chr($pad), $pad);
		$td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_ECB, '');
		$iv = @mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		@mcrypt_generic_init($td, $key, $iv);
		$data = @mcrypt_generic($td, $str);
		@mcrypt_generic_deinit($td);
		@mcrypt_module_close($td);
		$data = base64_encode($data);
		return preg_replace('/\s*/', '',$data);
	}

	protected function _build_str($arr)
	{
		$arr_tmp = array_map(function($k, $v){
			return $k . '=' . $v;
		}, array_keys($arr), $arr);
		
		return implode('&', $arr_tmp);
	}

	//取得不可重複隨機編號
	protected function _get_billno()
	{
		return $this->api['_cagent'] . date('YmdHis') . rand(100, 999);
	}
}