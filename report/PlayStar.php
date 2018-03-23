<?php

namespace Game\Report;

class PlayStar extends \Game\PlayStar
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
				$account = $val['username'];
				$member_id = $tool->get($account)->member_id ?? 0;

				$insert[] = [
					'report_uid' => $val['sn'],//單號
					'bet_date' => strtotime($val['tm']),//投注時間
					'set_date' => strtotime($val['tm']),//結帳時間
					'bet_amount' => $val['bet']/100,//投注金額
					'set_amount' => $val['bet']/100,//有效投注
					'win_amount' => ($val['win']-$val['bet'])/100,//輸贏結果
					'type_id' => ($val['win'] > 0? 1: 2),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_playstar', $insert);
		}
	}

	// 取得各站報表, 忽略錯誤
	public function get_report($sdate, $edate)
	{
		$branch = \DB::table('t_branch')->where('id', '!=', 1)->get();

		$result = [];

		foreach($branch as $val){

			// init with branch_id
			$this->init($val->id);

			$data = @$this->_get_report($sdate, $edate);

			$result = array_merge($result, $data['data'] ?? []);
		}

		return ['code' => 0, 'data' => $result];
	}
	
	public function _get_report($sdate, $edate)
	{
		$post = [
			'host_id' => $this->api['_host_id'],
			'start_dtm' => date('Y-m-d\TH:i:s', $sdate),
			'end_dtm' => date('Y-m-d\TH:i:s', $edate),
			'type' => 0,
		];

		$url = $this->api['_url'] . '/feed/gamehistory/?' . http_build_query($post);
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		$result = json_decode($result, true);

		if($result ?? 0){
			return ['code' => 0, 'data' => $this->adjust($result)];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	public function adjust($result)
	{
		$arr = [];
		foreach($result as $date=>$user_data){
			foreach($user_data as $key=>$val){
				foreach($val as $data){
					$tmp = $data;
					$tmp['tm'] = $date . ' ' . $tmp['tm']; 
					$tmp['username'] = $key;
					$arr[] = $tmp;
				}
			}
		}
		return $arr;
	}
	
	public static function get_game($info = [])
	{
		$game = $info['gid'] ?? 0;

		$type = [
			1 => 'PSS-ON-00005',
			2 => 'PSS-ON-00019',
			3 => 'PSS-ON-00035',
			4 => 'PSS-ON-00038',
			5 => 'PSS-ON-00044',
			'PSS-ON-00005' => '印度之寶',
			'PSS-ON-00019' => '天子',
			'PSS-ON-00035' => '狼人',
			'PSS-ON-00038' => '變臉',
			'PSS-ON-00044' => '金雞報喜',
		];

		return $game? ($type[$game] ?? $game): $type;
	}
}