<?php

namespace Game;

class Account
{
	protected $id;
	protected $acc;

	public function __construct(){
		$this->id = '';
		$this->acc = [];
	}

	public function init($game)
	{
		if(is_string($game)){
			$this->id = \DB::table('t_game')->where('game', $game)->first()->id ?? 0;
		}else{
			$this->id = $game;
		}
		
		return $this;
	}

	public function get($account)
	{
		if($this->acc[$account] ?? 0){
			$result = $this->acc[$account];
		}else{
			$result = \DB::table('t_game_account')
				->where('account', $account)
				->where('game_id', $this->id)
				->first();
			$this->acc[$account] = $result ?? -1;
		}
		
		return $result;
	}

	// 取得近期登入帳號
	public function get_recent($branch_id, $period = 7*86400)
	{
		$result = '';
		$tmp = [];
		
		$acc = \DB::table('t_game_account')
			->where('game_id', $this->id)
			->where('branch_id', $branch_id)
			->where('login_time', '>', time() - $period)
			->get();
		
		foreach($acc as $v){
			$tmp[$v->account] = $v->account;
		}
		$result = implode(',', $tmp);
		
		return $result;
	}

	public function get_game_account($member_id, $game_id)
	{
		// $branch_id = \Config::get('branch.branch_id');
		$branch_id = \DB::table('t_member')
			->select('branch_id')
			->where('id', $member_id)
			->first()->branch_id;

		$arr = [];
		//Get available accounts for each game
		$query = \DB::table('t_game_account')
			->select('id', 'game_id')
			->where('status_id', 1)
			->where('branch_id', $branch_id)
			->groupBy('game_id');

		if(is_array($game_id)){
			$query->whereIn('game_id', $game_id);
		}else{
			$query->where('game_id', $game_id);
		}

		$available_accs = $query->lockForUpdate()->get();

		//Fill in the acc template
		foreach($available_accs as $acc){
			$arr[$acc->game_id] = $acc->id;
		}

		//Make assigned account unavailable
		\DB::table('t_game_account')
			->whereIn('id', $arr)
			->update([
				'status_id' => 2,
				'member_id' => $member_id,
			]);

		return $arr;
	}

	// deal with lack of game accounts
	public function set_game_account(){
		
		$acc = \DB::table('t_game_member_config')
			->where('game_account_id', 0)
			->get();
			
		$len = count($acc);
		$i = 0;
		foreach($acc as $v){
			\DB::beginTransaction();
			$member_id = $v->member_id;
			$game_id = $v->game_id;
			
			$new_acc = $this->get_game_account($member_id, $game_id);
			$new_acc = array_values($new_acc)[0] ?? 0; //get first index
			
			if(!$new_acc){
				$str = 'member_id: ' . $member_id . ' game_id: ' . $game_id . ' failed' . '(' . (++$i) . '/' . $len . ')' . "\n";
			}else{
				// update account
				$id = \DB::table('t_game_member_config')
					->where('member_id', $member_id)
					->where('game_id', $game_id)
					->first()->id;
					
				\DB::table('t_game_member_config')
					->where('id', $id)
					->update(['game_account_id' => $new_acc]);
				
				$str = 'member_id: ' . $member_id . ' game_id: ' . $game_id . ' success' . '(' . (++$i) . '/' . $len . ')';
				echo "\033[999D";
			}
			echo $str;
			
			\DB::commit();
		}
	}

	public static function generate_accounts($branch_id, $qty, $game_id){
		echo "=====================START======================\n";

		//game class
		$game_name = \DB::table('t_game')
			->select('game')
			->where('id', $game_id)
			->first()->game;
		$game = \App::make('Game\\' . $game_name);

		//api params
		\Config::set('branch.branch_id', $branch_id);
		$game->init($branch_id);
		var_dump($game->api);

		/*account & pwd*/
		//prefix
		$prefix = \DB::table('t_branch')
			->select('code')
			->where('id', $branch_id)
			->first()->code ?? '';
		if(strlen($prefix) != 4){
			return "branch code not found or length != 4";
		}

		//account
		$account = \DB::table('t_game_account')
			->select('account')
			->where('game_id', $game_id)
			->where('branch_id', $branch_id)
			->where('account', 'like', $prefix . '%')
			->orderBy('account', 'desc')
			->limit(1)
			->first()->account ?? '00000';
		$count = (int)substr($account, 4)+1;

		echo "initial account serial number is " . $count . "\n";
		if ($count + $qty >= 9999999) {
			return "{$count} + {$qty} exceeds max acceptable number 9999999.";
		}else{
			echo "start generating account from {$prefix}{$count} to {$prefix}" . ($count+$qty-1) . " for game {$game_name}\n";
		}
		$pwd = 'Ab1' . str_random(7); //same pwd for every generated accounts

		//generate
		$insert = [];
		$time = time();
		for ($i=1; $i <= $qty; $i++) { 
			$acc = $prefix . str_pad($count, 7, '0', STR_PAD_LEFT);
			$result = $game->add_account($acc, $pwd);

			if ($result['code'] == 7) {
				//fail
				$i--;
				echo "fail when generating account {$acc}\n";
			}else{
				$acc = ($game_name == 'EvoPlay')? ($prefix . str_pad($result['data']['user_id'] ?? 'error', 7, '0', STR_PAD_LEFT)): $acc;
				//success
				$insert[] = [
					'branch_id' => $branch_id,
					'game_id' => $game_id,
					'account' => $acc,
					'status_id' => 1,
					'password' => encrypt($pwd),
				];

				//display progress
				$len = 15 + strlen($i) + strlen($qty);
				echo "\033[{$len}D";
				echo "{$acc} ({$i}/{$qty})";
			}

			//insert every 1000 rows or last one
			if (count($insert) == 100 || $i == $qty) {
				\DB::table('t_game_account')->insert($insert);
				$insert = [];
			}
			$count++;
		}

		echo "\n";
		echo "=====================FIN======================\n";
		return "time spent " . (time() - $time) . "s";
	}
}