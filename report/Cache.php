<?php

namespace Game\Report;

class Cache
{
	public static $ctrl = [
		'allbet'       => [0,       86399,      0, 'bet'], //時間起, 時間迄, 時間偏移, 採結帳時間
		'salon'        => [-43200, 129599, -43200, 'bet'],
		'globalgaming' => [0,       86399,      0, 'bet'],
		'comebets'     => [0,       86399,      0, 'bet'],
		'microsova'    => [0,       86399,      0, 'bet'],
		'supersport'   => [0,       86400,      0, 'set'],
		'xinxin'   	   => [0,       86400,      0, 'set'],
		'ebet'   	   => [0,       86399,      0, 'bet'],
		'dreamgame'	   => [0,       86399,      0, 'bet'],
		'orientalgame' => [0,       86399,      0, 'bet'],
		'zhifubao' 	   => [0,       86399,      0, 'bet'],
		//'goldenasia'   => [-43200, 129599, -43200, 'set'],
		//'goldenclub'   => [-43200, 129599, -43200, 'bet'],
		'ninetynine'   => [0,       86399,      0, 'bet'],
		'globebet'     => [0,       86399,      0, 'set'],
		'playstar'     => [0,       86399,      0, 'bet'],
		'evoplay'      => [-28800, 129599, -28800, 'bet'],
		'booongo'      => [0,       86399,      0, 'bet'],
	];
	
	public function set_cache($sdate, $edate, $game = '')
	{
		$alter = self::$ctrl;
		
		if($game){
			$s = $alter[$game][0] + ($sdate ?? strtotime(date('Y-m-d'))); //時間起
			$e = $alter[$game][1] + ($edate ?? strtotime(date('Y-m-d'))); //時間迄
			$m = $alter[$game][2];  //偏移量
			$c = $alter[$game][3] . '_date';  //採結帳時間
			
			$r = \DB::table('t_report_' . $game)
				->select(
					\DB::raw('SUM(IF(status_id = 1, bet_amount, 0)) as "sbet"'),
					\DB::raw('SUM(IF(status_id = 1, set_amount, 0)) as "sset"'),
					\DB::raw('SUM(IF(status_id = 1, win_amount, 0)) as "swin"'),
					\DB::raw('SUM(IF(status_id = 2, bet_amount, 0)) as "hbet"'),
					\DB::raw('SUM(IF(status_id = 2, set_amount, 0)) as "hset"'),
					\DB::raw('SUM(IF(status_id = 2, win_amount, 0)) as "hwin"'),
					\DB::raw('UNIX_TIMESTAMP(FROM_UNIXTIME(' . $c . ' + ' . $m . ', "%Y-%m-%d")) as kbet_date'),
					'member_id'
				)
				->where($c, '>=', $s)
				->where($c, '<=', $e)
				->groupBy('kbet_date', 'member_id')
				->get();
			
			$insert = [];
			foreach($r as $v){
				$insert[] = [
					'member_id' => $v->member_id,
					'bet_date'  => $v->kbet_date,
					'bet_'  . $game => $v->sbet,
					'set_'  . $game => $v->sset,
					'win_'  . $game => $v->swin,
					'hbet_' . $game => $v->hbet,
					'hset_' . $game => $v->hset,
					'hwin_' . $game => $v->hwin,
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_cache', $insert);
			
		}else{
			foreach($alter as $k=>$v){
				$this->set_cache($sdate, $edate, $k);
			}
		}
	}

	public function set_report($stime, $etime)
	{
		\App::make('Game\Report\Allbet')->set_report($stime - 60, $etime - 60);// time
		\App::make('Game\Report\Salon')->set_report($stime, $etime);// time
		\App::make('Game\Report\Microsova')->set_report($stime - 600, $etime - 600);// time
		\App::make('Game\Report\ComeBets')->set_report($stime, $etime);// time
		\App::make('Game\Report\GlobalGaming')->set_report($stime - 600, $etime - 600);// time
		\App::make('Game\Report\SuperSport')->set_report(strtotime(date('Y-m-d', $stime)), strtotime(date('Y-m-d', $etime)) + 86400);// date 2日
		\App::make('Game\Report\XinXin')->set_report();// no timespan needed, get past 7days
		\App::make('Game\Report\Ebet')->set_report($stime, $etime);// time
		\App::make('Game\Report\DreamGame')->set_report($stime, $etime);// time not needed
		\App::make('Game\Report\OrientalGame')->set_report($stime, $etime);// time
		\App::make('Game\Report\ZhiFuBao')->set_report($stime, $etime);// time
		\App::make('Game\Report\NinetyNine')->set_report($stime, $etime);// time
		\App::make('Game\Report\GlobeBet')->set_report($stime, $etime);// time
		\App::make('Game\Report\PlayStar')->set_report($stime, $etime);// time
		\App::make('Game\Report\EvoPlay')->set_report($stime, $etime);// time
		\App::make('Game\Report\BooonGo')->set_report($stime, $etime);// time
	}

	public function repair()
	{
		//回補報表
		$get = \Request::all();
		$stime = strtotime($get['stime'] ?? 0);
		$etime = strtotime($get['etime'] ?? 0);
		$game = \Request::get('game') ?? '';
		$account = \Request::get('account') ?? '';
		$data = \DB::table('t_game_account as gc')
			->join('t_game as g', 'gc.game_id', '=', 'g.id')
			->where('account', $account)
			->where('g.game', $game)
			->first();

		if($data){
			\App::make("Game\Report\\{$game}")->set_report($stime, $etime, $account);
			// update the log time
			\DB::table('t_game_account')->where('id', $data->id)->update(['login_time' => time()]);
			
		}else{
			$err = ['Repair page need params', 'game(library name)', 'account(in games)', 'stime(yyyy-mm-dd hh:ii:ss)', 'etime(yyyy-mm-dd hh:ii:ss)' ];
			
			dd($err);
		}
		
		dd('fin');
	}
}
