<?php

namespace Game;

class Microsova
{
	public $api = array(
		'_url'        => 'API網址',
		'_server'     => 'GameServer',
		'_customerid' => 'CustomerId',
		'_majorid'    => 'MajorId',
		'_password'   => 'Password',
		'_unit_key'   => 'Unit key',
		'_vendor_key' => 'Vendor key',
	);

	function __construct()
	{
		$this->_lib = 'Microsova';
		
		//正式
		// $this->api = array(
			// '_url'        => 'http://ag.r288.net:8080/MicroSovaWS.asmx',
			// '_server'     => 'wmgs.no9dns.com',
			// '_customerid' => '88BK_TW',
			// '_majorid'    => 'MajorAccount',
			// '_password'   => '88bk',
			// '_unit_key'   => '716',
			// '_vendor_key' => 'F2AFB48C07364B8A9DF7CEB5D4EF681A',
		// );
		
		//測試
		// $this->api = array(
			// '_url'        => 'http://59.125.150.194:8088/MicroSovaWS.asmx',
			// '_server'     => '59.125.150.194',
			// '_customerid' => '88bk_tw',
			// '_majorid'    => '88bkmajor',
			// '_password'   => '8888',
			// '_unit_key'   => '590',
			// '_vendor_key' => 'F2AFB48C07364B8A9DF7CEB5D4EF681A',
		// );
	}

	public function init($gcg_account = '')
	{
		$this->api = \App::make('Lib\Mix')->get_agent_api($this->_lib, $gcg_account);
		return $this;
	}

	/**
	 *取得VerdorKey 停用
	 */
	/*public function get_vendor_key()
	{
		$this->init();
		$post = [
			'CustomerId' => $this->api['_customerid'],//客戶別ID
			'AccountId'  => $this->api['_majorid'],   //具major身份的AccountId，用來產生VendorKey
			'Password'   => $this->api['_password'],  //MajorId的密碼
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/ResetVendorKey', $post);
		
		//xml to json
		$result = json_encode(simplexml_load_string($this->_strip_trash_tag($result) ?? ''));
		$result = json_decode($result, true);
		
		if(($result['State'] ?? null) === '1'){
			return ['code' => 0, 'data' => $result['ResultString']];
		}else{ // 回傳錯誤
			return ['code' => 7, 'text' => ''];
		}
	}*/

	/**
	 * 建立微妙帳號
	 *
	 * @param $gcg_account 會員帳號
	 * @param $gcg_password 會員密碼
	 */
	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();
		$post = [
			'VendorKey'   => $this->api['_vendor_key'],//廠商驗證碼
			'UnitKey'     => $this->api['_unit_key'],//單位碼
			'AccountId'   => $gcg_account,//會員帳號
			'AccountName' => $gcg_account,//會員名稱(會員帳號)
			'Password'    => $gcg_password//會員密碼
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/CreateUser', $post);
		
		//xml to json
		$result = json_encode(simplexml_load_string($this->_strip_trash_tag($result) ?? ''));
		$result = json_decode($result, true);
		
		if(($result['State'] ?? null) === '1'){
			return ['code' => 0, 'data' => $result];
		}else{ // 回傳錯誤
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 取得餘額
	 * @param $gcg_account
	 * @return mixed
	 */
	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);
		$post = [
			'VendorKey' => $this->api['_vendor_key'],//廠商驗證碼
			'AccountId' => $gcg_account,//會員帳號
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/GetBalance', $post);
		
		//xml to json
		$result = json_encode(simplexml_load_string($this->_strip_trash_tag($result) ?? ''));
		$result = json_decode($result, true);
		
		if(($result['State'] ?? null) === '1'){
			return ['code' => 0, 'data' => $result['ResultDecimal']];
		}else{ // 回傳錯誤
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 存入(加點)額度
	 * @param $gcg_account 會員帳號
	 * @param $credit 存入金額(正數)
	 * @return
	 */
	public function store_credit($gcg_account, $credit)
	{
		$credit = abs($credit);//確保一定為正數
		return $this->account_credit_transfer($gcg_account, $credit);
	}

	/**
	 * 提取(扣點)額度
	 * @param $gcg_account 會員帳號
	 * @param $credit 提取金額(正數)
	 * @return
	 */
	public function take_credit($gcg_account, $credit)
	{
		$credit = abs($credit) * -1;//確保一定為負數
		return $this->account_credit_transfer($gcg_account, $credit);
	}

	/**
	 * 轉帳
	 * @param $gcg_account 會員帳號
	 * @param $credit 轉帳金額(開洗分數值)
	 */
	public function account_credit_transfer($gcg_account, $credit)
	{
		$this->init($gcg_account);
		$post = [
			'VendorKey'    => $this->api['_vendor_key'],//廠商驗證碼
			'AdminId'      => $this->api['_majorid'],//廠商帳號
			'AccountId'    => $gcg_account,//會員帳號
			'BalanceValue' => $credit,//開洗分數值
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/CreateTransfer', $post);
		
		//xml to json
		$result = json_encode(simplexml_load_string($this->_strip_trash_tag($result) ?? ''));
		$result = json_decode($result, true);
		
		if(($result['State'] ?? null) === '1'){
			return ['code' => 0, 'data' => $result];
		}else{ // 回傳錯誤
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 取得遊戲的驗證碼
	 * @param $gcg_account
	 * @return mixed
	 */
	public function get_login_game_key($gcg_account)
	{
		$this->init($gcg_account);
		$post = [
			'VendorKey' => $this->api['_vendor_key'],//廠商驗證碼
			'AccountId' => $gcg_account,//會員帳號
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/GetLoginGameKey', $post);
		
		//xml to json
		$result = json_encode(simplexml_load_string($this->_strip_trash_tag($result) ?? ''));
		$result = json_decode($result, true);
		
		if(($result['State'] ?? null) === '1'){
			return ['code' => 0, 'data' => $result['ResultString'] ?? ''];
		}else{ // 回傳錯誤
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 登入遊戲
	 * @param $gcg_account 會員帳號
	 * @param $gcg_password 會員密碼
	 * @return mixed
	 */
	public function login_game($gcg_account, $gcg_password, $token=false)
	{
		$this->init($gcg_account);
		$type = \Request::get('type') ?? 0;
		
		$result = [];
		if($type || $token){
			$result = $this->get_login_game_key($gcg_account);
		}
		$key = $result['data'] ?? 0;
		
		$arr_game = \Game\Report\Microsova::get_game_name();
		
		$post = [
			'game_id' => $type,
			'game_server' => $this->api['_server'],
			'game_token' => $key,
			'game_path' => url('/game/' . ($arr_game[$type]['en'] ?? '') ),
			'game_name' => ($arr_game[$type]['en'] ?? ''),
			'customer_id' => $this->api['_customerid'],
			'account' => $gcg_account,
			'password' => $gcg_password,
		];
		
		if(($result['code'] ?? 0) == 0){
			return ['code' => 0, 'data' => $post];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 刪除會造成simplexml_load_string無法正常讀取的標籤
	 * @param $str
	 * @return mixed
	 */
	protected function _strip_trash_tag($str)
	{
		$str = str_replace('&lt;', '<', $str);
		$str = str_replace('&gt;', '>', $str);
		$str = str_replace('xs:', '', $str);
		$str = str_replace('diffgr:', '', $str);
		$str = str_replace('msdata:', '', $str);
		$str = str_replace('<string xmlns="MicroSovaWS"><?xml version="1.0" encoding="utf-8"?>', '', $str);
		$str = str_replace('</string>', '', $str);

		return $str;
	}
}