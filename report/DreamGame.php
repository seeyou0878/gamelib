<?php

namespace Game\Report;

class DreamGame extends \Game\DreamGame
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
				$account = $val['userName'];
				$member_id = $tool->get($account)->member_id ?? 0;

				$insert[] = [
					'report_uid' => $val['id'],//單號
					'bet_date' => strtotime($val['betTime']),//投注時間
					'set_date' => strtotime($val['calTime']),//結帳時間
					'bet_amount' => $val['betPoints'],//投注金額
					'set_amount' => $val['availableBet'],//有效投注
					'win_amount' => $val['winOrLoss'] - $val['betPoints'],//輸贏結果
					'type_id' => ($val['winOrLoss'] > $val['betPoints'])? 1: (($val['winOrLoss'] < $val['betPoints'])? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_dreamgame', $insert);
		}
		// 標記注單
		$this->mark_report();
	}

	// 取得各站報表, 忽略錯誤, 參數虛設
	public function get_report($sdate = '', $edate = '')
	{
		$branch = \DB::table('t_branch')->where('id', '!=', 1)->get();

		$result = [];

		foreach($branch as $val){

			// init with branch_id
			$this->init($val->id);

			$data = @$this->_get_report();

			$result = array_merge($result, $data['data']['list'] ?? []);
		}

		return ['code' => 0, 'data' => $result];
	}

	// 查詢投注紀錄 10秒只能請求1次, max 1000
	public function _get_report()
	{
		$acc = $this->api['_agent'];
		$key = md5($acc . $this->api['_api_key']);

		$post = [
			'token' => $key
		];

		$url = $this->api['_url'] . "/game/getReport/{$acc}";
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, json_encode($post));
		$result = json_decode($result, true);
	
		if(($result['codeId'] ?? false) === 0){
			return ['code' => 0, 'data' => $this->adjust($result)];
		}else{
			return ['code' => 7, 'data' => ''];
		}
	}

	public function adjust($arr)
	{
		foreach($arr['list'] ?? [] as $key=>$val){
			$this->mark[] = $val['id'];
		}
		return $arr;
	}

	// 標記注單
	public function mark_report()
	{
		$branch = \DB::table('t_branch')->where('id', '!=', 1)->get();

		foreach($branch as $val){

			// init with branch_id
			$this->init($val->id);

			$data = @$this->_mark_report($this->mark);
		}

		return ['code' => 0, 'data' => true];
	}

	// 標記注單
	public function _mark_report($arr)
	{
		$acc = $this->api['_agent'];
		$key = md5($acc . $this->api['_api_key']);

		$post = [
			'token' => $key,
			'list' => $arr,
		];

		$url = $this->api['_url'] . "/game/markReport/{$acc}";
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, json_encode($post));
		$result = json_decode($result, true);
		
		if(($result['codeId'] ?? false) === 0){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'data' => ''];
		}
	}

	//遊戲說明--------------------------------------------------------------------------------------------------------//
	public static function get_game($info)
	{
		$type = $info['gameType'];
		$id = $info['gameId'];

		if($type = 1)
			$arr = [
				1 => '百家樂',
				3 => '龍虎',
				4 => '輪盤',
				5 => '骰寶',
				7 => '牛牛',
				8 => '競咪百家',
				9 => '賭場撲克',
			];
		else
			$arr = [
				1 => '會員發紅包',
				2 => '會員搶紅包',
				3 => '小費',
				4 => '公司發紅包',
			];

		return $arr[$id] ?? '';
	}

	public static function get_card($card)
	{
		$type = array('', '黑桃', '紅桃', '梅花', '方塊');
		$alias = array(1 => 'A', 11 => 'J', 12 => 'Q', 0 => 'K');
		$result = $type[ceil($card/13)] . ($alias[$card % 13] ?? $card % 13);

		return ($card > 0 && $card < 53)? $result: '';
	}

	public static function create_bet_content_column($info){
		//Game Result
		$result = '';
		if($info->gameType === 1){
			$func_parse = function($str_cards){
				$arr_cards = explode('-', $str_cards);
				$txt = '';
				foreach($arr_cards as $c){
					$txt .= self::get_card($c);
					$txt .= ' ';
				}
				return "[{$txt}]";
			};
			
			$detail = json_decode($info->result);
			$poker = $detail->poker ?? '';
			switch ($info->gameId) {
				case 1: //百家樂
				case 8: //競咪百家樂
					$bankerCards = $func_parse($poker->banker);
					$playerCards = $func_parse($poker->player);
					$result = "{莊:{$bankerCards},<br> 閑:{$playerCards}}";
					break;
				case 3: //龍虎
					$result = "{龍:{$poker->dragon}, 虎:{$poker->tiger}}";
					break;
				case 4: //輪盤
					$result = "{{$detail->result}}";
					break;
				case 5: //骰寶
					$dices = str_split($detail->result, 1);
					$dices = implode(', ', $dices);
					$result = "{{$dices}}";
					break;
				case 7: //牛牛
					$fcard = $poker->firstcard;
					$banker = $poker->banker;
					$player1 = $poker->player1;
					$player2 = $poker->player2;
					$player3 = $poker->player3;

					$result .= '首牌:  ' . $func_parse($fcard)   . '<br>';
					$result .= '莊家:  ' . $func_parse($banker)  . '<br>';
					$result .= '閑家1: ' . $func_parse($player1) . '<br>';
					$result .= '閑家2: ' . $func_parse($player2) . '<br>';
					$result .= '閑家3: ' . $func_parse($player3) . '<br>';
					break;
				case 9: //賭場撲克
					$arr1 = ['', '莊贏', '閑贏', '和局'];
					$arr2 = ['', '高牌', '一對', '兩對', '三條', '順子', '同花', '葫蘆', '四條', '同花順', '皇家同花順'];
					$tmp = explode('|', $detail->result);
					$res = explode(',', $tmp[0]);
					
					$result .= '結果: ' . $arr1[$res[1]] . '<br>';
					$result .= '莊家: ' . $arr2[$res[2]] . $func_parse($poker->banker) . '<br>';
					$result .= '閑家: ' . $arr2[$res[3]] . $func_parse($poker->player) . '<br>';
					$result .= '公牌: <br>' . $func_parse($poker->community) . '<br>';
					break;
			}
		}else{
			switch ($info->gameId) {
				case 1: //'會員發紅包',
					return '會員發紅包';
					break;
				case 2: //'會員搶紅包',
					return '會員搶紅包';
					break;
				case 3: //'小費',
					return '小費';
					break;
				case 4: //'公司發紅包',
					return '公司發紅包';
					break;
			}
		}

		//Player Bets
		$bets = '';
		$arr_bet = json_decode($info->betDetail, true);
		foreach ($arr_bet as $key => $val) {
			$type = self::parse_bet($key);
			if(is_array($val)){
				$val = array_map(function($k, $v){ return '<' . $k . ',' . $v . '>';}, array_keys($val), $val);
				$val = '<br>' . implode('<br>', $val);
			}
			
			if($type)
				$bets .= "<span class=small>{$type}: {$val}</span><br/>";
		}

		return "{$bets}<span class=small>結果: {$result}</span>";
	}
	
	public static function parse_bet($id=-1)
	{
		$type = [
			'banker' => '莊下注金額',
			'banker6' => '免傭莊下注金額',
			'player' => '閑下注金額',
			'tie' => '和下注金額',
			'pPair' => '莊對下注金額',
			'bPair' => '閑對下注金額',
			'big' => '大下注金額',
			'small' => '小下注金額',
			'dragon' => '龍下注金額',
			'tiger' => '虎下注金額',
			'dragonRed' => '龍紅下注金額',
			'dragonBlack' => '龍黑下注金額',
			'tigerRed' => '虎紅下注金額',
			'tigerBlack' => '虎黑下注金額',
			'dragonOdd' => '龍單下注金額',
			'tigerOdd' => '虎單下注金額',
			'dragonEven' => '龍雙下注金額',
			'tigerEven' => '虎雙下注金額',
			'direct' => '直注<號碼,下注金額>',
			'separate' => '分注<號碼,下注金額>',
			'street' => '街注<號碼,下注金額>',
			'angle' => '角注<號碼,下注金額>',
			'line' => '線注<號碼,下注金額>',
			'three' => '三數注<號碼,下注金額>',
			'four' => '四個號碼下注金額',
			'firstRow' => '行注一下注金額',
			'sndRow' => '行注二下注金額',
			'thrRow' => '行注三下注金額',
			'firstCol' => '打注一下注金額',
			'sndCol' => '打注二下注金額',
			'thrCol' => '打注三下注金額',
			'red' => '紅色下注金額',
			'black' => '黑色下注金額',
			'odd' => '單下注金額',
			'even' => '雙下注金額',
			'low' => '小下注金額',
			'high' => '大下注金額',
			'allDices' => '全圍下注金額',
			'threeForces' => '三軍<號碼,下注金額>',
			'nineWayGards' => '段牌<號碼,下注金額>',
			'pairs' => '長牌<號碼,下注金額>',
			'surroundDices' => '圍骰<號碼,下注金額>',
			'points' => '點數<號碼,下注金額>',
			'player1Double' => '閑一翻倍下注金額',
			'player2Double' => '閑二翻倍下注金額',
			'player3Double' => '閑三翻倍下注金額',
			'player1Equal' => '閑一平倍下注金額',
			'player2Equal' => '閑二平倍下注金額',
			'player3Equal' => '閑三平倍下注金額',
			'player1Many' => '閑一多倍下注金額',
			'player2Many' => '閑二多倍下注金額',
			'player3Many' => '閑三多倍下注金額',
			// 'id' => '紅包Id',
			// 'userName' => '紅包對應的會員的登入昵稱',
			// 'type' => '紅包類型：1：定數隨機分金額，2：定金額平均分',
			// 'points' => '紅包金額',
			// 'count' => '紅包個數',
			// 'currencyId' => '紅包幣種ID',
			// 'currencyRate' => '發紅包時匯率["相對CNY"]',
			// 'message' => '紅包附帶信息',
		];

		return $type[$id] ?? '';
	}
}

