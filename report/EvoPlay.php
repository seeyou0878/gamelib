<?php

namespace Game\Report;

class EvoPlay extends \Game\EvoPlay
{
	function __construct()
	{
		parent::__construct();
	}

	public function set_report($sdate, $edate)
	{
		$result = $this->get_report($sdate, $edate);

		if($result['data'] ?? 0){

			$report = $result['data'];
			$insert = [];

			$tool = \App::make('Game\Account')->init($this->_lib);
			foreach($report as $val){
				$info = json_encode($val, JSON_UNESCAPED_UNICODE);
				$account = $val['data']['user']['user_id'];
				$member_id = $tool->get($account)->member_id ?? 0;

				$insert[] = [
					'report_uid' => $val['event_id'],//單號
					'bet_date' => $val['time'],//投注時間
					'set_date' => $val['time'],//結帳時間
					'bet_amount' => $val['data']['pay_for_action_this_round'] ?? 0,//投注金額
					'set_amount' => $val['data']['pay_for_action_this_round'] ?? 0,//有效投注
					'win_amount' => (($val['data']['balance'] ?? 0) - ($val['data']['balance_before_pay'] ?? 0)),//輸贏結果
					'type_id' => (($val['data']['total_win'] ?? 0)> 0? 1: 2),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
				
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_evoplay', $insert);
		}
	}

	public function get_report($sdate, $edate)
	{
		$datas = \DB::table('t_report_evoplay')
			->where('bet_date', 0)
			->select('info')
			->get();

		$result = [];
		foreach($datas as $data){
			$data = json_decode($data->info, true);

			$gcg_account = \DB::table('t_game_account')
				->join('t_game', 't_game_account.game_id', '=', 't_game.id')
				->where('t_game.game', $this->_lib)
				->where('t_game_account.account', 'like', '%' . str_pad($data['data']['user']['user_id'] ?? 0, 7, '0', STR_PAD_LEFT))
				->value('t_game_account.account');
			
			$data['data']['user']['user_id'] = $gcg_account;
			$result[] = $data ?? '';
		}
		
		if($result ?? 0){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}
	
	public function _get_report()
	{
		$data = \Request::all();
		
		unset($data['event']['data']['game']['absolute_name']);
		if($data['event']['data']['game'] ?? 0){
			if(isset($data['event']['data']['pay_for_action_this_round'])){
				\App::make('Lib\Mix')->insert_duplicate('t_report_evoplay', [[
					'report_uid' => $data['event']['event_id'] ?? 0,
					'info' => json_encode($data['event']),
				]]);
			}
		}

		return '{"status":"ok"}';
	}

	public static function get_game($info)
	{
		$game = $info['data']['game']['game_id'] ?? 0;

		$type = [
			4   => '埃及眾神',
			7   => '神秘麻將',
			10  => '激情籃球',
			13  => '轉運護身符',
			16  => '長城探寶',
			19  => '羅賓漢',
			22  => '新春大吉',
			25  => '海盜之戰',
			28  => '西遊記',
			31  => '少林傳奇',
			34  => '拉斯維加斯之夜',
			37  => '珠寶店',
			76  => '赤壁之戰',
			79  => '印第安納',
			82  => '妖精打架',
			85  => '反恐部隊',
			88  => '熱帶水果',
			94  => '神鬼傳奇',
			95  => '秦始皇',
			98  => '亞特蘭提斯',
			101 => '勇者傳說',
			104 => '女孩壞壞',
			107 => '足球女孩',
		];

		return $type[$game] ?? $game;
	}
}