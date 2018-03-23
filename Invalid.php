<?php

namespace Lib;

/*
	return value: true = invalid, false = valid;
*/
class Invalid
{
	public function ip($list)
	{
		//\App::make('Lib\Invalid')->ip('127.0.0.1,192.168.1.1')
		$arr = explode(',', $list);
		
		foreach($arr as $ip){
			if($ip && $this->ban_check(2, $ip)){
				return true;
			}
		}
		return false;
	}
	
	public function phone($str)
	{
		return $this->ban_check(1, $str);
	}
	
	//Bank account
	public function account($str)
	{
		return $this->ban_check(3, $str);
	}
	
	public function bank_acc_format($str)
	{
		return !preg_match('/^\d{3}-\d+$/', $str);
	}
	
	public function limit($str)
	{
		$branch_id = \Config::get('branch.branch_id');
		$limit = \DB::table('t_branch')->select('limit')->where('id', $branch_id)->first()->limit ?? 0;
		
		// t_branch.limit = 0 不限制
		if($limit){
			$phone_count = \DB::table('t_member')->where([
				['branch_id', $branch_id], 
				['phone', $str]
			])->count();
			return $phone_count >= $limit;
		}else{
			return false;
		}
	}
	
	public function is_acc_dup($acc, $id = 0, $branch_id = 0)
	{
		$branch_id = $branch_id?: \Config::get('branch.branch_id');
		$a_count = \DB::table('t_account')
					->where('account', $acc)
					->where('branch_id', $branch_id)
					->where('id', '!=', $id)
					->count();
		$c_count = \DB::table('t_child')
					->where('account', $acc)
					->where('branch_id', $branch_id)
					->where('id', '!=', $id)
					->count();
		
		return $a_count || $c_count;
	}
	
	public function has_master($branch_id)
	{
		//check if this branch already has master
		$master_count = \DB::table('t_account')
			->where('branch_id', $branch_id)
			->where('level_id', 1)
			->count();

		return $master_count >= 1;
	}
	
	public function is_valid_level($level)
	{
		$max_lvl = \DB::table('t_level')->count();
		if ($level <= $max_lvl) {
			return true;
		}

		return false;
	}
	
	private function ban_check($type_id, $target)
	{
		//1 phone, 2 IP, 3 bank
		return \DB::table('t_ban')
			->select('id')
			->whereIn('branch_id', [\Config::get('branch.branch_id'), 1])
			->where([
				['type_id', $type_id],
				['target', $target]
			])->first()? true: false;
	}
	
	public function is_bank_account_dup($bank_id, $bank_account, $branch_id = 0)
	{
		$branch_id = $branch_id?: \Config::get('branch.branch_id');
		$count = \DB::table('t_member_bank')
			->join('t_member', 't_member_bank.member_id', '=', 't_member.id')
			->where('t_member_bank.bank_id', $bank_id)
			->where('t_member_bank.account_no', $bank_account)
			->where('t_member.branch_id', $branch_id)
			->count();
		
		return $count;
	}
	
	public function store_range($amount, $type, $branch_id = 0)
	{
		$result = ['code' => 0, 'data' => ''];
		$data = \DB::table('t_branch')
			->where('id', $branch_id)
			->first()->config_extra;
		$data = json_decode($data, true);
		
		$type = \DB::table('t_order_store_src')->where('id', $type)->first()->name ?? 'ATM';
		// IBON => store_cvs_max
		$type = strtolower(($type == 'IBON')? 'CVS': $type);
		
		$min = ($data['store_' . $type . '_min'] ?? 0)?: 200;
		$max = ($data['store_' . $type . '_max'] ?? 0)?: 20000;
		$min = ($min > $max)? $max: $min;
		if($amount < $min || $amount > $max){
			$result = ['code' => 1, 'text' => '金額需大於' . $min . ', 小於' . $max . ', 如需更多資訊，請聯絡線上客服！'];
		}
		
		return $result;
	}
}