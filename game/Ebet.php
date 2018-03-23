<?php

namespace Game;

class Ebet
{
	public $api = [
		'_url' => 'http://bafangyule.ebet.im:8888/',
		'_sub_channel_id' => 1289
	];

	function __construct()
	{
		$this->_lib = 'Ebet';
		$this->_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCG1MDouKdjK30rPfs5TQjVjvnsAnfuR5VnGG8ESO/ORtBDgo5sqebWnwB1UJSgK1GIhd+eogNaO+1GOUWnpyHjiCQf0Cg8GugxM0Gq0AlkOSAWEpLMWac47AYLy6ZcbAa+pSiPNvptk7W+/KIib3ODlPlkAYJHGx6qHIThXTOEIwIDAQAB';
		$this->_private_key = 'MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBAIbUwOi4p2MrfSs9+zlNCNWO+ewCd+5HlWcYbwRI785G0EOCjmyp5tafAHVQlKArUYiF356iA1o77UY5RaenIeOIJB/QKDwa6DEzQarQCWQ5IBYSksxZpzjsBgvLplxsBr6lKI82+m2Ttb78oiJvc4OU+WQBgkcbHqochOFdM4QjAgMBAAECgYAEuT9o5881cjiYYzuB7mj40mF/GzcIagmZ6wk4pTWBjImPU+uZcvpbWoaxlXkfg2T/23DSJeroJmFRrH/8N6bAPNMWYH7XjHp/7nht6YfUQTUeKX+7Zx1uk/tW7mb2lbeFflTwCErq0vpSNICf5lFVqokd2dUOMuyu7gCsZKRKwQJBAM/sogd0S2Ns/7pf1eA1C8cez9Exg4Z9kfzE7JJWEZFXG7581fzYkRMYR2VZ8hE/ehDuNUjLaDBk1z9GFbmdDS8CQQCmAZ1++mb7CzQKPFNhXRD4irXoaoy+jc/O7deruP2fIlg2rbCdtLEHn5CynjiDh3CN/cJ2YiDtFJl6R3K9YQNNAkAXGmH+lgtyZsAbg16OZRaD74aD5g6JORapkW//6pRVI+qvRcu5Jo8oIgB84HunMvhrPSyqg/91sR7BpxXu4+Z9AkBxqf91tuwWDii2rXGF49w/4XIGThZKTv0vuWiHeuWlNTXjUm/wu4zPJHFF69HUNUNa5ZplxnC3A/jGYe9tPeStAkB4Pea+jn2op60sptZlKfJVF579J58cEqAacsODpkk3OcHt5GOKLy9Q5P+XKt4IB0cnE+38qIPaB0McLtIDs+d4';
		$this->_channelId =  309;
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
	 */
	public function add_account($gcg_account, $gcg_password)
	{
		$this->init();
		$post = [
			'username' => $gcg_account,
			'channelId' => $this->_channelId,
			'Lang' => 2, //Traditional Chinese
			'subChannelId' => $this->api['_sub_channel_id'],
			'signature' => $this->get_signature($gcg_account),
		];

		$result = \App::make('Lib\Curl')->set_header('ContentType: application/x-www-formurlencoded')
			->curl($this->api['_url'] . '/api/syncuser', $post);

		$result = json_decode($result, true);
		
		if(($result['status'] ?? null) === 200){
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
		$time = time();
		$post = [
			'username' => $gcg_account,
			'channelId' => $this->_channelId,
			'signature' => $this->get_signature($gcg_account . $time),
			'timestamp' => $time,
		];
		
		$result = \App::make('Lib\Curl')->set_header('ContentType: application/x-www-formurlencoded')
			->curl($this->api['_url'] . '/api/userinfo', $post);
		
		$result = json_decode($result, true);
		
		if(($result['status'] ?? null) === 200){
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
		$rechargeReqId = $gcg_account . str_random(25);
		$time = time();
		$post = [
			'username' => $gcg_account,
			'money' => $credit,
			'channelId' => $this->_channelId,
			'rechargeReqId' => $rechargeReqId,
			'signature' => $this->get_signature($gcg_account . $time),
			'timestamp' => $time,
		];

		$result = \App::make('Lib\Curl')->set_header('ContentType: application/x-www-formurlencoded')
			->curl($this->api['_url'] . '/api/recharge', $post);

		$result = json_decode($result, true);
		
		//second request to check for recharge progress
		if(($result['status'] ?? null) === 200){
			$money = $result['money'];
			$post = [
				'channelId' => $this->_channelId,
				'rechargeReqId' => $rechargeReqId,
				'signature' => $this->get_signature($rechargeReqId),
			];

			//if still charging(0), try again
			$count = 0;
			do{
				$result = \App::make('Lib\Curl')->set_header('ContentType: application/x-www-formurlencoded')
				->curl($this->api['_url'] . '/api/rechargestatus', $post);

				$result = json_decode($result, true);

				if($count++)//not the first try
					sleep(1);
			}while(($result['status'] ?? null) === 0 && $count < 10);
		}

		if(($result['status'] ?? null) === 200){
			return ['code' => 0, 'data' => $money];
		}else{
			return ['code' => 7, 'text' => $result['status'] ?? 'fail'];
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

		//use get_balance to check if server is up
		$con_check = $this->get_balance($gcg_account);
		if($con_check['code'] == 7)
			return ['code' => 7, 'text' => 'fail'];

		//login url
		$ts = time();
		$accessToken = encrypt($gcg_account);
		$url = "http://bafangyule.sdfd.rocks/h5/vdccfv?ts={$ts}&username={$gcg_account}&accessToken={$accessToken}";
		
		return ['code' => 0, 'data' => $url];
	}

	public function get_signature($plaintext){
		//private Key encrypt
		$rsa = new \Crypt_RSA(); 
		$rsa->loadKey($this->_private_key); //xxx as private Key
		$rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
		$rsa->setHash("md5");
		$signature = $rsa->sign($plaintext);
		return base64_encode($signature);
	}

	public function login_check(){
		
		// 接受HTTP请求
		// $post = file_get_contents('php://input');

		// 获取HTTP 请求参数， 并解析JSON数据
		
		// 读取 cmd 值
		$post = \Request::getContent();
		$post = json_decode($post, true);

		$cmd = $post['cmd'] ?? '';

		// 处理登入业务逻辑
		if($cmd == 'RegisterOrLoginReq'){
			// eventType 是登入模式
			$eventType = $post['eventType'] ?? '';

			// 读取渠道ID
			$channelId = $post['channelId'] ?? '';

			// 读取玩家登入名
			$username = $post['username'] ?? '';

			// 获取密码
			$password = $post['password'] ?? '';

			// 读取签名
			$signature = $post['signature'] ?? '';

			// 读取timestamp
			$timestamp =$post['timestamp'] ?? '';

			// 读取accessToken
			$accessToken = $post['accessToken'] ?? '';

			//default response
			$status = 505; //server is busy

			//token for this user
			$token = encrypt($timestamp . $username);

			// 如果是用户密码登入
			if ($eventType == "1") {
				// 从数据库查询用户名密码是否错误
				// 签名部分是 username + timestamp
				
				$sig_chk = $this->get_signature($username . $timestamp) == $signature;
				
				if(!$sig_chk)
					$status = 4026;
				else
					$status = 200;
			}

			// 如果是玩家在渠道手机H5 网站跳转去 eBET APP
			if ($eventType == "3") {
				// AppLinks 连接如 aaa://login?u=username&p=password

				// 从数据库查询用户名密码是否错误,
				// 签名部分是 timestamp + accessToken
				
				$sig_chk = $this->get_signature($timestamp . $token) == $signature;

				if(!$sig_chk)
					$status = 4026;
				else
					$status = 200;
			}

			// 如果是通过令牌登入自动跳转
			if ($eventType == "4") {
				// 从数据库里查询用户名和对应令牌是否匹配
				// 签名部分是 timestamp + accessToken
				
				$token_chk = decrypt($accessToken) == $username;
				
				$time_chk = ((time() - $timestamp) < 120);

				if(!$time_chk)
					$status = 4026;
				else if(!$token_chk)
					$status = 410;
				else
					$status = 200;
			}
			// 验证完成后， 返回请求响应
			
			// status , subChannelId, accessToken, username
			/* 如果是子渠道， 返回子渠道ID ， 不是就返回subChannelId = 0
				accessToken => 每次通过用户名密码登入， 返回新的token,
				username - 返回玩家登入名，
				status - 根据验证结果， 返回对应的结果， 如200 为 验证成功 ， 401 为用户名密码失败， 410 为令牌登入失败
			*/
			//return data
			$this->init($username);
			$data = [
				'status'=> $status,
				'subChannelId' => (int)($this->api['_sub_channel_id'] ?? ''),
				'accessToken' => ($status == 200)? $token : '',
				'username'=> $username,
			];
			
			$result = json_encode($data);

		}else{
			$result = '';
		}
		
		//Ebet log
		// \DB::table('t_log_system')->insert([
			// 'cdate' => time(),
			// 'title' => '[ebet] 登入紀錄',
			// 'content' => 'IN: ' . json_encode($post) . ' OUT: ' . $result,
		// ]);
		
		// 返回数据
		header('Content-type: application/json');
		return $result;
	}
	
}