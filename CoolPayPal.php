<?php

namespace Lib;

class CoolPayPal
{
	public $api = [
		'_url' => 'API 網址',
		'_merchant_id' => '廠商編號',
		'_valid_key' => '_valid_key',
		'_hash_key' => '_hash_key',
		'_hash_iv' => '_hash_iv',
	];
	
	public function init($arr)
	{
		$this->api = $arr + $this->api;
		
		return $this;
	}
	
	public function order($order_uid, $total)
	{
		$result = ['code' => 0, 'text' => ''];
		
		$post = [
			'HashKey' => $this->api['_hash_key'],
			'HashIV' => $this->api['_hash_iv'],
			'MerTradeID' => $order_uid,
			'MerProductID' => 'credit',
			'MerUserID' => 'user',
			'Amount' => $total,
			'TradeDesc' => 'credit',
			'ItemName' => 'credit',
			'UnionPay' => 0,
			'Validate' => '',
		];
		
		$post['Validate'] = md5($this->api['_valid_key'] . $post['HashKey'] . $post['MerTradeID'] . $post['MerProductID'] . $post['MerUserID'] . $post['Amount']);
		
		$data = \App::make('Lib\Curl')->curl($this->api['_url'], $post);
		$html = str_replace(array('+', '/'), array('-', '_'), base64_encode($data));
		
		if($data){
			$data = [
				'code' => '',
				'html' => $html,
			];
			$result = ['code' => 0, 'data' => $data];
			
		}else{
			$result = ['code' => 16, 'text' => '信用卡系統維護中, 請稍後再試'];
		}
		
		return $result;
	}
	
	public function notify(){
		
		$MerTradeID = \Request::get('MerTradeID');
		$RtnCode = \Request::get('RtnCode');
		
		$insert = [
			'cdate' => time(),
			'order_uid' => $MerTradeID,
			'title' => '仟柏通知已付款',
			'content' => json_encode(\Request::all()),
		];
		\DB::table('t_log_allpay')->insert($insert);
		
		if($MerTradeID){
			$data = \DB::table('t_order')->where('order_uid', $MerTradeID)->first();
		}
		
		if($RtnCode == 1){
			
			$update = [
				'src_status' => 2,
			];
			
			\DB::table('t_order')->where(['id' => $data->id])->update($update);
			// notice
			\App::make('Lib\Mix')->setNotice($data->id);
		}
		return 'OK';
	}
}