<?php

namespace Game\Report;

class Salon extends \Game\Salon
{
	function __construct()
	{
		parent::__construct();
	}

	public function set_report($sdate, $edate, $account=null)
	{
		if($account){
			$result = $this->init($account)->_get_report($sdate, $edate, $account);
		}else{
			$result = $this->get_report($sdate, $edate);
		}
		
		if($result['data'] ?? 0){
			
			$report = $result['data'];
			$insert = [];
			
			$tool = \App::make('Game\Account')->init($this->_lib);
			foreach($report as $val){
				$info = json_encode($val, JSON_UNESCAPED_UNICODE);
				$account = $val['Username'];
				$member_id = $tool->get($account)->member_id ?? 0;
				
				$insert[] = [
					'report_uid' => $val['BetID'] . ($val['BetAmount'] == 0? 's': ''),//注單編號(唯一) 48彩派彩單號重複問題
					'bet_date' => strtotime($val['BetTime']),//投注時間
					'set_date' => strtotime($val['PayoutTime']),//結帳日期
					'bet_amount' => $val['BetAmount'],//投注金額
					'set_amount' => $val['Rolling'],//有效金額
					'win_amount' => $val['ResultAmount'],//輸贏金額
					'type_id' => ($val['ResultAmount'] > 0)? 1: (($val['ResultAmount'] < 0)? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_salon', $insert);
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
			
			// get current branch login accounts
			$account = $this->get_active_accounts($sdate, $edate);
			if(!$account){
				$account = \App::make('Game\Account')->init($this->_lib)->get_recent($val->id);
			}
			$data = @$this->_get_report($sdate, $edate, $account);
			
			$result = array_merge($result, $data['data'] ?? []);
		}

		return ['code' => 0, 'data' => $result];
	}

	// 遊戲帳號
	public function _get_report($sdate, $edate, $account)
	{
		$arr = explode(',', $account);
		
		$result = [];
		
		foreach($arr as $account){
			$data = @$this->__get_report($sdate, $edate, $account);
			$result = array_merge($result, $data['data']);
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 處理分頁
	public function __get_report($sdate, $edate, $account)
	{
		$result = [];
		
		while(1){// 取得下一頁
			$data = $this->___get_report($sdate, $edate, $account, ($data['data']['Offset'] ?? 0));
			$result = array_merge($result, $data['data']['UserBetItemList']['UserBetItem'] ?? []);
			
			if(($data['data']['More'] ?? 'false') == 'false'){
				break;
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 查詢特定會員投注紀錄
	public function ___get_report($sdate, $edate, $account = '', $offset = 0)
	{
		$time = date('YmdHis');
		$key = $this->api['_secret_key'];
		
		$post = [
			'method' => 'GetUserBetItemDV',
			'Key' => $key,
			'Time' => $time,
			'Username' => $account,
			'FromTime' => date('Y-m-d H:i:s', $sdate),//起始時間
			'ToTime' => date('Y-m-d H:i:s', $edate),//結束時間
			'Offset' => $offset,
		];
		
		$qs = http_build_query($post);
		$q = urlencode($this->_des_encrypt($qs, $this->_des_key));
		$s = md5($qs . $this->_md5_key . $time . $key);
		
		$url = $this->api['_url'] . '?q=' . $q . '&s=' . $s;
		$result = \App::make('Lib\Curl')->curl($url, '', false);

		//xml to json
		$result = json_encode(simplexml_load_string($result));
		$result = json_decode($result, true);
		
		if(($result['ErrorMsgId'] ?? null) === '0'){
			return ['code' => 0, 'data' => $this->adjust($result)];
		}else{
			return ['code' => 7, 'data' => ''];
		}
	}

	public function get_active_accounts($sdate, $edate)
	{
		$time = date('YmdHis');
		$key = $this->api['_secret_key'];
		
		$post = [
			'method' => 'GetAllBetDetailsForTimeIntervalDV',
			'Key' => $key,
			'Time' => $time,
			'Checkkey' => 1,
			'FromTime' => date('Y-m-d H:i:s', $sdate),//起始時間
			'ToTime' => date('Y-m-d H:i:s', $edate),//結束時間
		];
		
		$qs = http_build_query($post);
		$q = urlencode($this->_des_encrypt($qs, $this->_des_key));
		$s = md5($qs . $this->_md5_key . $time . $key);
		
		$url = $this->api['_url'] . '?q=' . $q . '&s=' . $s;
		$result = \App::make('Lib\Curl')->curl($url, '', false);
		//xml to json
		$result = json_encode(simplexml_load_string($result));
		$result = json_decode($result, true);
		
		//pluck username column
		$arr = [];

		// 單筆格式僅一層
		$result = $result['BetDetailList']['BetDetail'] ?? [];
		if(isset($result['Username'])){
			$username = $result['Username'];
			$arr[$username] = $username;
		}else{
			foreach($result ?? [] as $v){
				$username = $v['Username'];
				$arr[$username] = $username;
			}
		}
		
		return (empty($arr))? false : implode(',', $arr);
	}

	public function adjust($arr)
	{
		// 單筆格式僅一層
		if($arr['ItemCount'] == 1){
			$arr['UserBetItemList']['UserBetItem'] = [$arr['UserBetItemList']['UserBetItem']] ?? [];
		}
		
		// 增加帳號欄位
		foreach($arr['UserBetItemList']['UserBetItem'] ?? [] as $key=>$val){
			$arr['UserBetItemList']['UserBetItem'][$key]['Username'] = $arr['Username'];
		}
		return $arr;
	}

	//遊戲說明--------------------------------------------------------------------------------------------------------//
	public static function get_game($arr)
	{
		$game = $arr['GameType'] ?? '';
		$result = $arr['HostName'] ?? '';
		
		if($game == 'slot' || $game == 'minigame'){
			$egame = array(
				'EG-SLOT-S001' => '大鬧天宮',
				'EG-SLOT-S002' => '嫦娥奔月',
				'EG-SLOT-S003' => '黃飛鴻',
				'EG-SLOT-012' => '阿茲特克',
				'EG-SLOT-014' => '水晶海',
				'EG-SLOT-020' => '對方快車',
				'EG-SLOT-051' => '發大財',
				'EG-SLOT-053' => '旺財',
				'EG-SLOT-A001' => '過大年',
				'EG-SLOT-A002' => '三星報囍',
				'EG-SLOT-A005' => '夢幻女神',
				'EG-SLOT-A012' => '趣怪喪屍',
				'EG-MINI-B001' => '發發發',
			);
			$result .= '-' . ($egame[$arr['Detail'] ?? ''] ?? '');
		}else{
			$result .= '<br>局號' . $arr['Round'];
		}
		
		return $result;
	}

	public static function get_game_result_string($arr)
	{
		$result = '';
		$game = $arr['GameType'] ?? '';
		
		switch($game){
			case 'bac':
				$result .= self::get_bac($arr);
				break;
			case 'dtx':
				$result .= self::get_dtx($arr);
				break;
			case 'sicbo':
				$result .= self::get_sicbo($arr);
				break;
			case 'ftan':
				$result .= self::get_ftan($arr);
				break;
			case 'rot':
				$result .= self::get_rot($arr);
				break;
			case 'slot':
				break;
			case 'lottery':
				$result .= self::get_lottery($arr);
				break;
			case 'minigame':
				break;
		}
		return $result;
	}

	public static function get_bac($arr)
	{
		$result = '';
		$game = $arr['BetType'] ?? '';
		$type = array(
			0 => '和', 1 => '閑', 2 => '莊', 3 => '閑對', 4 => '莊對', 5 => '閑點單', 6 => '莊點單', 7 => '總點單', 8 => '閑點雙', 9 => '莊點雙',
			10 => '總點雙', 11 => '閑點小', 12 => '莊點小', 13 => '總點小', 14 => '閑點大', 15 => '莊點大', 16 => '總點大', 17 => '閑牌小', 18 => '莊牌小', 19 => '總牌小',
			20 => '閑牌大', 21 => '莊牌大', 22 => '總牌大', 25 => '超级六和', 26 => '超级六閑赢', 27 => '超级六莊赢', 28 => '超级六閑對', 29 => '超级六莊對',
			30 => '超级六', 31 => '超级百家樂和', 32 => '超级百家樂閑赢', 33 => '超级百家樂莊赢', 34 => '超级百家樂閑對', 35 => '超级百家樂莊對', 36 => '閑例牌', 37 => '莊例牌', 38 => '超级百家樂閑例牌', 39 => '超级百家樂莊例牌',
			40 => '超级六閑例牌', 41 => '超级六莊例牌',
		);
		
		$result .= $type[$game] ?? '';
		$result .= '<br>';
		$tmp = array();
		$tmp[] = self::get_card($arr['GameResult']['BaccaratResult']['PlayerCard1'] ?? '');
		$tmp[] = self::get_card($arr['GameResult']['BaccaratResult']['PlayerCard2'] ?? '');
		$tmp[] = self::get_card($arr['GameResult']['BaccaratResult']['PlayerCard3'] ?? '');
		$result .= '{閑:' . trim(implode(',', $tmp), ',') . '}, ';
		
		$tmp = array();
		$tmp[] = self::get_card($arr['GameResult']['BaccaratResult']['BankerCard1'] ?? '');
		$tmp[] = self::get_card($arr['GameResult']['BaccaratResult']['BankerCard2'] ?? '');
		$tmp[] = self::get_card($arr['GameResult']['BaccaratResult']['BankerCard3'] ?? '');
		$result .= '{莊:' . trim(implode(',', $tmp), ',') . '}';
		
		return $result;
	}

	public static function get_dtx($arr)
	{
		$result = '';
		$game = $arr['BetType'] ?? '';
		$type = array(
			0 => '和', 1 => '龍', 2 => '虎',
		);
		
		$result .= $type[$game] ?? '';
		$result = '<br>{龍:' . self::get_card($arr['GameResult']['DragonTigerResult']['DragonCard'] ?? '') . '}, {虎:' . self::get_card($arr['GameResult']['DragonTigerResult']['TigerCard'] ?? '') . '}';
		
		return $result;
	}

	public static function get_sicbo($arr)
	{
		$result = '';
		$game = $arr['BetType'] ?? '';
		$type = array(
			0 => '小', 1 => '大', 2 => '單', 3 => '雙', 4 => '三軍1', 5 => '三軍2', 6 => '三軍3', 7 => '三軍4', 8 => '三軍5', 9 => '三軍6',
			10 => '圍一', 11 => '圍二', 12 => '圍三', 13 => '圍四', 14 => '圍五', 15 => '圍六', 16 => '全圍', 17 => '4點', 18 => '5點', 19 => '6點',
			20 => '7點', 21 => '8點', 22 => '9點', 23 => '10點', 24 => '11點', 25 => '12點', 26 => '13點', 27 => '14點', 28 => '15點', 29 => '16點',
			30 => '17點', 31 => '長牌12', 32 => '長牌13', 33 => '長牌14', 34 => '長牌15', 35 => '長牌16', 36 => '長牌23', 37 => '長牌24', 38 => '長牌25', 39 => '長牌26',
			40 => '長牌34', 41 => '長牌35', 42 => '長牌36', 43 => '長牌45', 44 => '長牌46', 45 => '長牌56', 46 => '短牌1', 47 => '短牌2', 48 => '短牌3', 49 => '短牌4',
			50 => '短牌5', 51 => '短牌6', 52 => '三全單', 53 => '兩單一雙', 54 => '兩雙一單', 55 => '三全雙', 56 => '1234', 57 => '2345', 58 => '2356', 59 => '3456',
			60 => '112', 61 => '113', 62 => '114', 63 => '115', 64 => '116', 65 => '221', 66 => '223', 67 => '224', 68 => '225', 69 => '226',
			70 => '331', 71 => '332', 72 => '334', 73 => '335', 74 => '336', 75 => '441', 76 => '442', 77 => '443', 78 => '445', 79 => '446',
			80 => '551', 81 => '552', 82 => '553', 83 => '554', 84 => '556', 85 => '661', 86 => '662', 87 => '663', 88 => '664', 89 => '665',
			90 => '126', 91 => '135', 92 => '234', 93 => '256', 94 => '346', 95 => '123', 96 => '136', 97 => '145', 98 => '235', 99 => '356',
			100 => '124', 101 => '146', 102 => '236', 103 => '245', 104 => '456', 105 => '125', 106 => '134', 107 => '156', 108 => '246', 109 => '345',
		);
		
		$result .= $type[$game] ?? '';
		$tmp = array();
		$tmp[] = ($arr['GameResult']['SicboResult']['Dice1'] ?? '');
		$tmp[] = ($arr['GameResult']['SicboResult']['Dice2'] ?? '');
		$tmp[] = ($arr['GameResult']['SicboResult']['Dice3'] ?? '');
		$result .= '<br>結果: ' . implode(', ', $tmp);
		
		return $result;
	}

	public static function get_ftan($arr)
	{
		$result = '';
		$game = $arr['BetType'] ?? '';
		$type = array(
			0 => '單', 1 => '雙', 2 => '1正', 3 => '2正', 4 => '3正', 5 => '4正', 6 => '1番', 7 => '2番', 8 => '3番', 9 => '4番',
			10 => '1念2', 11 => '1念3', 12 => '1念4', 13 => '2念1', 14 => '2念3', 15 => '2念4', 16 => '3念1', 17 => '3念2', 18 => '3念4', 19 => '4念1',
			20 => '4念2', 21 => '4念3', 22 => '12角', 23 => '14角', 24 => '23角', 25 => '34角', 26 => '23一通', 27 => '24一通', 28 => '34一通', 29 => '13二通',
			30 => '14二通', 31 => '34二通', 32 => '12三通', 33 => '14三通', 34 => '24三通', 35 => '12四通', 36 => '13四通', 37 => '23四通', 38 => '123中', 39 => '124中',
			40 => '134中', 41 => '234中',
		);
		
		$result .= $type[$game] ?? '';
		$result .= '<br>結果: ' . ($arr['GameResult']['FantanResult']['Point'] ?? '') . '番';
		
		return $result;
	}

	public static function get_rot($arr)
	{
		$result = '';
		$game = $arr['BetType'] ?? '';
		$type = array(
			0 => '0', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9',
			10 => '10', 11 => '11', 12 => '12', 13 => '13', 14 => '14', 15 => '15', 16 => '16', 17 => '17', 18 => '18', 19 => '19',
			20 => '20', 21 => '21', 22 => '22', 23 => '23', 24 => '24', 25 => '25', 26 => '26', 27 => '27', 28 => '28', 29 => '29',
			30 => '30', 31 => '31', 32 => '32', 33 => '33', 34 => '34', 35 => '35', 36 => '36', 37 => '0,1', 38 => '0,2', 39 => '0,3',
			40 => '1,2', 41 => '1,4', 42 => '2,3', 43 => '2,5', 44 => '3,6', 45 => '4,5', 46 => '4,7', 47 => '5,6', 48 => '5,8', 49 => '6,9',
			50 => '7,8', 51 => '7,10', 52 => '8,9', 53 => '8,11', 54 => '9,12', 55 => '10,11', 56 => '10,13', 57 => '11,12', 58 => '11,14', 59 => '12,15',
			60 => '13,14', 61 => '13,16', 62 => '14,15', 63 => '14,17', 64 => '15,18', 65 => '16,17', 66 => '16,19', 67 => '17,18', 68 => '17,20', 69 => '18,21',
			70 => '19,20', 71 => '19,22', 72 => '20,21', 73 => '20,23', 74 => '21,24', 75 => '22,23', 76 => '22,25', 77 => '23,24', 78 => '23,26', 79 => '24,27',
			80 => '25,26', 81 => '25,28', 82 => '26,27', 83 => '26,29', 84 => '27,30', 85 => '28.29', 86 => '28,31', 87 => '29,30', 88 => '29,32', 89 => '30,33',
			90 => '31,32', 91 => '31,34', 92 => '32,33', 93 => '32,35', 94 => '33,36', 95 => '34,35', 96 => '35,36', 97 => '0,1,2', 98 => '0,2,3', 99 => '1,2,3',
			100 => '4,5,6', 101 => '7,8,9', 102 => '10,11,121', 103 => '13,14,15', 104 => '16,17,18', 105 => '19,20,21', 106 => '22,23,24', 107 => '25,26,27', 108 => '28,29,30', 109 => '31,32,33',
			110 => '34,35,36', 111 => '1,2,4,5', 112 => '2,3,5,6', 113 => '4,5,7,8', 114 => '5,6,8,9', 115 => '7,8,10,11', 116 => '8,9,11,12', 117 => '10,11,13,14', 118 => '11,12,14,15', 119 => '13,14,16,17',
			120 => '14,15,17,18', 121 => '16,17,19,20', 122 => '17,18,20,21', 123 => '19,20,22,23', 124 => '20,21,23,24', 125 => '22,23,25,26', 126 => '23,24,26,27', 127 => '25,26,28,29', 128 => '26,27,29,30', 129 => '28,29,31,32',
			130 => '29,30,32,33', 131 => '31,32,34,35', 132 => '32,33,35,36', 133 => '1,2,3,4,5,6', 134 => '4,5,6,7,8,9', 135 => '7,8,9,10,11,12', 136 => '10,11,12,13,14,15', 137 => '13,14,15,16,17,18', 138 => '16,17,18,19,20,21', 139 => '19,20,21,22,23,24',
			140 => '22,23,24,25,26,27', 141 => '25,26,27,28,29,30', 142 => '28,29,30,31,32,33', 143 => '31,32,33,34,35,36', 144 => '第一列 (1~12)', 145 => '第二列 (13~24)', 146 => '第三列 (25~36)', 147 => '第一行 (1~34)', 148 => '第二行 (2~35)', 149 => '第三行 (3~36)',
			150 => '1~18 (小)', 151 => '19~36 (大)', 152 => '單', 153 => '雙', 154 => '紅', 155 => '黑', 156 => '0,1,2,3',
		);
		
		$result .= $type[$game] ?? '';
		$result .= '<br>結果: ' . ($arr['GameResult']['RouletteResult']['Point'] ?? '');
		
		return $result;
	}

	public static function get_lottery($arr)
	{
		$result = '';
		$game = $arr['BetType'] ?? '';
		$type = array(
			0 => '單式 (6個號碼)', 1 => '複式 (多於6個號碼)', 2 => '膽拖', 3 => '特別號碼1', 4 => '特別號碼2', 5 => '特別號碼3', 6 => '特別號碼4', 7 => '特別號碼5', 8 => '特別號碼6', 9 => '特別號碼7',
			10 => '特別號碼8', 11 => '特別號碼9', 12 => '特別號碼10', 13 => '特別號碼11', 14 => '特別號碼12', 15 => '特別號碼13', 16 => '特別號碼14', 17 => '特別號碼15', 18 => '特別號碼16', 19 => '特別號碼17',
			20 => '特別號碼18', 21 => '特別號碼19', 22 => '特別號碼20', 23 => '特別號碼21', 24 => '特別號碼22', 25 => '特別號碼23', 26 => '特別號碼24', 27 => '特別號碼25', 28 => '特別號碼26', 29 => '特別號碼27',
			30 => '特別號碼28', 31 => '特別號碼29', 32 => '特別號碼30', 33 => '特別號碼31', 34 => '特別號碼32', 35 => '特別號碼33', 36 => '特別號碼34', 37 => '特別號碼35', 38 => '特別號碼36', 39 => '特別號碼37',
			40 => '特別號碼38', 41 => '特別號碼39', 42 => '特別號碼40', 43 => '特別號碼41', 44 => '特別號碼42', 45 => '特別號碼43', 46 => '特別號碼44', 47 => '特別號碼45', 48 => '特別號碼46', 49 => '特別號碼47',
			50 => '特別號碼48', 51 => '特碼單', 52 => '特碼雙', 53 => '特碼大', 54 => '特碼小', 55 => '特碼紅', 56 => '特碼藍', 57 => '特碼綠',
		);
		
		$tmp = explode(', ', $arr['Detail'] ?? '');
		
		$result .= $type[$game] ?? '';
		$result .= ($tmp[1] ?? '') . '期 ' . ($tmp[2] ?? '');
		$result .= '<br>結果: ' . ($arr['GameResult']['LotteryResult']['LotteryResult'] ?? '');
		
		return $result;
	}

	public static function get_card($arr)
	{
		$type = array('', '黑桃', '紅桃', '梅花', '方塊');
		$alias = array(0 => '', 1 => 'A', 11 => 'J', 12 => 'Q', 13 => 'K');
		
		return $type[$arr['Suit'] ?? 0] . ($alias[$arr['Rank'] ?? 0] ?? ($arr['Rank'] ?? ''));
	}

	public static function create_bet_content_column($info, $tpl){
		$html3 = $tpl->block('salon_bet_content')->assign([
			'game_result' => self::get_game_result_string(json_decode(json_encode($info), true)),
		])->render(false);

		return $html3;
	}
	//--------------------------------------------------------------------------------------------------------遊戲說明//
}