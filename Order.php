<?php

namespace Lib;

class Order
{
	function __construct(){
		$this->last = $_SESSION['user']['account'] ?? '';
	}

	public function get_order_uid()
	{
		//分站代碼 + 日期yymmddHHii + 隨機3碼
		//88bk 1806221932 564
		
		$branch = \DB::table('t_branch')->where('id', \Config::get('branch.branch_id'))->first();
		$code = $branch->code ?? 'GTK';
		
		$result = '';
		
		for($i = 0; $i < 900; $i++){
			$result = $code . date('ymdHi') . rand(100, 999);
			
			// duplicate check
			$data = \DB::table('t_order')->where('order_uid', $result)->first();
			if(!$data){
				break;
			}
		}
		return $result;
	}

	public function get_game_account($member_id, $game_id){
		
		$result = '';
		$wallet = \DB::table('t_game')->where('id', $game_id)->first();
		if(($wallet->game ?? '') == 'Wallet'){
			// 電子錢包回傳 Wallet
			$result = $wallet->game;
		}else{
			// 其餘回傳遊戲帳號
			$acc = \DB::table('t_game_member_config')
				->select('t_game_account.account')
				->join('t_game', 't_game.id', '=', 't_game_member_config.game_id')
				->join('t_game_account', 't_game_account.id', '=', 't_game_member_config.game_account_id')
				->where('t_game_member_config.member_id', $member_id)
				->where('t_game.id', $game_id)
				->first();
			$result = $acc->account ?? '';
		}
		return $result;
	}

	public function get_bank_account($member_id){
		
		$result = '';
		$bank = \DB::table('t_member_bank')
			->select('t_member_bank.*', 't_bank.code as bank_code', 't_bank.name as bank_name')
			->join('t_bank', 't_bank.id', '=', 't_member_bank.bank_id')
			->where('t_member_bank.member_id', $member_id)
			->where('t_member_bank.status_id', 1)->first();
			
		$result = [
			'bank_id' => $bank->bank_id ?? '',
			'bank_name' => $bank->bank_name ?? '',
			'bank_acc' => '(' . ($bank->bank_code ?? '') . ')' . ($bank->account_no ?? ''),
		];
		
		return $result;
	}

	public function get_bank_receive($receive_id){
		
		$result = '';
		$bank = \DB::table('t_receive')
			->select('t_receive.*', 't_bank.code as bank_code', 't_bank.name as bank_name')
			->join('t_bank', 't_bank.id', '=', 't_receive.bank_id')
			->where('t_receive.id', $receive_id)->first();
			
		$result = [
			'bank_id' => $bank->bank_id ?? '',
			'bank_name' => $bank->bank_name ?? '',
			'bank_acc' => '(' . ($bank->bank_code ?? '') . ')' . ($bank->account_no ?? ''),
		];
		
		return $result;
	}

