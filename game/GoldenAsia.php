<?php

namespace Game;

/**
 *  GA黃金亞洲
 *  必須使用 Content-Type:application/json + json_encode格式
 *  代理帳號使用大寫, 會員帳號不限
 *
 */
class GoldenAsia
{
	public $api = array(
		'_url' => 'API網址',
		'_agent' => '代理用戶名',
		'_api_key' => 'APIKey',
		'_suffix' => '手機版後綴',
	);
	
    function __construct()
    {
		$this->_lib = 'GoldenAsia';
		
		//正式
		// $this->api = array(
			// '_url' => 'http://api.ga666.net/gaapi/api/rest/',
			// '_agent' => 'GATEST3',
			// '_api_key' => 'nTnjQ9hKn5XI',
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
     * @param string $gcg_password 遊戲密碼 md5編碼
     * @return mixed
     */
    public function add_account($gcg_account, $gcg_password)
    {
		$this->init();
        $acc = $this->api['_agent'];
		$key = $this->api['_api_key'];
		
		$send_data = array(
			'userName' => $gcg_account,
			'passWord' => md5($gcg_password),
			'currencyName' => 'TWD', //台幣
			//'limitId' => '', //若無則不傳送, 否則會錯
		);
		
		$url = $this->api['_url'] . $acc . '/' . md5($key . $acc) . '/register';
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, json_encode($send_data));
		
		if((json_decode($result)->code ?? false) === 0){
            return array('code' => 0, 'data' => json_decode($result, true));
        }else{
            return array('code' => 7, 'data' => '');
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
        $acc = $this->api['_agent'];
		$key = $this->api['_api_key'];
		
		$url = $this->api['_url'] . $acc . '/' . md5($key . $acc) . '/getBalance/' . $gcg_account;
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, '', false);
		
		if((json_decode($result)->code ?? false) === 0){
            return array('code' => 0, 'data' => (json_decode($result)->obj ?? 0));
        }else{
            return array('code' => 7, 'data' => '');
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
		$acc = $this->api['_agent'];
		$key = $this->api['_api_key'];
		$serial = $this->_get_serial();
		
        $send_data = array(
			'userName' => $gcg_account,
			'amount' => $credit,
			'serial' => $serial,
			'type' => 1,
			'token' => md5($key . $gcg_account . $serial),
		);
		
		$url = $this->api['_url'] . $acc . '/' . md5($key . $acc) . '/transfer';
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, json_encode($send_data));
		
		if((json_decode($result)->code ?? false) === 0){
            return array('code' => 0, 'data' => json_decode($result, true));
        }else{
            return array('code' => 7, 'data' => '');
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
		$acc = $this->api['_agent'];
		$key = $this->api['_api_key'];
        $serial = $this->_get_serial();
		
        $send_data = array(
			'userName' => $gcg_account,
			'amount' => $credit,
			'serial' => $serial,
			'type' => 2,
			'token' => md5($key . $gcg_account . $serial),
		);
		
		$url = $this->api['_url'] . $acc . '/' . md5($key . $acc) . '/transfer';
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, json_encode($send_data));
		
		if((json_decode($result)->code ?? false) === 0){
            return array('code' => 0, 'data' => json_decode($result, true));
        }else{
            return array('code' => 7, 'data' => '');
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
        $acc = $this->api['_agent'];
		$key = $this->api['_api_key'];
		
		$url = $this->api['_url'] . $acc . '/' . md5($key . $acc) . '/login/' . $gcg_account . '/' . 'tw';
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, '', false);
		
		$result_arr = json_decode($result, true);
		$result_arr['gcg_account'] = $gcg_account . '@' . $this->api['_suffix'];
		$result_arr['gcg_passwd'] = $gcg_password;

		if((json_decode($result)->code ?? false) === 0){
            return array('code' => 0, 'data' => $result_arr);
        }else{
            return array('code' => 7, 'data' => '');
        }
    }
	
	//取得不可重複隨機編號
	protected function _get_serial()
	{
		return '88bk' . date('YmdHis') . rand(100, 999);
	}
}