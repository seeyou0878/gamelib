<?php

namespace Game\Report;

class XinXin extends \Game\XinXin
{
	function __construct()
	{
		parent::__construct();
	}

	public function set_report()
	{
		$result = $this->get_report();
		
		if($result['data'] ?? 0){
			
			$report = $result['data'];
			$insert = [];
			
			$tool = \App::make('Game\Account')->init($this->_lib);
			foreach($report as $val){
				$info = json_encode($val, JSON_UNESCAPED_UNICODE);
				$account = $val['meusername1'];
				$member_id = $tool->get($account)->member_id ?? 0;
				
				$insert[] = [
					'report_uid' => $val['id'],//單號
					'bet_date' => strtotime($val['added_date']),//投注時間
					'set_date' => strtotime($val['orderdate']),//結帳時間
					'bet_amount' => $val['gold'],//投注金額
					'set_amount' => $val['gold_c'] ?? 0,//有效投注
					'win_amount' => $val['meresult'],//輸贏結果
					'type_id' => $this->result($val['result']),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_xinxin', $insert);
		}
	}

	// 取得各站報表, 忽略錯誤
	public function get_report()
	{
		$branch = \DB::table('t_branch')->where('id', '!=', 1)->get();
		
		$result = [];
		
		foreach($branch as $val){
			
			// init with branch_id
			$this->init($val->id);
			
			$data = @$this->_get_report();
			
			if(!count($result)){
				$result = $data['data'];
			}else{
				$result = array_merge($result, $data['data']);
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 處理分頁
	public function _get_report($maxModId = null)
	{
		$result = [];
		// workaround for xinxin api. If there is an update, this snippet must be removed.
		if(!$maxModId){
			$r = \DB::table('t_report_xinxin')->orderBy('id', 'desc')->first();
			$r = json_decode($r->info, true) ?? 0;
			$maxModId = $r['mrid'] ?? 0;
		}
		
		while(1){// 取得下一頁
			$maxModId = $data['data']['maxModId'] ?? $maxModId;
			$data = $this->__get_report($maxModId);
			$result = array_merge($result, $data['data']['wgs'] ?? []);
			
			if(($data['data']['more'] ?? 0) != 1){
				break;
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 單次取得報表
	public function __get_report($maxModId = null)
	{
		$post = [
			'maxModId' => $maxModId,
			'checked' => 0, //所有注單
		];

		$result = \App::make('Lib\Curl')->set_header('api_key: ' . $this->_security_code)
			->curl($this->api['_url'] . '/api/getTix.php', $post);
		$result = json_decode($result, true);//轉成陣列

		if(($result['code'] ?? null) === '001'){
			return ['code' => 0, 'data' => $result];
		}else{ // 回傳錯誤
			return ['code' => 7, 'text' => (string)$result['msg'] ?? ''];
		}
	}

	public function result($result){
		if(in_array($result, ['W', 'WW'])) {
			return 1;
		}else if(in_array($result, ['L', 'LL', 'WL'])){
			return 2;
		}else{
			return null;
		}
	}

	public static function get_game($info){
		return $info['g_title'] . '<br/>' . $info['r_title'];
	}

	public static function create_bet_content_column($info){
		return $info->detail_1;
	}
}