	public function payment($member_id, $total, $type){
		
		$order_uid = $this->get_order_uid();
		
		$member = \DB::table('t_member')
			->select('t_member.*', 't1.id as mem_receive_id', 't2.id as bch_receive_id')
			->leftjoin('t_branch', 't_branch.id', '=', 't_member.branch_id')
			->leftjoin('t_receive as t1', 't1.id', '=', 't_member.receive_id')
			->leftjoin('t_receive as t2', 't2.id', '=', 't_branch.receive_id')
			->where('t_member.id', $member_id)
			->first();
			
		$type = \DB::table('t_order_store_src')->where('id', $type)->first();
		
		if($type){
			// ATM && 指定帳本
			$mem_receive_id = $member->mem_receive_id ?? 0;
			$bch_receive_id = $member->bch_receive_id ?? 0;
			
			if(($mem_receive_id || $bch_receive_id) && ($type->id == 2)){ //ATM
				// 1.會員設定 2.分站設定 3.金流設定
				$receive_id = $mem_receive_id?: $bch_receive_id;
				$bank = $this->get_bank_receive($receive_id);
				
				$data = [
					'order_uid' => $order_uid,
					'code' => $bank['bank_acc'],
					'type' => 1,
					'total' => $total,
				];
				
			}else if(env('APP_DEBUG')){
				// testing
				$where = [
					'branch_id' => $member->branch_id,
					'status_id' => 1,
				];
				if($type->id == 5){
					$payment = \DB::table('t_payment')->where($where)->whereIn('type', [2])->inRandomOrder()->first();
				}else{
					$payment = \DB::table('t_payment')->where($where)->whereIn('type', [1, 3])->inRandomOrder()->first();
				}
				$config = json_decode($payment->config ?? '', true) ?? [];
				$data = [
					'order_uid' => $order_uid,
					'code' => '(000)0000000' . $payment->type,
					'html' => '',
					'percent' => $config['_percent'] ?? '',
					'type' => $type->id,
					'total' => $total,
				];
				
			}else if($type->id == 5){
				// 仟柏/漢岳 金流
				$where = [
					'branch_id' => $member->branch_id,
					'status_id' => 1,
				];
				$payment = \DB::table('t_payment')->where($where)->whereIn('type', [2])->inRandomOrder()->first();
				$config = json_decode($payment->config ?? '', true) ?? [];
				
				$api = \App::make('Lib\CoolPayPal')->init($config)->order($order_uid, $total, $type->name);
				
				if($api['code'] ?? 1){
					return ['code' => 1, 'text' => $api['text']];
					
				}else{
					$data = [
						'order_uid' => $order_uid,
						'code' => $api['data']['code'],
						'html' => $api['data']['html'],
						'percent' => $config['_percent'] ?? '',
						'type' => $type->id,
						'total' => $total,
					];
				}
				
			}else{
				// 綠界/金恆通 金流
				$where = [
					'branch_id' => $member->branch_id,
					'status_id' => 1,
				];
				$payment = \DB::table('t_payment')->where($where)->whereIn('type', [1, 3])->inRandomOrder()->first();
				$config = json_decode($payment->config ?? '', true) ?? [];
				
				switch($payment->type ?? 0){
					case 1:
						$api = \App::make('Lib\Allpay')->init($config)->order($order_uid, $total, $type->name);
						break;
					case 3:
						$api = \App::make('Lib\AeroPay')->init($config)->order($order_uid, $total, $type->name);
						break;
				}
				
				if($api['code'] ?? 1){
					return ['code' => 1, 'text' => $api['text']];
					
				}else{
					$data = [
						'order_uid' => $order_uid,
						'code' => $api['data']['code'],
						'type' => $type->id,
						'total' => $total,
					];
				}
			}
		}else{
			return ['code' => 1, 'text' => '請選擇繳費方式'];
		}
		
		return ['code' => 0, 'data' => $data];
	}

