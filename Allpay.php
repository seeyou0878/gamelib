<?php

namespace Lib;

class Allpay
{
	public $api = [
		'_url' => 'API 網址',
		'_merchant_id' => '廠商編號',
		'_app_code' => '_app_code',
		'_hash_key' => '_hash_key',
		'_hash_iv' => '_hash_iv',
	];
	
	public function init($arr)
	{
		$this->api = $arr + $this->api;
		$this->api['_url'] = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V4';
		
		return $this;
	}
	
	public function order($order_uid, $total, $ChoosePayment = 'ATM')
	{
		$result = ['code' => 0, 'text' => ''];
		
		switch($ChoosePayment){
			default:
			case 'ATM':
				$ChooseSubPayment = 'CHINATRUST';
				break;
			case 'CVS':
				$ChooseSubPayment = 'CVS';
				break;
			case 'IBON':
				$ChoosePayment = 'CVS';
				$ChooseSubPayment = 'IBON';
				break;
		}
		
		// take first admin alias
		$list = \DB::table('t_branch')->where('id', 1)->first()->admin_alias;
		$host = preg_split('/[\r\n\s]+/', $list)[0] ?? '';
		
		if(!$host){
			$result = ['code' => 16, 'text' => '付款通知設定錯誤'];
		}else if(!$this->api['_merchant_id'] || !$this->api['_hash_key'] || !$this->api['_hash_iv']){
			$result = ['code' => 16, 'text' => '金流參數不完整'];
		}else{
			$post = [
				'MerchantID'        => $this->api['_merchant_id'],
				'MerchantTradeNo'   => $order_uid,
				'MerchantTradeDate' => date('Y/m/d H:i:s'),
				'PaymentType'       => 'aio',
				'TotalAmount'       => $total,
				'TradeDesc'         => 'GTK 點數儲值',
				'ItemName'          => '點數',
				'ReturnURL'         => 'http://' . $host . '/allpay/notify',
				'ChoosePayment'     => $ChoosePayment,
				'ChooseSubPayment'  => $ChooseSubPayment,
				'NeedExtraPaidInfo' => 'Y',
				'PaymentInfoURL'    => 'http://' . $host .'/allpay/check',
				'EncryptType'       => 1,
			];
			
			// expire in 60 min
			if($ChoosePayment == 'CVS'){
				$post['StoreExpireDate'] = 60;
			}
			
			$post['CheckMacValue'] = $this->get_mac($post);
			
			$type = ($post['ChoosePayment'] == 'ATM')? 'ATM': $post['ChooseSubPayment'];
			$data = \App::make('Lib\Curl')->curl($this->api['_url'], $post);
			$data = \DB::table('t_log_allpay')->where('order_uid', $order_uid)->first()->content ?? '[]';
			$data = json_decode($data, true);
			
			if(($data['RtnCode'] ?? 0) == 2){
				// ATM
				$data = [
					'code' => '(' . $data['BankCode'] . ')' . $data['vAccount'],
					'type' => $type,
				];
				$result = ['code' => 0, 'data' => $data];
				
			}else if(($data['RtnCode'] ?? 0) == 10100073){
				// 超商繳款
				$data = array(
					'code' => $data['PaymentNo'],
					'type' => $type,
				);
				$result = ['code' => 0, 'data' => $data];
				
			}else{
				$result = ['code' => 16, 'text' => '付款通知設定錯誤'];
			}
		}
		return $result;
	}
	
	public function get_mac($tmp){
		ksort($tmp);
		
		$arr = [];
		$arr[] = 'HashKey=' . $this->api['_hash_key'];
		foreach($tmp as $k=>$v){
			$arr[] = $k . '=' . $v;
		}
		$arr[] = 'HashIV=' . $this->api['_hash_iv'];
		
		$str = implode('&', $arr);
		$str = urlencode($str);
		$str = strtolower($str);
		$sha256 = hash('sha256', $str);
		$CheckMacValue = strtoupper($sha256);
		
		return $CheckMacValue;
	}
	
	public function check(){
		
		$result = 0;
		$MerchantTradeNo = \Request::get('MerchantTradeNo');
		
		if($MerchantTradeNo){
			$insert = array(
				'cdate' => time(),
				'order_uid' => $MerchantTradeNo,
				'title' => '綠界回傳繳費資訊',
				'content' => json_encode(\Request::all()),
			);
			\DB::table('t_log_allpay')->insert($insert);
		}
		return $result;
	}
	
	public function notify(){
		
		$MerchantTradeNo = \Request::get('MerchantTradeNo');
		$TradeAmt = \Request::get('TradeAmt');
		$RtnCode = \Request::get('RtnCode');
		$PayFrom = \Request::get('PayFrom');
		$ATMAccBank = \Request::get('ATMAccBank');
		$ATMAccNo = \Request::get('ATMAccNo');
		
		$insert = [
			'cdate' => time(),
			'order_uid' => $MerchantTradeNo,
			'title' => '綠界通知已付款',
			'content' => json_encode(\Request::all()),
		];
		\DB::table('t_log_allpay')->insert($insert);
		
		if($MerchantTradeNo){
			$data = \DB::table('t_order')->where('order_uid', $MerchantTradeNo)->first();
		}
		
		if((($data->total ?? 0) == $TradeAmt) && ($RtnCode == 1)){
			$from = '';
			$from .= $PayFrom?: '';
			$from .= $ATMAccBank? "({$ATMAccBank}){$ATMAccNo}": '';

			$update = [
				'src_text' => $data->src_text . $from,
				'src_status' => 2,
			];
			
			\DB::table('t_order')->where(['id' => $data->id])->update($update);
			// notice
			\App::make('Lib\Mix')->setNotice($data->id);
		}
		return '1|OK';
	}
}