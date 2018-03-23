<?php

namespace Game;

/**
 *  GG遊聯天下
 *  鍵値連接符 /\\\\/
 *  必須使用 header GGaming:WEB_GG_GI_ + cagent
 */
class GlobalGaming
{
	public $api = array(
		'_url' => 'API網址',
		'_url_report' => '報表API網址',
		'_cagent'  => 'cagent',
		'_des_key' => 'DES key',
		'_md5_key' => 'MD5 key',
	);

	function __construct()
	{
		$this->_lib = 'GlobalGaming';
		
		//正式
		// $this->api = array(
			// '_url' => 'https://api.gg626.com/api/doLink.do',
			// '_url_report' => 'http://betrec.gg626.com/api/doReport.do',
			// '_cagent' => 'BF001',
			// '_des_key' => '7Iq6H0uH',
			// '_md5_key' => '02Gf888ui73014BqII',
		// );
		
		//測試
		// $this->api = array(
			// '_url' => 'https://testapi.gg626.com/api/doLink.do',
			// '_url_report' => 'http://testapi.gg626.com:5050/api/doReport.do',
			// '_cagent' => 'TE160',
			// '_des_key' => '12345678assa',
			// '_md5_key' => '123456',
		// );
	}

	public function init($gcg_account = '')
	{
		$this->api = \App::make('Lib\Mix')->get_agent_api($this->_lib, $gcg_account);
		return $this;
	}

	/**
	 * 建立GA帳號
	 * @param string $gcg_account 遊戲帳號
	 * @param string $gcg_password 遊戲密碼
	 * @return mixed
	 */
	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();
		$post = [
			'cagent' => $this->api['_cagent'],
			'loginname' => $gcg_account,
			'password' => $gcg_password,
			'method' => 'ca',
			'actype' => 1,  //真錢帳號
			'cur' => 'TWD', //台幣
		];
		
		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		
		$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')->set_header('GGaming:WEB_GG_GI_' . $this->api['_cagent'])->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 0){
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
		$post = [
			'cagent' => $this->api['_cagent'],
			'loginname' => $gcg_account,
			'password' => \App::make('Lib\Mix')->get_password($this->_lib, $gcg_account),
			'method' => 'gb',
			'cur' => 'TWD', //台幣
		];
		
		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		
		$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')->set_header('GGaming:WEB_GG_GI_' . $this->api['_cagent'])->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 0){
			return ['code' => 0, 'data' => $result['dbalance'] ?? -1];
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
		$this->init($gcg_account);
		$post = [
			'cagent' => $this->api['_cagent'],
			'loginname' => $gcg_account,
			'password' => \App::make('Lib\Mix')->get_password($this->_lib, $gcg_account),
			'method' => 'tc',
			'billno' => $this->_get_billno(),
			'type' => 'IN',
			'credit' => $credit,
			'cur' => 'TWD', //台幣
			'ip' => '',
		];
		
		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		
		$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')->set_header('GGaming:WEB_GG_GI_' . $this->api['_cagent'])->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 0){
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
		$this->init($gcg_account);
		$post = [
			'cagent' => $this->api['_cagent'],
			'loginname' => $gcg_account,
			'password' => \App::make('Lib\Mix')->get_password($this->_lib, $gcg_account),
			'method' => 'tc',
			'billno' => $this->_get_billno(),
			'type' => 'OUT',
			'credit' => $credit,
			'cur' => 'TWD', //台幣
			'ip' => '',
		];
		
		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		
		$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')->set_header('GGaming:WEB_GG_GI_' . $this->api['_cagent'])->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 0){
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
		$type = \Request::get('type') ?? 0;
		
		$result = ['code' => 0];
		if($type){
			$post = [
				'cagent' => $this->api['_cagent'],
				'loginname' => $gcg_account,
				'password' => $gcg_password,
				'method' => 'fw',
				'sid' => $this->_get_billno(),
				'lang' => 'zh-CN',
				'gametype' => $type,
				'ip' => '',
			];
			
			$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
			$md5 = md5($params . $this->api['_md5_key']);
			
			$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
			$result = \App::make('Lib\Curl')->set_header('GGaming:WEB_GG_GI_' . $this->api['_cagent'])->curl($url, '', false);
			$result = json_decode($result, true);
		
			if(($result['code'] ?? null) === 0){
				// pass
			}else{
				return ['code' => 7, 'text' => ''];
			}
		}
		
		$url = $result['url'] ?? '';
		$result = [
			'url' => $url,
			'gameId' => $type,
		];
		
		return ['code' => 0, 'data' => $result];
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

	//GG 使用/\\\\/分割符
	protected function _build_str($arr)
	{
		$arr_tmp = array_map(function($k, $v){
			return $k . '=' . $v;
		}, array_keys($arr), $arr);
		
		return implode('/\\\\/', $arr_tmp);
	}

	//取得不可重複隨機編號
	protected function _get_billno()
	{
		return $this->api['_cagent'] . date('YmdHis') . rand(100, 999);
	}
}