	public function store($data){
		
		$result = ['code' => 0, 'text' => ''];
		$id = $data['id'] ?? 0;
		
		// 檢查會員與分站
		$member = \DB::table('t_member')->where('id', $data['member_id'] ?? 0)->first();
		if($member){
			$data['branch_id'] = $member->branch_id;
		}else{
			$result = ['code' => 1, 'text' => '該會員不存在'];
		}
		
		if($result['code']){
			// fail
		}elseif($id){
			// 儲存
			\DB::beginTransaction();
			$order = \DB::table('t_order')->where('id', $id)->lockForUpdate()->first();
			
			// 訂單待處理
			if($order && $order->status_id == 1){
				
				// 更新遊戲帳號
				$acc = $this->get_game_account($data['member_id'], $data['tar_id']);
				if(!$acc){
					$result = ['code' => 1, 'text' => '指定遊戲帳號錯誤'];
				}else if($data['status_id'] == 2 && $data['src_status'] != 2){
					$result = ['code' => 1, 'text' => '未付款訂單'];
				}else if($data['total'] != $order->total){
					$result = ['code' => 1, 'text' => '金額禁止調整'];
				}else{
					$data['tar_text'] = ($acc == 'Wallet')? '': $acc;
					$data['udate']    = time();
					$data['last']     = $this->last;
					$data['src_id']   = $order->src_id;  //金流禁止調整
					$data['src_text'] = $order->src_text;//金流禁止調整
					\DB::table('t_order')->where('id', $id)->update($data);
				}
				
				if($result['code']){
					// fail
				}else{
					if($data['status_id'] == 2 && $data['src_status'] == 2){
						// 執行儲值
						$order = \DB::table('t_order')->where('id', $id)->first();
						$api = $this->order_credit($order, false);
						if($api['code'] == 17){
							// API已送出, 若失敗則未完成
							$arr = ['status_id' => 3, 'tar_status' => 3];
							$result = ['code' => 1, 'data' => $id, 'text' => $api['text']];
						}else{
							// 儲值成功
							$arr = ['status_id' => 2, 'tar_status' => 2];
							$result = ['code' => 0, 'data' => $id, 'text' => '儲值成功'];
						}
						$arr = array_replace_recursive($data, $arr, $api['data'] ?? []);
						\DB::table('t_order')->where('id', $id)->update($arr);
						
					}else{
						// 普通更新
						$result = ['code' => 0, 'data' => $id, 'text' => ''];
					}
				}
				
			}else{
				$result = ['code' => 1, 'text' => '訂單已處理禁止變更'];
			}
			\DB::commit();
		}else{
			// 新增
			$branch = \DB::table('t_branch')->where('id', $member->branch_id)->first();
			$e = json_decode($branch->config_extra, true);
			
			// convenience store
			$wky = ($e['store_weekly'] ?? 0);
			$con = ($data['src_id'] == 3 || $data['src_id'] == 4) && $wky;
			if($con){
				$sum = \DB::table('t_order')->where('member_id', $member->id)->whereIn('src_id', [3, 4])->whereIn('status_id', [1, 2, 3])->where('type_id', 1)->where('cdate', '>=', strtotime('monday this week'))->sum('total');
			}
			
			$invalid_range = \App::make('Lib\Invalid')->store_range($data['total'], $data['src_id'], $data['branch_id']);
			if($invalid_range['code']){
				$result = $invalid_range;
			}else if($con && ($wky - $sum < $data['total'])){
				$str = '每周超商儲值限額為:' . $wky . '，可儲值:' . ($wky - $sum) . '，請聯繫客服！';
				$result = ['code' => 1, 'text' => $str];
			}else{
				$pay = \App::make('Lib\Order')->payment($data['member_id'], $data['total'], $data['src_id'] ?? 0);
				$acc = $this->get_game_account($data['member_id'], $data['tar_id']);
				if($pay['code']){
					// fail
					$result = ['code' => 1, 'text' => $pay['text']];
				}else if(!$acc){
					// fail
					$result = ['code' => 1, 'text' => '指定遊戲帳號錯誤'];
				}else{
					$data['order_uid']  = $pay['data']['order_uid'];
					$data['cdate']      = time();
					$data['udate']      = time();
					$data['last']       = $this->last;
					$data['type_id']    = 1;
					$data['src_id']     = $pay['data']['type'];
					$data['src_text']   = $pay['data']['code'];
					$data['src_status'] = 1;
					$data['src_value']  = 0;
					$data['tar_text']   = ($acc == 'Wallet')? '': $acc;
					$data['tar_status'] = 1;
					$data['tar_value']  = 0;
					$data['status_id']  = 1;
					
					// credit card flow
					$data['extra'] = ceil($data['total'] * ($pay['data']['percent'] ?? 0) / 100);
					
					$id = \DB::table('t_order')->insertGetId($data);
					$result = ['code' => 0, 'data' => $id, 'text' => '儲值單成立', 'html' => ($pay['data']['html'] ?? '')];
				}
			}
		}
		
		// notice
		if($result['code']){
			// fail
		}else{
			\App::make('Lib\Mix')->setNotice($id);
		}
		return $result;
	}

