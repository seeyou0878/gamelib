<?php

namespace Game\Report;

class Ebet extends \Game\Ebet
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
				
				//get total bet amount by looping through betMap and sump up betMoney
				$bet_amount = 0;
				foreach ($val['betMap'] as $b) {
					$bet_amount += $b['betMoney'];
				}

				$insert[] = [
					'report_uid' => $val['roundNo'] . $val['userId'],//單號
					'bet_date' => $val['createTime'],//投注時間
					'set_date' => $val['payoutTime'],//結帳時間
					'bet_amount' => $bet_amount,//投注金額
					'set_amount' => $val['validBet'],//有效投注
					'win_amount' => ($val['payout'] - $bet_amount),//輸贏結果
					'type_id' => ($val['payout'] > $val['validBet'])? 1: (($val['payout'] < $val['validBet'])? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_ebet', $insert);
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
			
			if(!count($result)){
				$result = $data['data'];
			}else{
				$result = array_merge($result, $data['data']);
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 處理分頁
	public function _get_report($sdate, $edate, $page = 1)
	{
		$result = [];
		
		while(1){// 取得下一頁
			$data = $this->__get_report($sdate, $edate, $page++);
			$max = $data['data']['count'] ?? 0;
			$result = array_merge($result, $data['data']['betHistories'] ?? []);
			
			if(count($result) >= $max){
				break;
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 單次取得報表
	public function __get_report($sdate, $edate, $page = 1)
	{
		$time = time();
		$post = [
			'channelId' => $this->_channelId,
			'subChannelId' => $this->api['_sub_channel_id'],
			'startTimeStr' => date('Y-m-d H:i:s', $sdate),//起始時間
			'endTimeStr' => date('Y-m-d H:i:s', $edate),//結束時間
			'pageNum' => $page,//頁碼
			'pageSize' => 5000,
			'timestamp' => $time,
			'signature' => $this->get_signature($time),
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/api/userbethistory', $post);
		
		$result = json_decode($result, true);
		if(($result['status'] ?? null) === 200){			
			return ['code' => 0, 'data' => $result];
		}else{ // 回傳錯誤
			return ['code' => 7, 'text' => ''];
		}
	}

	/**
	 * 取得遊戲類型字串
	 * @param $game_type 遊戲類型 int
	 * @return mixed
	 */
		public static function parse_game_type($id=-1)
		{
			$type = [            
				1 => '百家樂',
				2 => '龍虎',
				3 => '骰寶',
				4 => '輪盤',
				5 => '水果機',
			];

			return $type[$id] ?? '';
		}

		public static function parse_bet($id=-1)
		{
			$type = [
				60 => '閑',
				66 => '閑對',
				68 => '和',
				80 => '莊',
				88 => '莊對',
				10 => '龍',
				11 => '虎',
				68 => '和',
				100 => '單',
				101 => '雙',
				102 => '大',
				103 => '小',
				104 => '對子1',
				105 => '對子2',
				106 => '對子3',
				107 => '對子4',
				108 => '對子5',
				109 => '對子6',
				110 => '圍骰1',
				111 => '圍骰2',
				112 => '圍骰3',
				113 => '圍骰4',
				114 => '圍骰5',
				115 => '圍骰6',
				116 => '全圍',
				117 => '4 點',
				118 => '5 點',
				119 => '6 點',
				120 => '7 點',
				121 => '8 點',
				125 => '9 點',
				126 => '10 點',
				127 => '11 點',
				128 => '12 點',
				129 => '13 點',
				130 => '14 點',
				131 => '15 點',
				132 => '16 點',
				133 => '17 點',
				134 => '單點數',
				135 => '單點數2',
				136 => '單點數3',
				137 => '單點數4',
				138 => '單點數5',
				139 => '單點數6',
				140 => '組合1-2',
				141 => '組合1-3',
				142 => '組合1-4',
				143 => '組合1-5',
				144 => '組合1-6',
				145 => '組合2-3',
				146 => '組合2-4',
				147 => '組合2-5',
				148 => '組合2-6',
				149 => '組合3-4',
				150 => '組合3-5',
				151 => '組合3-6',
				152 => '組合4-5',
				153 => '組合4-6',
				154 => '組合5-6',
				155 => '二同號',
				156 => '三同號',
				200 => '直接注',
				201 => '分注',
				202 => '街注',
				203 => '角注',
				204 => '三數',
				205 => '四個號碼',
				206 => '線注',
				207 => '列注',
				208 => '打注',
				209 => '紅',
				210 => '黑',
				211 => '單',
				212 => '雙',
				213 => '大',
				214 => '小',
			];

			return $type[$id] ?? '';
		}

		public static function parse_card($id=-1)
		{
			$type = [
				0 => '梅花2',
				13 => '方片2',
				26 => '紅桃2',
				39=> '黑桃2',
				1 => '梅花3',
				14 => '方片3',
				27 => '紅桃3',
				40=> '黑桃3',
				2 => '梅花4',
				15 => '方片4',
				28 => '紅桃4',
				41=> '黑桃4',
				3 => '梅花5',
				16 => '方片5',
				29 => '紅桃5',
				42=> '黑桃5',
				4 => '梅花6',
				17 => '方片6',
				30 => '紅桃6',
				43=> '黑桃6',
				5 => '梅花7',
				18 => '方片7',
				31 => '紅桃7',
				44=> '黑桃7',
				6 => '梅花8',
				19 => '方片8',
				32 => '紅桃8',
				45=> '黑桃8',
				7 => '梅花9',
				20 => '方片9',
				33 => '紅桃9',
				46=> '黑桃9',
				8 => '梅花T',
				21 => '方片T',
				34 => '紅桃T',
				47=> '黑桃T',
				9 => '梅花J',
				22 => '方片J',
				35 => '紅桃J',
				48=> '黑桃J',
				10 => '梅花Q',
				23 => '方片Q',
				36 => '紅桃Q',
				49=> '黑桃Q',
				11 => '梅花K',
				24 => '方片K',
				37 => '紅桃K',
				50=> '黑桃K',
				12 => '梅花A',
				25 => '方片A',
				38 => '紅桃A',
				51=> '黑桃A',
			];

			return $type[$id] ?? '';
		}

	/**
	 * 取得遊戲類型字串
	 * @param $game_type 遊戲類型 int
	 * @return mixed
	 */
	public static function get_game_type_string($game_type){
		return (self::parse_game_type($game_type)) ? self::parse_game_type($game_type) : $game_type;
	}

	public static function get_game($info){
		return self::get_game_type_string($info['gameType']);
	}

	public static function create_bet_content_column($info){
		//result
		$result = '';
		switch ($info->gameType) {
			case 1:	//'百家乐',
				$func_parse = function($cards){
					array_walk($cards, function($card) use(&$txt) {
						$txt .= self::parse_card($card);
						$txt .= ' ';
					});
					return "[{$txt}]";
				};
				
				$bankerCards = $func_parse($info->bankerCards);
				$playerCards = $func_parse($info->playerCards);
				$result = "{莊:{$bankerCards},<br/> 閑:{$playerCards}}";
				break;
			case 2:	//'龙虎',
				$result = "{龍:{$info->dragonCard}, 虎:{$info->tigerCard}}";
				break;
			case 3:	//'骰宝',
				$result = json_encode($info->allDices);
				break;
			case 4:	//'轮盘',
				$result = $info->number;
				break;
			case 5:	//'水果机',
			
				break;
		}

		//bets
		$bets = '';
		$num = '';
		foreach ($info->betMap as $bet) {
			$num = implode(', ', ($bet->betNumber ?? []));
			$type = self::parse_bet($bet->betType);
			$bets .= "<span class=small>{$type}{$num}: \${$bet->betMoney}</span><br/>";
		}

		return "{$bets}<span class=small>{$result}</span>";
	}
}