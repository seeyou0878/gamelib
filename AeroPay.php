<?php

namespace Lib;

class AeroPay
{
	public $api = [
		'_merchant_id' => '廠商編號',
	];
	
	public function init($arr)
	{
		$this->api = $arr + $this->api;
		$this->api['_url_atm'] = 'http://store.ufo.com.tw/VRACT/Gateway.asp';
		$this->api['_url_cvs'] = 'http://store.ufo.com.tw/Paymain/Gateway.asp';
		
		return $this;
	}
	
	public function order($order_uid, $total, $type = 'ATM')
	{
		$result = ['code' => 0, 'text' => ''];
		
		// take first admin alias
		$list = \DB::table('t_branch')->where('id', 1)->first()->admin_alias;
		$host = preg_split('/[\r\n\s]+/', $list)[0] ?? '';
		
		if(!$host){
			$result = ['code' => 16, 'text' => '付款通知設定錯誤'];
		}else if(!$this->api['_merchant_id']){
			$result = ['code' => 16, 'text' => '金流參數不完整'];
		}else{
			
			$post = [
				'Merchent' => $this->api['_merchant_id'],
				'OrderID' => $order_uid,
				'Product' => 'GAME',
				'Name' => 'xxxx',
				'Total' => $total,
				'ReAUrl' => 'http://' . $host . '/aeropay/check',
				'ReBUrl' => 'http://' . $host . '/aeropay/notify',
			];
			
			if($type == 'ATM'){
				$data = \App::make('Lib\Curl')->curl($this->api['_url_atm'], $post);
			}else{
				// expire in 60 min
				$post['Hour'] = 1;
				$data = \App::make('Lib\Curl')->curl($this->api['_url_cvs'], $post);
			}
			
			$data = \DB::table('t_log_allpay')->where('order_uid', $order_uid)->first()->content ?? '[]';
			$data = json_decode($data, true);
			
			if($data['ACID'] ?? 0){
				// ATM
				$data = [
					'code' => '(' . substr($data['ACID'], 0, 3) . ')' . substr($data['ACID'], 3),
					'type' => $type,
				];
				$result = ['code' => 0, 'data' => $data];
			}else if($data['StoreCode'] ?? 0){
				// 超商繳款
				$data = array(
					'code' => $data['StoreCode'],
					'type' => $type,
				);
				$result = ['code' => 0, 'data' => $data];
				
			}else{
				$result = ['code' => 16, 'text' => '付款通知設定錯誤'];
			}
		}
		
		return $result;
	}
	
	public function check(){
		
		$result = 0;
		$order_uid = \Request::get('Ordernum');
		
		if($order_uid){
			$insert = array(
				'cdate' => time(),
				'order_uid' => $order_uid,
				'title' => '金恆通回傳繳費資訊',
				'content' => json_encode(\Request::all()),
			);
			\DB::table('t_log_allpay')->insert($insert);
		}
		
		return $result;
	}
	
	public function notify(){
		
		$order_uid = \Request::get('Ordernum');
		$Total = \Request::get('Total');
		$Status = \Request::get('Status');
		
		$insert = [
			'cdate' => time(),
			'order_uid' => $order_uid,
			'title' => '金恆通通知已付款',
			'content' => json_encode(\Request::all()),
		];
		\DB::table('t_log_allpay')->insert($insert);
		
		if($order_uid){
			$data = \DB::table('t_order')->where('order_uid', $order_uid)->first();
		}
		
		if((($data->total ?? 0) == $Total) && ($Status == '0000')){
			
			$update = [
				'src_status' => 2,
			];
			
			\DB::table('t_order')->where(['id' => $data->id])->update($update);
			// notice
			\App::make('Lib\Mix')->setNotice($data->id);
		}
		
		return '1|OK';
	}
}