	public function transfer($data){
		
		$result = ['code' => 0, 'text' => ''];
		$id = $data['id'] ?? 0;
		
		// 檢查會員與分站
		$member = \DB::table('t_member')->where('id', $data['member_id'] ?? 0)->first();
		if($member){
			$data['branch_id'] = $member->branch_id;
		}else{
			$result = ['code' => 1, 'text' => '該會員不存在'];
		}
		
		if($result['code']){
			// fail
		}elseif($id){
			// 儲存
			\DB::beginTransaction();
			$order = \DB::table('t_order')->where('id', $id)->lockForUpdate()->first();
			
			// 訂單待處理
			if($order && $order->status_id == 1){
				
				if($data['total'] < 10 || $data['total'] > 20000){
					$result = ['code' => 1, 'text' => '金額需大於10, 小於20000'];
				}else if($data['src_id'] == $data['tar_id']){
					$result = ['code' => 1, 'text' => '來源與目標不可相同'];
				}else{
					// 更新遊戲帳號
					$acc1 = $this->get_game_account($data['member_id'], $data['src_id']);
					$acc2 = $this->get_game_account($data['member_id'], $data['tar_id']);
					if(!$acc1){
						$result = ['code' => 1, 'text' => '來源遊戲帳號錯誤'];
					}else if(!$acc2){
						$result = ['code' => 1, 'text' => '目標遊戲帳號錯誤'];
					}else{
						$data['src_text'] = ($acc1 == 'Wallet')? '': $acc1;
						$data['tar_text'] = ($acc2 == 'Wallet')? '': $acc2;
						$data['udate']    = time();
						$data['last']     = $this->last;
						\DB::table('t_order')->where('id', $id)->update($data);
					}
				}
				
				if($result['code']){
					// fail
				}else{
					if($data['status_id'] == 2){
						// 執行轉移
						$order = \DB::table('t_order')->where('id', $id)->first();
						$api1 = $this->order_credit($order, true);
						if($api1['code'] == 17){
							// API已送出, 若失敗則未完成
							$arr = ['status_id' => 3, 'src_status' => 3, 'tar_status' => 1];
							$result = ['code' => 1, 'data' => $id, 'text' => $api1['text']];
						}else if($api1['code'] == 16){
							// API檢查失敗(未扣), 回待處理
							$arr = ['status_id' => 1, 'src_status' => 1, 'tar_status' => 1];
							$result = ['code' => 1, 'data' => $id, 'text' => $api1['text']];
						}else{
							// 成功
							$api2 = $this->order_credit($order, false);
							if($api2['code'] == 17){
								// API已送出, 若失敗則未完成
								$arr = ['status_id' => 3, 'src_status' => 2, 'tar_status' => 3];
								$result = ['code' => 1, 'data' => $id, 'text' => $api2['text']];
							}else{
								// 轉移成功
								$arr = ['status_id' => 2, 'src_status' => 2, 'tar_status' => 2];
								$result = ['code' => 0, 'data' => $id, 'text' => '轉移成功'];
							}
						}
						
						$arr = array_replace_recursive($data, $arr, $api1['data'] ?? [], $api2['data'] ?? []);
						\DB::table('t_order')->where('id', $id)->update($arr);
						
					}else{
						// 普通更新
						$result = ['code' => 0, 'data' => $id, 'text' => ''];
					}
				}
				
			}else{
				$result = ['code' => 1, 'text' => '訂單已處理禁止變更'];
			}
			\DB::commit();
		}else{
			// 新增
			if($data['total'] < 10 || $data['total'] > 20000){
				$result = ['code' => 1, 'text' => '金額需大於10, 小於20000'];
			}else if($data['src_id'] == $data['tar_id']){
				$result = ['code' => 1, 'text' => '來源與目標不可相同'];
			}else{
				$acc1 = $this->get_game_account($data['member_id'], $data['src_id']);
				$acc2 = $this->get_game_account($data['member_id'], $data['tar_id']);
				
				if(!$acc1){
					// fail
					$result = ['code' => 1, 'text' => '來源遊戲帳號錯誤'];
				}else if(!$acc2){
					// fail
					$result = ['code' => 1, 'text' => '目標遊戲帳號錯誤'];
				}else{
					$game1 = \DB::table('t_game')->where('id', $data['src_id'])->first();
					$game2 = \DB::table('t_game')->where('id', $data['tar_id'])->first();
					
					if($acc1 == 'Wallet'){
						$api1 = ['code' => 0, 'data' => $member->wallet];
					}else{
						$api1 = \App::make('Game\\' . $game1->game)->get_balance($acc1);
					}
					$api2 = \App::make('Game\\' . $game2->game)->get_balance($acc2);
					
					if($api1['code']){
						$result = ['code' => 1, 'text' => $game1->name . '維護中, 請稍後再試'];
					}else if($api2['code']){
						$result = ['code' => 1, 'text' => $game2->name . '維護中, 請稍後再試'];
					}else if($api1['data'] < $data['total']){
						$result = ['code' => 1, 'text' => $game1->name . '餘額不足'];
					}else{
						$data['order_uid']  = $this->get_order_uid();
						$data['cdate']      = time();
						$data['udate']      = time();
						$data['last']       = $this->last;
						$data['type_id']    = 2;
						$data['src_text']   = ($acc1 == 'Wallet')? '': $acc1;
						$data['src_status'] = 1;
						$data['src_value']  = 0;
						$data['tar_text']   = ($acc2 == 'Wallet')? '': $acc2;
						$data['tar_status'] = 1;
						$data['tar_value']  = 0;
						$data['status_id']  = 1;
						
						$id = \DB::table('t_order')->insertGetId($data);
						$result = ['code' => 0, 'data' => $id, 'text' => '轉移單成立'];
					}
				}
			}
		}
		
		// notice
		if($result['code']){
			// fail
		}else{
			\App::make('Lib\Mix')->setNotice($id);
		}
		return $result;
	}

