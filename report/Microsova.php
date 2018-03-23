<?php

namespace Game\Report;

class Microsova extends \Game\Microsova
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
				$account = $val['PlayerId'];
				$member_id = $tool->get($account)->member_id ?? 0;
				
				$insert[] = [
					'report_uid' => $val['SeqNo'],//單號
					'bet_date' => strtotime($val['LogTime']),//投注時間
					'set_date' => strtotime($val['LogTime']),//結帳時間
					'bet_amount' => ($val['TotalBet'] / $val['ExchangeRate']),//投注金額
					'set_amount' => ($val['TotalBet'] / $val['ExchangeRate']),//有效投注
					'win_amount' => ($val['TotalWin'] - $val['TotalBet']) / $val['ExchangeRate'],//輸贏結果
					'type_id' => ($val['TotalWin'] > $val['TotalBet'])? 1: (($val['TotalWin'] < $val['TotalBet'])? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_microsova', $insert);
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
			$result = array_merge($result, $data['data']['ResultData']['diffgram']['NewDataSet']['QueryGameDetailByUser'] ?? []);
			
			if(($data['data']['MaxPage'] ?? 0) <= ($data['data']['CurrentPage'] ?? 0)){
				break;
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 單次取得報表
	public function __get_report($sdate, $edate, $page = 1)
	{
		$post = [
			'VendorKey' => $this->api['_vendor_key'],
			'AccountId' => '',//會員帳號
			'BeginDate' => date('Y-m-d H:i:s', $sdate),//起始時間
			'EndDate' => date('Y-m-d H:i:s', $edate),//結束時間
			'PageIndex' => $page,//頁碼
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/QueryGameDetailByUser', $post);
		
		//xml to json
		$result = json_encode(simplexml_load_string($this->_strip_trash_tag($result) ?? ''));
		$result = json_decode($result, true);
		
		if(($result['State'] ?? null) === '1'){
			
			//單筆格式僅一層
			if(isset($result['ResultData']['diffgram']['NewDataSet']['QueryGameDetailByUser']['@attributes'])){
				$result['ResultData']['diffgram']['NewDataSet']['QueryGameDetailByUser'] = array($result['ResultData']['diffgram']['NewDataSet']['QueryGameDetailByUser']);
			}
			
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
	public static function get_game_name($arr = [])
	{
		$game = $arr['GameTypeId'] ?? 0;
		$type = [
			1=>['ch'=>'幸運賽狗',         'en'=>'DGame'],
			3=>['ch'=>'動物王國',         'en'=>'ANGame'],
			4=>['ch'=>'幸運小丑馬戲團',   'en'=>'CGame'],
			5=>['ch'=>'幸運海盜',         'en'=>'LPGame'],
			6=>['ch'=>'木偶奇偶記',       'en'=>'PCGame'],
			7=>['ch'=>'冠軍的榮耀',       'en'=>'HLGame'],
			8=>['ch'=>'泰山',             'en'=>'TZGame'],
			9=>['ch'=>'埃及',             'en'=>'EGGame'],
			11=>['ch'=>'幸運小丑馬戲團2', 'en'=>'C2Game'],
			12=>['ch'=>'綠野仙蹤',        'en'=>'WWGame'],
			13=>['ch'=>'輪盤天堂',        'en'=>'RPGame'],
			14=>['ch'=>'深海獵場',        'en'=>'FMGame'],
			15=>['ch'=>'歡慶新年',        'en'=>'NYGame'],
			16=>['ch'=>'瘋狂上班族',      'en'=>'CWGame'],
			17=>['ch'=>'玩具戰爭',        'en'=>'TWGame'],
			18=>['ch'=>'西遊記',          'en'=>'PWGame'],
			19=>['ch'=>'太空探險',        'en'=>'SEGame'],
			20=>['ch'=>'夏威夷島',        'en'=>'HIGame'],
			21=>['ch'=>'仲秋夜',          'en'=>'AUGame'],
			22=>['ch'=>'鯊魚派對',        'en'=>'SPGame'],
			23=>['ch'=>'銀河金錢輪',      'en'=>'BWGame'],
			24=>['ch'=>'足球先鋒',        'en'=>'SSGame'],
			25=>['ch'=>'歡樂萬聖節',      'en'=>'HHGame'],
			26=>['ch'=>'功夫',            'en'=>'KFGame'],
			27=>['ch'=>'幸運百家樂',      'en'=>'BGame'],
			28=>['ch'=>'世界盃',          'en'=>'WCGame'],
			29=>['ch'=>'經典賽馬',        'en'=>'KDGame'],
			30=>['ch'=>'多人動物王國',    'en'=>'MultiANGame'],
			32=>['ch'=>'龍霸天下',        'en'=>'LDGame'],
			33=>['ch'=>'多人幸運骰寶',    'en'=>'MultiSGame'],
			35=>['ch'=>'多人急速賽車',    'en'=>'MultiORGame'],
			36=>['ch'=>'多人輪盤天堂',    'en'=>'MultiRPGame'],
			37=>['ch'=>'三國風雲',        'en'=>'TKGame'],
			38=>['ch'=>'多人捕魚機',      'en'=>'FLGame'],
			39=>['ch'=>'扶桑花',          'en'=>'IMGame'],
			40=>['ch'=>'多人賽狗',        'en'=>'MultiDGame'],
			41=>['ch'=>'多人鯊魚派對',    'en'=>'MultiSPGame'],
			42=>['ch'=>'多人魚蝦蟹',      'en'=>'MultiFCGame'],
		];
		
		return $game? ($type[$game]['ch'] ?? ''): $type;
	}

	/**
	 * 取得遊戲類型字串
	 * @param $game_type 遊戲類型 int
	 * @return mixed
	 */
	public static function get_game_type_string($game_type){
		$arr['GameTypeId'] = $game_type;
		return (self::get_game_name($arr)) ? self::get_game_name($arr) : $game_type;
	}

	public static function get_game($info){
		return self::get_game_type_string($info['GameTypeId']);
	}
}