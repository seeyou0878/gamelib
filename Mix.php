<?php

namespace Lib;

class Mix
{
    public function password_hash($account, $password)
    {
		// old method...
        return md5(sha1($account . config('app.key') . $password));
    }
	
	public function insert_duplicate($table, $insert){
		// $insert = [
		// 	[
		// 		'report_uid' => '4129541684299',
		// 		'bet_amount' => '3',
		// 		'result_amount' => '9',
		// 	],
		// 	[
		// 		'report_uid' => '4',
		// 		'bet_amount' => '5',
		// 		'result_amount' => '6',
		// 	],
		// ];
		$row = $insert[0] ?? '';
		if(!is_array($row)){
			return;
		}

		$column_count = count($row);
		$column = '';
		$suffix = '';

		$tmp1 = [];
		$tmp2 = [];
		foreach($row as $k => $v){
			$tmp1[] = $k;
			$tmp2[] = $k . '=VALUES(' . $k . ')';
		}
		$column = implode(',', $tmp1);
		$suffix = implode(',', $tmp2);

		$arr = array_chunk($insert, 1000);
		$str = '(' . trim(str_repeat('?,', $column_count), ',') . '),';
		foreach($arr as $v){ 
			$params = array_flatten($v);

			//(?,?,?)
			$item_count = count($v);
			$placeholder = trim(str_repeat($str, $item_count), ',');

			\DB::select('INSERT INTO ' . $table . ' (' . $column . ') VALUES ' . $placeholder . ' ON DUPLICATE KEY UPDATE ' . $suffix, $params);
		}
	}
	
	public function get_agent_api($game, $account = ''){
		
		$result = [];
		$branch_id = 0;
		$game_account_id = 0;
		
		if(is_string($account) && strlen($account)){
			// init with game account
			$data = \DB::table('t_game_account')
				->select('t_game_account.branch_id', 't_game_account.id')
				->join('t_game', 't_game_account.game_id', '=', 't_game.id')
				->where('t_game_account.account', $account)
				->where('t_game.game', $game)
				->first();
				
			$branch_id = $data->branch_id ?? 0;
			$game_account_id = $data->id ?? 0;
			
		}else if((int)$account){
			// init with branch_id that is passed
			$branch_id = $account;
			
		}else if(\Config::get('branch.branch_id')){
			// init with branch_id in config
			$branch_id = \Config::get('branch.branch_id');
		}
		
		if($branch_id){
			$data = \DB::table('t_branch')->where('id', $branch_id)->first();
			$result = json_decode($data->{'config_' . strtolower($game)}, true);
		}
		
		if($game_account_id){
			$result['game_account_id'] = $game_account_id;
		}
		
		return $result;
	}
	
	public function get_password($game, $account){
		
		$result = '';
		
		$data = \DB::table('t_game_account')
				->select('t_game_account.password')
				->join('t_game', 't_game_account.game_id', '=', 't_game.id')
				->where('t_game_account.account', $account)
				->where('t_game.game', $game)
				->first();
		
		$result = decrypt($data->password ?? '');
		
		return $result;
	}
	
	public function get_credit($id, $format = true)
	{
		$data = [];
		$data['Wallet'] = \DB::table('t_member')->where('id', $id)->first()->wallet ?? 0;
		
		$game = \DB::table('t_game_member_config as t1')
				->select('t_game.game', 't_game_account.account')
				->join('t_game', 't_game.id', '=', 't1.game_id')
				->join('t_game_account', 't_game_account.id', '=', 't1.game_account_id')
				->where('t1.member_id', $id)
				->get()->toArray();
		
		foreach($game ?? [] as $v){
			if($v->game == 'Wallet') continue;
			if($v->account){
				$api = @\App::make('Game\\' . $v->game)->get_balance($v->account);
				$data[$v->game] = $api['data'] ?? '';
			}
		}
		
		foreach($data as $key=>$val){
			if($data[$key] === ''){
				$data[$key] = '維護中';
			}else{
				$data[$key] = ($format)? number_format(floor((int)$data[$key])): floor((int)$data[$key]);
			}
		}
		
		return ['code' => 0, 'data' => $data];
	}

	public function setUnread($msg_id)
	{
		$member_id = \DB::table('t_message')->select('member_id')->where('id', $msg_id)->first()->member_id;
		$unread = \DB::table('t_message')->where(['member_id' => $member_id, 'read' => 2])->count();
		$notice = \DB::table('t_member')->select('notice')->where('id', $member_id)->first()->notice;
		$notice = json_decode($notice, true) ?? [];
		$notice['unread'] = $unread;
		$notice = json_encode($notice);
		\DB::table('t_member')->where('id', $member_id)->update(['notice' => $notice]);
		
		$msg = \DB::table('t_message')->where('id', $msg_id)->first();
		$member_id = $msg->member_id;
		$branch_id = $msg->branch_id;
		$read = $msg->read;
		
		$notify = function($table, $id){
			if ($table == 't_branch') {
				if ($id == 1) { //admin
					$where = ['read' => 1];
				}else{ //branch
					$where = ['branch_id' => $id, 'read' => 1];
				}
			}else{ //t_member
				$where = ['member_id' => $id, 'read' => 2];
			}

			$notice = \DB::table($table)->select('notice')->where('id', $id)->first()->notice;
			$notice = json_decode($notice, true) ?? [];
			$unread = \DB::table('t_message')->where($where)->count();
			$notice['unread'] = $unread;
			if ($table == 't_branch') {
		  		\Redis::publish('client', json_encode(['method' => 'notice', 'branch_id' => $id, 'data' => $notice]));
		  	}else{
		  		\Redis::publish('client', json_encode(['method' => 'unread', 'member_id' => $id, 'data' => $unread]));
		  	}
			$notice = json_encode($notice);
			\DB::table($table)->where('id', $id)->update(['notice' => $notice]);
		};

		// branch notice
		$notify('t_branch', $branch_id);
		$notify('t_branch', 1);

		
		// member notice
		$notify('t_member', $member_id);
		
	}
	