	public function withdraw($data){
		
		$result = ['code' => 0, 'text' => ''];
		$id = $data['id'] ?? 0;
		
		// 檢查會員與分站
		$member = \DB::table('t_member')->where('id', $data['member_id'] ?? 0)->first();
		if($member){
			$data['branch_id'] = $member->branch_id;
		}else{
			$result = ['code' => 1, 'text' => '該會員不存在'];
		}
		
		if($result['code']){
			// fail
		}else if($id){
			// 儲存
			\DB::beginTransaction();
			$order = \DB::table('t_order')->where('id', $id)->lockForUpdate()->first();
			
			// 訂單待處理
			if($order && $order->status_id == 1){
				
				// 更新遊戲帳號
				$acc = $this->get_game_account($data['member_id'], $data['src_id']);
				$bank = $this->get_bank_account($data['member_id']);
				if($data['total'] < 1000){
					$result = ['code' => 1, 'text' => '金額需大於1000'];
				}else if(!$acc){
					$result = ['code' => 1, 'text' => '指定遊戲帳號錯誤'];
				}else if(!$bank){
					$result = ['code' => 1, 'text' => '會員銀行帳戶設定異常'];
				}else if($data['status_id'] == 2 && $data['src_status'] != 2){
					$result = ['code' => 1, 'text' => '未扣款訂單'];
				}else if($order->src_status == 2 && $data['src_status'] != 2){
					$result = ['code' => 1, 'text' => '已扣款禁止變更狀態'];
				}else{
					$data['src_text'] = ($acc == 'Wallet')? '': $acc;
					$data['tar_id']   = $bank['bank_id'];
					$data['tar_text'] = $bank['bank_acc'];
					$data['udate']    = time();
					$data['last']     = $this->last;
					\DB::table('t_order')->where('id', $id)->update($data);
				}
				
				if($result['code']){
					// fail
				}else{
					if($order->src_status != 2 && $data['src_status'] == 2){
						// 執行扣款
						$order = \DB::table('t_order')->where('id', $id)->first();
						$api = $this->order_credit($order, true);
						if($api['code'] == 17){
							// API已送出, 若失敗則未完成
							$arr = ['status_id' => 3, 'src_status' => 3];
							$result = ['code' => 1, 'data' => $id, 'text' => $api['text']];
						}else if($api['code'] == 16){
							// API未送出
							$arr = ['status_id' => 1, 'src_status' => 1];
							$result = ['code' => 1, 'data' => $id, 'text' => $api['text']];
						}else{
							// 扣款成功, 訂單狀態不變更
							$arr = ['src_status' => 2];
							$result = ['code' => 0, 'data' => $id, 'text' => '扣款成功'];
						}
						$arr = array_replace_recursive($data, $arr, $api['data'] ?? []);
						\DB::table('t_order')->where('id', $id)->update($arr);
						
					}else{
						// 普通更新
						$result = ['code' => 0, 'data' => $id, 'text' => ''];
					}
				}
				
			}else{
				$result = ['code' => 1, 'text' => '訂單已處理禁止變更'];
			}
			\DB::commit();
		}else{
			// 新增
			if($data['total'] < 1000){
				$result = ['code' => 1, 'text' => '金額需大於1000'];
			}else{
				
				$acc = $this->get_game_account($data['member_id'], $data['src_id']);
				$bank = $this->get_bank_account($data['member_id']);
				if(!$acc){
					// fail
					$result = ['code' => 1, 'text' => '指定遊戲帳號錯誤'];
				}else if(!$bank){
					$result = ['code' => 1, 'text' => '會員銀行帳戶設定異常'];
				}else{
					$data['order_uid']  = $this->get_order_uid();
					$data['cdate']      = time();
					$data['udate']      = time();
					$data['last']       = $this->last;
					$data['type_id']    = 3;
					$data['src_text']   = ($acc == 'Wallet')? '': $acc;
					$data['src_status'] = 1;
					$data['src_value']  = 0;
					$data['tar_id']     = $bank['bank_id'];
					$data['tar_text']   = $bank['bank_acc'];
					$data['tar_status'] = 1;
					$data['tar_value']  = 0;
					$data['status_id']  = 1;
					
					// 預設手續費
					$branch = \DB::table('t_branch')->where('id', $member->branch_id)->first();
					$e = json_decode($branch->config_extra, true);
					$data['extra'] = ceil($data['total'] * ($e['percent'] ?? 0) / 100) + ($e['fixed'] ?? 0);
					
					$id = \DB::table('t_order')->insertGetId($data);
					$result = ['code' => 0, 'data' => $id, 'text' => '提領單成立'];
				}
			}
		}
		
		// notice
		if($result['code']){
			// fail
		}else{
			\App::make('Lib\Mix')->setNotice($id);
		}
		return $result;
	}

