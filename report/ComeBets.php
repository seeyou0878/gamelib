<?php

namespace Game\Report;

class ComeBets extends \Game\ComeBets
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
			
			$tool = \App::make('Game\Account')->init('ComeBets');
			foreach($report as $val){
				$info = json_encode($val, JSON_UNESCAPED_UNICODE);
				$account = $val['username'];
				$member_id = $tool->get($account)->member_id ?? 0;
				
				$insert[] = [
					'report_uid' => ($val['game_name'] . '_' . $val['id']),//單號
					'bet_date' => strtotime($val['time']),//投注時間
					'set_date' => strtotime($val['time']),//結帳時間
					'bet_amount' => $val['bet_total_credit'],//投注金額
					'set_amount' => $val['bet_total_credit'],//有效投注
					'win_amount' => $val['user_credit_diff'],//輸贏結果
					'type_id' => ($val['user_credit_diff'] > 0)? 1: (($val['user_credit_diff'] < 0)? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_comebets', $insert);
		}
	}

	// 處理遊戲類型
	public function get_report($sdate, $edate)
	{
		$result = [];
		
		for($i = 1; $i <= 5; $i++){
			$data = $this->_get_report($sdate, $edate, $i);
			$result = array_merge($result, $data['data']);
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 處理分頁
	public function _get_report($sdate, $edate, $type = 1, $page = 1)
	{
		$result = [];
		
		while(1){// 取得下一頁
			$data = $this->__get_report($sdate, $edate, $type, $page++);
			$result = array_merge($result, $data['data']['histories'] ?? []);
			
			if(count($data['data']['histories'] ?? []) == 0){
				break;
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 查詢投注紀錄
	public function __get_report($sdate, $edate, $type = 1, $page = 1)
	{
		$post = [
			'account_id'   => $this->_account_id,
			'date_from'    => date('Y-m-d H:i:s', $sdate),
			'date_to'      => date('Y-m-d H:i:s', $edate),
			'page'         => $page,
			'page_count'   => 1000,
			'game_type'    => $type,
			'session_guid' => $this->_get_sessionGUID(),
		];
		$post['check_code'] = $this->_get_check_code($post);
		
		$result = \App::make('Lib\Curl')->curl($this->_url . '/v1/api/histories', $post);
		$result = json_decode($result, true);
		
		if(($result['status'] ?? null) === true){
			return ['code' => 0, 'data' => $this->adjust($result, $type)];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	public function adjust($arr, $type)
	{
		foreach($arr['histories'] ?? [] as $key=>$val){
			$arr['histories'][$key]['username'] = str_replace('77bf_agent_', '', $arr['histories'][$key]['username']);
			$arr['histories'][$key]['game_type'] = $type;
		}
		return $arr;
	}

	public static $game_types = array(
		1 => '老虎機',
		2 => '捕魚機',
		3 => '輪盤',
		4 => '5PK',
		5 => '鑽石列車',
	);

	/**
	 * 取得遊戲類型字串
	 * @param $game_type 遊戲類型 int
	 * @return mixed
	 */
	public static function get_game_type_string($game_type){
		return (isset(self::$game_types[$game_type])) ? self::$game_types[$game_type] : $game_type;
	}

	public static function get_game($info){
		$game_type = 'game_type';
		$game_type_string = self::get_game_type_string($info['game_type']);

		return $game_type_string . '<br>' . $info['game_name'];
	}
}