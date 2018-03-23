<?php

namespace Game;

class Allbet
{
	public $api = array(
		'_url' => 'API網址',
		'_property_id' => 'propertyId',
		'_agent' => '代理用戶名',
		'_des_key' => 'DES key',
		'_md5_key' => 'MD5 key',
		'_suffix' => '手機版後綴',
	);
	
	function __construct()
	{
		$this->_lib = 'Allbet';
		$this->_random = mt_rand();
		
		//正式
		// $this->api = array(
			// '_url' => 'https://api3.abgapi.net',
			// '_property_id' => '3146829',
			// '_agent' => 'bk888y8',
			// '_des_key' => 'XHlm6R2em3tsm37NHU/ZFc43F8PQs6R2',
			// '_md5_key' => 'qAZnR640o9rZFaeuT8UkK/NmEo+nsH433omvnjvZ5kQ=',
		// );
	}
	
	public function init($gcg_account = '')
	{
		$this->api = \App::make('Lib\Mix')->get_agent_api($this->_lib, $gcg_account);
		return $this;
	}
	
	/**
	 * 驗證或建立會員
	 *
	 * @param $gcg_account 會員帳號（英數底線）
	 * @param $gcg_password 會員密碼（英數底線）
	 * @return  array
	 */
	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();
		$post = [
			'random' => $this->_random,
			'agent' => $this->api['_agent'],
			'client' => $gcg_account, //會員帳號
			'password' => $gcg_password,//會員密碼
			'vipHandicaps' => '12',//VIP盤口編號
			'orHandicaps' => '3',//普通盤口編號
			'orHallRebate' => '0', //普通廳洗碼比
			'laxHallRebate' => '0', //電子遊戲廳洗碼比
		];

		$encode_data = base64_encode($this->_des_encrypt(http_build_query($post), $this->api['_des_key']));
		$sign = base64_encode(md5($encode_data . $this->api['_md5_key'], true));