	public function bonus($data){
		
		$result = ['code' => 0, 'text' => ''];
		$id = $data['id'] ?? 0;
		
		// 檢查會員與分站
		$member = \DB::table('t_member')->where('id', $data['member_id'] ?? 0)->first();
		if($member){
			$data['branch_id'] = $member->branch_id;
		}else{
			$result = ['code' => 1, 'text' => '該會員不存在'];
		}
		
		if($result['code']){
			// fail
		}else if($id){
			// 儲存
			\DB::beginTransaction();
			$order = \DB::table('t_order')->where('id', $id)->lockForUpdate()->first();
			// 訂單待處理
			if($order && $order->status_id == 1){
				
				// 更新遊戲帳號
				$acc = $this->get_game_account($data['member_id'], $data['tar_id']);
				
				if($data['total'] < 10 && $data['total'] > -10){
					$result = ['code' => 1, 'text' => '金額需大於10 或 小於-10'];
				}else if(!$data['src_id']){
					$result = ['code' => 1, 'text' => '請指定來源'];
				}else if(!$acc){
					$result = ['code' => 1, 'text' => '儲值目標設定錯誤'];
				}else{
					$data['udate']    = time();
					$data['last']     = $this->last;
					$data['tar_text'] = ($acc == 'Wallet')? '': $acc;
					\DB::table('t_order')->where('id', $id)->update($data);
				}

				if($result['code']){
					// fail
				}else{
					if($data['status_id'] == 2){
						// 執行轉移
						$order = \DB::table('t_order')->where('id', $id)->first();
						$api = $this->order_credit($order, false);
						if($api['code'] == 17){
							// API已送出, 若失敗則未完成
							$arr = ['status_id' => 3, 'src_status' => 3];
							$result = ['code' => 1, 'data' => $id, 'text' => $api['text']];
						}else if($api['code'] == 16){
							// API未送出
							$arr = ['status_id' => 1, 'src_status' => 1];
							$result = ['code' => 1, 'data' => $id, 'text' => $api['text']];
						}else{
							$arr = ['status_id' => 2, 'tar_status' => 2];
							$result = ['code' => 0, 'data' => $id, 'text' => '紅利單已處理'];
						}
						
						$arr = array_replace_recursive($data, $arr, $api['data'] ?? []);
						\DB::table('t_order')->where('id', $id)->update($arr);
						
					}else{
						// 普通更新
						$result = ['code' => 0, 'data' => $id, 'text' => ''];
					}
				}
			}else{
				$result = ['code' => 1, 'text' => '訂單已處理禁止變更'];
			}
			\DB::commit();

		}else{
			// 新增
			$acc = $this->get_game_account($data['member_id'], $data['tar_id']);
			$game = \DB::table('t_game')->where('id', $data['tar_id'])->first();
			
			if($data['total'] < 10 && $data['total'] > -10){
				$result = ['code' => 1, 'text' => '金額需大於10 或 小於-10'];
			}else if(!$data['src_id']){
				$result = ['code' => 1, 'text' => '請指定來源'];
			}else if(!$acc){
				$result = ['code' => 1, 'text' => '儲值目標設定錯誤'];
			}else{
				
				if($acc == 'Wallet'){
					$api = ['code' => 0, 'data' => $member->wallet];
				}else{
					$api = \App::make('Game\\' . $game->game)->get_balance($acc);
				}
				
				if($api['code']){
					$result = ['code' => 1, 'text' => $game->name . '維護中, 請稍後再試'];
				}else if($data['total'] < 0 && (($data['total'] + $api['data']) < 0)){
					$result = ['code' => 1, 'data' => ['src_value' => $api['data']], 'text' => '目標餘額不足'];
				}else{
					
					$data['order_uid']  = $this->get_order_uid();
					$data['cdate']      = time();
					$data['udate']      = time();
					$data['last']       = $this->last;
					$data['type_id']    = 4;
					$data['src_status'] = 2;
					$data['tar_text']   = ($acc == 'Wallet')? '': $acc;
					$data['tar_status'] = 1;
					$data['status_id']  = 1;
					
					$id = \DB::table('t_order')->insertGetId($data);
					$result = ['code' => 0, 'data' => $id, 'text' => '紅利單成立'];
				}
			}
		}

		return $result;
	}

