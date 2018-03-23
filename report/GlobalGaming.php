<?php

namespace Game\Report;

class GlobalGaming extends \Game\GlobalGaming
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
				$account = $val['accountno'];
				$member_id = $tool->get($account)->member_id ?? 0;
				
				$insert[] = [
					'report_uid' => $val['autoid'],//單號
					'bet_date' => strtotime($val['bettimeStr']),//投注時間
					'set_date' => strtotime($val['bettimeStr']),//結帳時間
					'bet_amount' => $val['bet'],//投注金額
					'set_amount' => $val['bet'],//有效投注
					'win_amount' => $val['profit'],//輸贏結果
					'type_id' => ($val['profit'] > 0)? 1: (($val['profit'] < 0)? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_globalgaming', $insert);
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
			
			$result = array_merge($result, $data['data']);
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 查詢投注紀錄 合併每10分鐘資料
	public function _get_report($sdate, $edate)
	{
		$result = [];
		
		for($s = $sdate; $s < $edate; $s += 600){
			$e = $s + 600;
			if($e > $edate){
				$e = $edate;
			}
			
			$data = $this->__get_report($s, $e);
			$result = array_merge($result, $data['data']['recordlist'] ?? []);
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 查詢投注紀錄 只可取10分鐘區間
	public function __get_report($sdate, $edate)
	{
		$post = [
			'cagent' => $this->api['_cagent'],
			'startdate' => date('Y-m-d H:i:s', $sdate),
			'enddate' => date('Y-m-d H:i:s', $edate),
			'method' => 'br',
		];
		
		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		
		$url = $this->api['_url_report'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')->set_header('GGaming:WEB_GG_GI_' . $this->api['_cagent'])->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 0){
			return ['code' => 0, 'data' => $this->adjust($result)];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	public function adjust($arr)
	{
		foreach($arr['recordlist'] ?? [] as $key=>$val){
			$arr['recordlist'][$key]['accountno'] = str_replace($this->api['_cagent'], '', $arr['recordlist'][$key]['accountno']);
		}
		return $arr;
	}

	public static function get_game($arr)
	{
		$game = $arr['gameId'] ?? '';
		$type = array(
			101 => '捕魚天下',
			102 => '水果機',
			103 => '單挑王',
			104 => '金鯊銀鯊',
			105 => '幸運五張',
			106 => '大魚吃小魚',
			107 => '射龍門',
			108 => '鑽石謎城',
		);
		
		return $type[$game] ?? '';
	}
}