		$post = [
			'data' => $encode_data,
			'sign' => $sign,
			'propertyId' => $this->api['_property_id'],
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/check_or_create', $post);
		$result = json_decode($result, true);
		
		if(($result['error_code'] ?? null) === 'OK'){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 查詢餘額
	 *
	 * @param $gcg_account 會員帳號
	 * @return  array
	 */
	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);
		$post = [
			'random' => $this->_random,
			'client' => $gcg_account, //會員帳號
			'password' => \App::make('Lib\Mix')->get_password($this->_lib, $gcg_account),//會員密碼
		];

		$encode_data = base64_encode($this->_des_encrypt(http_build_query($post), $this->api['_des_key']));
		$sign = base64_encode(md5($encode_data . $this->api['_md5_key'], true));

		$post = [
			'data' => $encode_data,
			'sign' => $sign,
			'propertyId' => $this->api['_property_id'],
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/get_balance', $post);
		$result = json_decode($result, true);
		
		if(($result['error_code'] ?? null) === 'OK'){
			return ['code' => 0 , 'data' => $result['balance'] ?? -1];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 存入(加點)額度
	 * @param $gcg_account 會員帳號
	 * @param $credit 存入金額(正數)
	 * @return  array
	 */
	public function store_credit($gcg_account, $credit)
	{
		return $this->account_credit_transfer($gcg_account, 1, $credit);
	}

	/**
	 * 提取(扣點)額度
	 * @param $gcg_account 會員帳號
	 * @param $credit 提取金額(正數)
	 * @return  array
	 */
	public function take_credit($gcg_account, $credit)
	{
		return $this->account_credit_transfer($gcg_account, 0, $credit);
	}

	/**
	 * 轉帳
	 * @param $gcg_account 會員帳號
	 * @param $opeFlag 轉帳類型（0.提領-從會員轉至代理商 1.存入-從代理商轉至會員）
	 * @param $credit 轉帳金額
	 * @return  array
	 */
	public function account_credit_transfer($gcg_account, $opeFlag, $credit)
	{
		$this->init($gcg_account);
		$post = [
			'random' => $this->_random,
			'agent' => $this->api['_agent'],//代理商
			'sn' => $this->api['_property_id'] . $this->_get_sn(),//交易流水號(propertyId+13位數字)
			'client' => $gcg_account, //會員帳號
			'operFlag' => $opeFlag,//轉帳類型
			'credit' => $credit,//轉帳金額
		];

		$encode_data = base64_encode($this->_des_encrypt(http_build_query($post), $this->api['_des_key']));
		$sign = base64_encode(md5($encode_data . $this->api['_md5_key'], true));

		$post = [
			'data' => $encode_data,
			'sign' => $sign,
			'propertyId' => $this->api['_property_id'],
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/agent_client_transfer', $post);
		$result = json_decode($result, true);
		
		if(($result['error_code'] ?? null) === 'OK'){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 登入遊戲
	 * @param $gcg_account 會員帳號
	 * @param $gcg_password 會員密碼
	 * @return mixed
	 */
	public function login_game($gcg_account, $gcg_password)
	{
		$this->init($gcg_account);
		$post = [
			'random' => $this->_random,
			'client' => $gcg_account,//會員帳號
			'password' => $gcg_password,//會員密碼
			'language' => 'zh_TW',//語言
		];
		
		$encode_data = base64_encode($this->_des_encrypt(http_build_query($post), $this->api['_des_key']));
		$sign = base64_encode(md5($encode_data . $this->api['_md5_key'], true));

		$post = [
			'data' => $encode_data,
			'sign' => $sign,
			'propertyId' => $this->api['_property_id'],
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/forward_game', $post);
		$result = json_decode($result, true);
		$result['gcg_account'] = $gcg_account . $this->api['_suffix'];
		$result['gcg_passwd'] = $gcg_password;
		
		if(($result['error_code'] ?? null) === 'OK'){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}
	
	// 設置密碼
	public function password($gcg_account, $gcg_password)
	{
		$this->init($gcg_account);
		$post = [
			'random' => $this->_random,
			'client' => $gcg_account,
			'newPassword' => $gcg_password,
		];
		
		$encode_data = base64_encode($this->_des_encrypt(http_build_query($post), $this->api['_des_key']));
		$sign = base64_encode(md5($encode_data . $this->api['_md5_key'], true));
		
		$post = [
			'data' => $encode_data,
			'sign' => $sign,
			'propertyId' => $this->api['_property_id'],
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/setup_client_password', $post);
		$result = json_decode($result, true);
		
		if(($result['error_code'] ?? null) === 'OK'){
			\DB::table('t_game_account')->where('id', $this->api['game_account_id'])->update(['password' => encrypt($gcg_password)]);
			return ['code' => 0, 'data' => ''];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}
	
	/**
	 * 取得13位數的交易流水號(timestamp + xyz)
	 * @return int
	 */
	private function _get_sn()
	{
		$sn_len = 13;//總長度限制
		$base_num = time();//用timestamp做基礎
		$base_len = strlen($base_num);//時間值的長度
		$rand_len = $sn_len - $base_len;//隨機數的最長長度
		$rand_max = (int)str_repeat(9, $rand_len);//隨機數字最大值：$rand_len長度的9
		$rand_num = mt_rand(1, $rand_max);//產生隨機數字
		$rand_str = str_pad($rand_num, $rand_len, '0', STR_PAD_LEFT);//隨機數轉字串，不足位數補0
		$sn = (int)($base_num . $rand_str);
		return $sn;
	}

	//DES加密
	protected function _des_encrypt($str, $key)
	{
		$blocksize = mcrypt_get_block_size(MCRYPT_TRIPLEDES, MCRYPT_MODE_CBC);
		$pad = $blocksize - (strlen($str) % $blocksize);
		$str = $str . str_repeat(chr($pad), $pad);
		return mcrypt_encrypt(MCRYPT_TRIPLEDES, base64_decode($key), $str, MCRYPT_MODE_CBC, base64_decode('AAAAAAAAAAA='));
	}
}