	//訂單處理加扣點行為
	public function order_credit($order, $first)
	{
		$result = ['code' => 0, 'text' => ''];
		
		$game_id      = $first? $order->src_id  : $order->tar_id;
		$game_account = $first? $order->src_text: $order->tar_text;
		
		$game = \DB::table('t_game')->where('id', $game_id)->first();
		$member = \DB::table('t_member')->where('id', $order->member_id)->lockForUpdate()->first();
		
		$data = [];
		
		$type = $game->game;
		
		//餘額查詢 API檢測
		switch($type){
			case 'Wallet':
				$credit = $member->wallet;
				break;
				
			default:
				$api = \App::make('Game\\' . $type)->get_balance($game_account);
				if($api['code']){
					if($first){
						$result = array('code' => 16, 'text' => '遊戲維護中, 請稍後再試');
					}else{
						//儲值時檢查若失敗則直接改電子錢包
						$result = array('code' => 0, 'text' => '');
						$type = 'Wallet';
						$credit = $member->wallet;
						$data['tar_id'] = 0;
						$data['tar_text'] = $game->name . '查詢失敗, 轉進電子錢包';
					}
				}else{
					$credit = $api['data'];
				}
				break;
		}
		
		if($result['code']){
			// fail
		}else{
			//扣款時檢查餘額
			if($first && ($credit < $order->total)){
				$result = ['code' => 16, 'data' => ['src_value' => $credit], 'text' => '遊戲餘額不足'];
			}
			
			//紅利單扣點
			if($order->type_id == 4 && $order->total < 0 && (($order->total + $credit) < 0)){
				$result = ['code' => 16, 'data' => ['src_value' => $credit], 'text' => '目標餘額不足'];
			}
		}
		
		if($result['code']){
			// fail
		}else{
			// credit card flow
			if(!$first && $order->extra!=0){
				$order->total -= $order->extra;
			}
			
			//加扣點
			switch($type){
				case 'Wallet':
					if($first){
						\DB::table('t_member')->where('id', $member->id)->decrement('wallet', $order->total);
						$data['src_value'] = $credit - $order->total;
					}else{
						\DB::table('t_member')->where('id', $member->id)->increment('wallet', $order->total);
						$data['tar_value'] = $credit + $order->total;
					}
					break;
					
				default:
					if($first){
						$api = \App::make('Game\\' . $type)->take_credit($game_account, $order->total);
					}else{
						if($order->type_id == 4 && $order->total < 0){
							//紅利單扣點
							$api = \App::make('Game\\' . $type)->take_credit($game_account, abs($order->total));
						}else{
							$api = \App::make('Game\\' . $type)->store_credit($game_account, $order->total);
						}
					}
					
					if($api['code']){
						$result = array('code' => 17, 'text' => '遊戲加扣點錯誤, 請手動處置');
						// 紀錄加扣點前的值
						if($first){
							$data['src_value'] = $credit;
						}else{
							$data['tar_value'] = $credit;
						}
					}else{
						// 紀錄加扣點後的值
						if($first){
							$data['src_value'] = $credit - $order->total;
						}else{
							$data['tar_value'] = $credit + $order->total;
						}
					}
					break;
			}
			
			$result['data'] = $data;
		}
		
		return $result;
	}
}