	public function setNotice($order_id, $branch_id = null)
	{
		/*
		case 1: setNotice(order_id, null)
			get b_id from this order,
			notify this branch,
			notify admin(branch 1)
		case 2: setNotice(null, branch_id)
			notify this branch,
			notify admin(branch 1)
		*/
		if (!$branch_id) {
			$order = \DB::table('t_order')->where('id', $order_id)->first();
			$branch_id = $order->branch_id;
			$tmp = $order->type_id;
		}

		//get notice obj: {store: 0, transfer: 0, withdraw: 0, unread: 0}
		$notice = \DB::table('t_branch')->select('notice')->where('id', $branch_id)->first()->notice;
		$notice = json_decode($notice, true) ?? [];

		//count t_order how many needs to notify, group by branch, if is admin count all, else count by own branch
		$get_notice_count = function($where, $branch_id){
			$result = '';
			$count = \DB::table('t_order')->select(\DB::raw('count(id) as c'), 'branch_id')->where($where)->groupBy('branch_id')->get();
			if($count->first()){
				if ($branch_id != 1) {
					$result = $count->where('branch_id', $branch_id)->first()->c ?? 0;
				}else{
					$sum = 0;
					foreach ($count as $v) {
						$sum += $v->c;
					}
					$result = $sum;
				}
			}
			return $result;
		};

		for ($i=1; $i < 4; $i++) { 
			$type_id = $tmp ?? $i;

			switch($type_id){
				case 1:
					$where = [
						'type_id' => $type_id,
						'src_status' => 2,
						'status_id' => 1,
					];
					$notice['store'] = $get_notice_count($where, $branch_id);
					break;
				case 2:
					$where = [
						'type_id' => $type_id,
						'status_id' => 3,
					];
					$notice['transfer'] = $get_notice_count($where, $branch_id);
					break;
				case 3:
					$where = [
						'type_id' => $type_id,
						'status_id' => 1,
					];
					$notice['withdraw'] = $get_notice_count($where, $branch_id);
					break;
			}

			if($tmp ?? 0) break;
		}

		//update new nums through swoole broadcast and in db 
		\Redis::publish('client', json_encode(['method' => 'notice', 'branch_id' => $branch_id, 'data' => $notice]));
		$notice = json_encode($notice);
		\DB::table('t_branch')->where('id', $branch_id)->update(['notice' => $notice]);

		//notify admin when is branch
		if ($order_id && $branch_id != 1) {
			$this->setNotice(null, 1);
		}
	}
	
	public function initNotice(){
		$arr = \DB::table('t_message')
			->select('id')
			->groupBy('member_id')
			->where('read', '!=', 3)
			->get();

		foreach ($arr as $v) {
			$this->setUnread($v->id);
		}

		$branches = \DB::table('t_branch')
			->select('id')
			->get();
		foreach ($branches as $b) {
			$this->setNotice(null, $b->id);
		}
	}

	public function get_order_info($id, $sdate = null, $edate = null, $branch_id = null, $type=null){
		//date
		$date = '';
		if($sdate){
			$date .= 'udate >= ' . $sdate;
			if($edate)
				$date .= ' AND ';
		}
		if($edate){
			$date .= 'udate <= ' . ($edate + 86399);
		}

		$orders = \DB::table('t_order')
			->select(
				\DB::raw('SUM(IF(type_id = 1 AND status_id = 2, total, 0)) as store'),
				\DB::raw('SUM(IF(type_id = 3 AND status_id = 2, total, 0)) as withdraw'),
				\DB::raw('SUM(IF(type_id = 4 AND src_id = 1 AND status_id = 2, total, 0)) as bonus'),
				\DB::raw('SUM(IF(type_id = 1 AND ' . ($date ?: '1=1') . ' AND status_id = 2, total, 0)) as store_tday'),
				\DB::raw('SUM(IF(type_id = 3 AND ' . ($date ?: '1=1') . ' AND status_id = 2, total, 0)) as withdraw_tday'),
				\DB::raw('SUM(IF(type_id = 4 AND ' . ($date ?: '1=1') . ' AND src_id = 1 AND status_id = 2, total, 0)) as bonus_tday')
			);

		//member_id(array, 1 id, or null)
		if(getType($id) == 'array')
			$orders->whereIn('member_id', $id);
		else if($id)
			$orders->where('member_id', $id);

		//type
		if($type)
			$orders->where('type_id', $type);

		$orders = $orders->first();

		return json_decode(json_encode($orders), true);
	}

	public static function to_pairs($obj, $k, $v){
		$arr = [];
		foreach ($obj as $o) {
			$arr[$o->$k] = $o->$v;
		}
		return $arr;
	}

	public static function chunk_insert($table, $data){
		$tmp = [];
		$tmp = array_chunk($data, 1000);
		foreach($tmp as $v){
			\DB::table($table)->insert($v);
		}
	}
}
