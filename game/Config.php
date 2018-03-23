<?php

namespace Game;

class Config
{
	public function set_agent_config($account_id)
    {
		$games = \DB::table('t_game')
			->where('game', '!=', 'Wallet')
			->where('status_id', 1)
			->get();

		$arr = [];
		foreach($games as $game){
			//new config for each game
			$arr[] = [
				'status_id' => 1,
				'account_id' => $account_id,
				'game_id' => $game->id,
			];
		}
		\DB::table('t_game_agent_config')->insert($arr);
    }

	public function set_member_config($member_id)
    {
		$games = \DB::table('t_game')
			->select(\DB::raw('GROUP_CONCAT(id) as ids'))
			->where('game', '!=', 'Wallet')
			->where('status_id', 1)
			->first()->ids ?? '';
		$game_ids = explode(',', $games);
		\DB::beginTransaction();
			$game_accounts = \App::make('Game\Account')->get_game_account($member_id, $game_ids);

			$arr = [];
			foreach($game_ids as $game_id){
				//new config for each game
				$arr[] = [
					'status_id' => 1,
					'member_id' => $member_id,
					'game_id' => $game_id,
					'game_account_id' => $game_accounts[$game_id] ?? 0,
				];
			}
			\DB::table('t_game_member_config')->insert($arr);
        \DB::commit();
    }

    public static function set_game_config($game_id){
    	//check if $game_id is valid
    	$game = \DB::table('t_game')
    		->select('game')
    		->where('id', $game_id)
    		->first()->game ?? '';
    	if (!$game) {
    		return 'no game found';
    	}

    	//check if account or member config of $game_id already exists, proceed only when both are absent
    	$has_account_cfg = \DB::table('t_game_agent_config')
    		->select(\DB::raw('count(id) as c'))
    		->where('game_id', $game_id)
    		->first()->c ?? 1;
    	if ($has_account_cfg > 0) {
    		return 'account config found';
    	}
    	$has_mem_cfg = \DB::table('t_game_member_config')
    		->select(\DB::raw('count(id) as c'))
    		->where('game_id', $game_id)
    		->first()->c ?? 1;
    	if ($has_mem_cfg > 0) {
    		return 'member config found';
    	}

    	//set configs
    	try{
    		\DB::beginTransaction();
    		self::set_all_acc_cfg($game_id);
	    	self::set_all_mem_cfg($game_id);
    		\DB::commit();
    	}catch(\Exception $e){
    		echo 'something\'s wrong!';
    		\DB::rollback();
    	}
    }

    private static function set_all_acc_cfg($game_id){
    	$accounts = \DB::table('t_account')
    		->select('id')
    		->where('branch_id', '!=', 1) //not admin
    		->get();

    	$insert = [];
    	foreach ($accounts as $acc) {
    		$insert[] = [
    			'status_id' => 1,
    			'account_id' => $acc->id,
    			'game_id' => $game_id,
    			'percent' => 0,
    			'rakeback' => 0,
    		];
    	}

    	\DB::table('t_game_agent_config')->insert($insert);
    	return true;
    }
    private static function set_all_mem_cfg($game_id){
    	//all member
    	$members = \DB::table('t_member')
    		->select('id', 'branch_id')
    		->get();

    	//available game accounts of $game_id
    	$game_accs = \DB::table('t_game_account')
    		->select('id', 'branch_id')
    		->where('game_id', $game_id)
    		->where('status_id', 1)
    		->get();

		$insert = [];

		$branchs = \DB::table('t_branch')
			->select('id')
    		->where('id', '!=', 1) //not admin
			->get();

		foreach ($branchs as $b) {
			$mem = $members->where('branch_id', $b->id); //members of this branch
			$acc = $game_accs->where('branch_id', $b->id);//available game accounts of this branch
			$acc = json_decode(json_encode($acc), true);

			//check if there's enough accounts for all members
			$mem_count = count($mem);
			$acc_count = count($acc);
			echo "setting member config for branch {$b->id}. (member: {$mem_count}, account: {$acc_count})\n";
			if($mem_count > $acc_count){
				echo "not enough account for branch {$b->id}. (member: {$mem_count}, account: {$acc_count})\n";
				// return false;
				continue;
			}

			foreach ($mem as $m) {
				$a = array_shift($acc);
				$insert[] = [
					'member_id' => $m->id,
					'game_id' => $game_id,
					'game_account_id' => $a['id'],
					'rakeback' => 0,
					'status_id' => 1,
				];

				\DB::table('t_game_account')
					->where('id', $a['id'])
					->update([
						'member_id' => $m->id,
						'status_id' => 2,
					]);
			}
		}
    	\Lib\Mix::chunk_insert('t_game_member_config', $insert);
    	return true;
    }

    public static function reset_game_config($game_id){
    	//agent
    	$agent = \DB::table('t_game_agent_config')
    		->where('game_id', $game_id)
    		->delete();
    	echo "reset game agent config.({$agent})\n";

    	//member
    	$member = \DB::table('t_game_member_config')
    		->where('game_id', $game_id)
    		->delete();
    	echo "reset game member config.({$member})\n";

    	//accounts
		$game_acc = \DB::table('t_game_account')
    		->where('game_id', $game_id)
			->update([
				'member_id' => '',
				'status_id' => 1,
			]);
    	echo "reset game accounts.({$game_acc})\n";
    }
}

