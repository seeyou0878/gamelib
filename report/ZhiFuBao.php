<?php

namespace Game\Report;

class ZhiFuBao extends \Game\ZhiFuBao
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
				$account = $val['accountNo'];
				$member_id = $tool->get($account)->member_id ?? 0;
				
				$insert[] = [
					'report_uid' => $val['ticketNo'],//單號
					'bet_date' => $val['bettime'] / 1000,//投注時間
					'set_date' => $val['instime'] / 1000,//結帳時間
					'bet_amount' => $val['betAmt'],//投注金額
					'set_amount' => $val['validAmt'],//有效投注
					'win_amount' => ($val['statusCode'] == 'P')? ($val['payAmt'] - $val['betAmt']): 0,//輸贏結果
					'type_id' => (($val['payAmt'] - $val['betAmt']) > 0)? 1: ((($val['payAmt'] - $val['betAmt']) < 0)? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_zhifubao', $insert);
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

	public function _get_report($sdate, $edate)
	{
		$result = [];
		
		for($s = $sdate; $s < $edate; $s += 1799){
			$e = $s + 1799;
			if($e > $edate){
				$e = $edate;
			}
			
			$data = @$this->__get_report($s, $e);
			$result = array_merge($result, $data['data'] ?? []);
		}
		
		return ['code' => 0, 'data' => $result];
	}
	
	public function __get_report($sdate, $edate, $page=1)
	{
		$result = [];
		
		while(1){
			
			$data = @$this->___get_report($sdate, $edate, $page++);
			$result = array_merge($result, json_decode($data['data']['result'] ?? '[]', true));
			
			if(!($data['data']['msg'] ?? 0)){
				break;
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	//30s of report
	public function ___get_report($sdate, $edate, $page=1)
	{
		$post = [
			'fromDate' => date('Y-m-d H:i:s', $sdate),
			'toDate' => date('Y-m-d H:i:s', $edate),
			'gamePlatform' => 'ZFB', 
			'remark' => $page,
		];
		
		$params = $this->_des_encrypt($this->_build_str($post), $this->api['_des_key']);
		$md5 = md5($params . $this->api['_md5_key']);
		$params = urlencode($params);
		
		$url = $this->api['_url'] . '?params=' . $params . '&key=' . $md5;
		$result = \App::make('Lib\Curl')
			->set_header('KK: WEB_KK_GI_' . $this->api['_cagent'])
			->set_header('METHOD: WEB_KK_MD_BT')
			->curl($url, '', false);
			
		$result = json_decode($result, true);
		if(($result['code'] ?? null) === 0){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	public static function parse_bet_type($info){
		$pool = ['進球時間', '波膽', '半場波膽', '總進球'];
		$type = [
			0 => [
				69843 => '0 - 10 分鐘',
				69844 => '11 - 20 分鐘',
				69845 => '21 - 30 分鐘',
				69846 => '31 - 40 分鐘',
				69847 => '41 - 50 分鐘',
				69848 => '51 - 60 分鐘',
				69849 => '61 - 70 分鐘',
				69850 => '71 - 80 分鐘',
				69851 => '80 - 終場',
				69852 => '終場',
			],
			1 => [
				1  => '0 - 0',
				2  => '1 - 0',
				3  => '1 - 1',
				4  => '0 - 1',
				5  => '2 - 0',
				6  => '2 - 1',
				7  => '2 - 2',
				8  => '1 - 2',
				9  => '0 - 2',
				10 => '3 - 0',
				11 => '3 - 1',
				12 => '3 - 2',
				13 => '3 - 3',
				14 => '2 - 3',
				15 => '1 - 3',
				16 => '0 - 3',
				9063254 => '主隊贏 進球四以上',
				9063255 => '客隊贏 進',
			],
			2 => [
				1 => '0 - 0',
				2 => '1 - 0',
				3 => '1 - 1',
				4 => '0 - 1',
				5 => '2 - 0',
				6 => '2 - 1',
				7 => '2 - 2',
				8 => '1 - 2',
				9 => '0 - 2',
				4506345 => '其他',
			],
			3 => [
				285469 => '1球',
				285470 => '2球以上',
				285471 => '3球以上',
				285472 => '4球以上',
				285473 => '5球以上',
				285474 => '6球以上',
				285475 => '7球以上',
			]
		];
		
		return ($pool[$info->oddsType ?? 0] ?? '') . '(' . ($type[$info->oddsType ?? 0][$info->betType ?? 0] ?? '') . ')';
	}

	public static function get_game($arr){
		$id = $arr['oddsType'] ?? '';
		$type = [
			0 => '進球時段',
			1 => '波膽', 
			2 => '半場波膽', 
			3 => '總得分',
		];

		return $type[$id] ?? '';
	}

	public static function parse_status($id = -1){
		$type = [
			'N' => '', // '正常票',
			'P' => '',// '已派彩',
			'C' => '取消票',
		];

		return $type[$id] ?? '';
	}

	public static function create_bet_content_column($info){
		//Game Result
		$teams = "[{$info->leagueName}] <br/> {$info->homeTeam} vs {$info->awayTeam}";

		$memo = explode(',', $info->memo); //备注： 半场波胆 （如： 2-1）, 波胆 （如： 3-2）， 总得分 （如： 5），首球时间 （如： 15 ）
		$results = [$memo[3] ?? '', $memo[1] ?? '', $memo[0] ?? '', $memo[2] ?? '']; //rearrange memo to fit the index of oddsType
		$result = $results[$info->oddsType];
		$result = ($result != 'null')? "{$result}" : '';

		$bet = self::parse_bet_type($info);
		$status = self::parse_status($info->statusCode);
		return "<span class='small'> {$teams} <br/> <span style='color: red'>{$bet}</span> [{$status}{$result}] </span>";
	}
}