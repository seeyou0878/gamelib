<?php

namespace Game;

class EvoPlay
{
	public $api = array(
		'_url' => 'API網址',
		'_project_id' => 'Project ID',
		'_api_version' => 'API Version',
		'_secrete_key' => 'Secrete Key',
	);

	function __construct()
	{
		$this->_lib = 'EvoPlay';
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
			'project' => $this->api['_project_id'],
			'version' => $this->api['_api_version'],
			'currency' => 'TWD',
		];
		
		$post['signature'] = @$this->getSignature($post);

		$url = $this->api['_url'] . '/User/registration?' . http_build_query($post);
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		$result = json_decode($result, true);

		if($result['user_id'] ?? 0){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function get_balance($gcg_account)
	{
		$this->init($gcg_account);
		
		$post = [
			'project' => $this->api['_project_id'],
			'version' => $this->api['_api_version'],
			'user_id' => $this->get_user_id($gcg_account),
		];

		$post['signature'] = @$this->getSignature($post);

		$url = $this->api['_url'] . '/User/infoById?' . http_build_query($post);
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		$result = json_decode($result, true);
		
		if($result['user_id'] ?? 0){
			return ['code' => 0, 'data' => ($result['balance'] ?? -1)];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function store_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);

		$post = [
			'project' => $this->api['_project_id'],
			'version' => $this->api['_api_version'],
			'wl_transaction_id' => $this->get_txn_id(),
			'user_id' => $this->get_user_id($gcg_account),
			'sum' => $credit,
			'currency' => 'TWD',
		];

		$post['signature'] = @$this->getSignature($post);

		$url = $this->api['_url'] . '/Finance/deposit?' . http_build_query($post);
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		$result = json_decode($result, true);
		
		if($result['user_id'] ?? 0){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function take_credit($gcg_account, $credit)
	{
		$this->init($gcg_account);

		$post = [
			'project' => $this->api['_project_id'],
			'version' => $this->api['_api_version'],
			'wl_transaction_id' => $this->get_txn_id(),
			'user_id' => $this->get_user_id($gcg_account),
			'sum' => $credit,
			'currency' => 'TWD',
		];

		$post['signature'] = @$this->getSignature($post);
		
		$url = $this->api['_url'] . '/Finance/withdrawal?' . http_build_query($post);
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		$result = json_decode($result, true);
		
		if($result['user_id'] ?? 0){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function login_game($gcg_account, $gcg_password)
	{
		$this->init($gcg_account);
		
		$type = \Request::get('type') ?? 0;
		
		$result = ['code' => 0];
		if($type){
			$post = [
				'project' => $this->api['_project_id'],
				'version' => $this->api['_api_version'],
				'user_id' => $this->get_user_id($gcg_account),
				'game_id' => $type,
				'back_url' => '/',
				'return_url_info' => 'true',
				'language' => 'cn',
			];

			$post['signature'] = @$this->getSignature($post);

			$url = $this->api['_url'] . '/Game/getIFrameURL?' . http_build_query($post);
			$result = \App::make('Lib\Curl')->curl($url, '', false);
			$result = json_decode($result, true);
		
			if($result['link'] ?? 0){
				
			}else{ //錯誤回傳
				return ['code' => 7, 'text' => ''];
			}
		}
		
		$result = [
			'url' => $result['link'] ?? '',
			'gameId' => $type,
		];
		
		return ['code' => 0, 'data' => $result];
	}
	
	function getSignature($post)
	{
		$project_id = $this->api['_project_id'];
		$api_version = $this->api['_api_version']; 
		$secrete_key = $this->api['_secrete_key'];
		
		if(!($post['sum'] ?? 0)){
			unset($post['currency']);
		}
		unset($post['return_url_info']);
		unset($post['project']);
		unset($post['version']);
		unset($post['language']);
		
		$required_args = $post;
		
		$md5 = array();
		$md5[] = $project_id;
		$md5[] = $api_version;
		$required_args = array_filter($required_args, function($val){ 
			return	!($val === null || (is_array($val) && !$val));
		});
		
		foreach ($required_args as $required_arg) {
			if(is_array($required_arg)){
				if(count($required_arg)) {
					$recursive_arg = '';
					array_walk_recursive($required_arg, function($item) use (&$recursive_arg) { 
						if(!is_array($item)) { 
							$recursive_arg .= ($item . ':');
						}
					});
					$md5[] = substr($recursive_arg, 0, strlen($recursive_arg)-1); 
				}else{
					$md5[] = '';
				}
			}else{
				$md5[] = $required_arg;
			}
		};
		
		$md5[] = $secrete_key;
		$md5_str = implode('*', $md5);
		return md5($md5_str);
	}
	
	public function get_txn_id()
	{
		return date('YmdHis') . rand(100, 999);
	}
	
	public function get_user_id($gcg_account)
	{
		return (int)substr($gcg_account, -7);
	}
}