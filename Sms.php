<?php

namespace Lib;

class Sms
{
	protected $acc;
	protected $pwd;
	
	public function __construct(){
        
		$branch = \DB::table('t_branch')->where('id', \Config::get('branch.branch_id'))->first();
		$arr = json_decode($branch->config_sms ?? '', true);
		
        if($branch && $arr){
            $this->acc = $arr['Account'];
			$this->pwd = $arr['Password'];
        }else{
			return array('code' => 9, 'text' => '');
		}
	}

    public function send($phone, $text)
    {
		$result = [];
		$data = array(
			'username' => $this->acc,
			'password' => $this->pwd,
			'mobile'   => $phone,
			'message'  => $text,
			'longsms'  => 'Y',
		);
		
		$response = \App::make('Lib\Curl')->curl('http://api.twsms.com/smsSend.php', $data);
		$xml = simplexml_load_string($response);
		
		if($xml->code == '00000'){
			$result = array('code' => 0, 'text' => '發送成功');
		}else{
			$result = array('code' => 9, 'text' => '(' . (string)$xml->code . ')' . (string)$xml->text);
		}
		
		return $result;
    }
	
	//查詢餘額
	public function get_balance()
    {
		$result = [];
		$send_data = array(
			'username'   => $this->acc,
			'password'   => $this->pwd,
			'checkpoint' => 'Y',
		);
		$response = \App::make('Lib\Curl')->curl('http://api.twsms.com/smsQuery.php', $send_data);
		$xml = simplexml_load_string($response);
		
		if($xml->code == '00000'){
			$result = array('code' => 0, 'text' => (int)$xml->point);
		}else{
			$result = array('code' => 9, 'text' => '(' . (string)$xml->code . ')' . (string)$xml->text);
		}
		return $result;
    }
}