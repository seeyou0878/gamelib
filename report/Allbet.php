<?php

namespace Game\Report;

class Allbet extends \Game\Allbet
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
				$account = $val['client'];
				$member_id = $tool->get($account)->member_id ?? 0;
				
				$insert[] = [
					'report_uid' => $val['betNum'],//單號
					'bet_date' => strtotime($val['betTime']),//投注時間
					'set_date' => strtotime($val['gameRoundEndTime']),//結帳時間
					'bet_amount' => $val['betAmount'],//投注金額
					'set_amount' => $val['validAmount'],//有效投注
					'win_amount' => isset($val['winOrLoss'])? $val['winOrLoss']: 0,//輸贏結果
					'type_id' => ($val['winOrLoss'] > 0)? 1: (($val['winOrLoss'] < 0)? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_allbet', $insert);
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

			//try to use "betlog_pieceof_histories_in30days" api first
			$data = @$this->_get_report($sdate, $edate);
			$result = array_merge($result, $data['data'] ?? []);

			//if fail, use "client_betlog_query"
			if($data['code'] != 0){
				// get current branch login accounts
				$account = \App::make('Game\Account')->init($this->_lib)->get_recent($val->id);
				$data = @$this->_get_report($sdate, $edate, $account);
				$result = array_merge($result, $data['data'] ?? []);
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 遊戲帳號
	public function _get_report($sdate, $edate, $account = null)
	{
		if($account){
			$arr = explode(',', $account);
			
			$result = [];
			
			foreach($arr as $account){
				$data = @$this->__get_report($sdate, $edate, $account);
				$result = array_merge($result, $data['data']);
			}
			return ['code' => 0, 'data' => $result];
		}else{
			//30 day api
			$post = [
				'random' => $this->_random,
				'startTime' => date('Y-m-d H:i:s', $sdate),
				'endTime' => date('Y-m-d H:i:s', $edate),
				'agent' => $this->api['_agent'],
			];

			$encode_data = base64_encode($this->_des_encrypt(http_build_query($post), $this->api['_des_key']));
			$sign = base64_encode(md5($encode_data . $this->api['_md5_key'], true));

			$post = [
				'data' => $encode_data,
				'sign' => $sign,
				'propertyId' => $this->api['_property_id'],
			];
			
			$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/betlog_pieceof_histories_in30days', $post);
			$result = json_decode($result, true);
			if(($result['error_code'] ?? null) === 'OK'){
				return ['code' => 0, 'data' => $result['histories']];
			}else{ //錯誤回傳
				return ['code' => 7, 'text' => ''];
			}
		}
	}

	// 處理分頁
	public function __get_report($sdate, $edate, $account, $page = 1)
	{
		$result = [];
		
		while(1){// 取得下一頁
			$data = $this->___get_report($sdate, $edate, $account, $page++);
			$result = array_merge($result, $data['data']['page']['datas'] ?? []);
			
			if($page > (ceil(($data['data']['page']['count'] ?? 0) / 100))){
				break;
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 查詢特定會員投注紀錄 最大查詢區間為2週 不可超過當前時間
	public function ___get_report($sdate, $edate, $account, $page = 1)
	{
		$post = [
			'random' => $this->_random,
			'client' => $account,
			'startTime' => date('Y-m-d H:i:s', $sdate),//開始時間
			'endTime'   => date('Y-m-d H:i:s', $edate),//結束時間
			'pageIndex' => $page,//欲索引頁碼
			'pageSize'  => 100,//每頁筆數
		];
		
		$encode_data = base64_encode($this->_des_encrypt(http_build_query($post), $this->api['_des_key']));
		$sign = base64_encode(md5($encode_data . $this->api['_md5_key'], TRUE));

		$post = [
			'data' => $encode_data,
			'sign' => $sign,
			'propertyId' => $this->api['_property_id'],
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/client_betlog_query', $post);
		$result = json_decode($result, true);
		
		if(($result['error_code'] ?? null) === 'OK'){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}

	//遊戲說明--------------------------------------------------------------------------------------------------------//
	public static $game_types = array(
		101   =>  '普通百家樂',
		102   =>  'VIP百家樂',
		103   =>  '急速百家樂',
		104   =>  '競咪百家樂',
		201   =>  '骰寶',
		301   =>  '龍虎',
		401   =>  '輪盤'
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
		$game_type_string = self::get_game_type_string($info['gameType']);
		return $info['gameRoundId'] . '<br>' . $game_type_string;
	}

	public static $bet_types = array(
		1001=>'莊', 1002=>'閑', 1003=>'和', 1004=>'大', 1005=>'小', 1006=>'莊對', 1007=>'閑對',//百家樂
		2001=>'龍', 2002=>'虎', 2003=>'和',//龍虎
		3001=>'小', 3002=>'單', 3003=>'雙', 3004=>'大', 3005=>'圍一', 3006=>'圍二', 3007=>'圍三', 3008=>'圍四', 3009=>'圍五', 3010=>'圍六', 3011=>'全圍',//骰寶
		3012=>'對1', 3013=>'對2', 3014=>'對3', 3015=>'對4', 3016=>'對5', 3017=>'對6', 3018=>'和值:4', 3019=>'和值:5', 3020=>'和值:6', 3021=>'和值:7', 3022=>'和值:8',
		3023=>'和值:9', 3024=>'和值:10', 3025=>'和值:11', 3026=>'和值:12', 3027=>'和值:13', 3028=>'和值:14', 3029=>'和值:15', 3030=>'和值:16', 3031=>'和值:17', 3032=>'',
		3033=>'牌九式:12', 3034=>'牌九式:13', 3035=>'牌九式:14', 3036=>'牌九式:15', 3037=>'牌九式:16', 3038=>'牌九式:23', 3039=>'牌九式:24', 3040=>'牌九式:25', 3041=>'牌九式:26',
		3042=>'牌九式:34', 3043=>'牌九式:35', 3044=>'牌九式:36', 3045=>'牌九式:45', 3046=>'牌九式:46', 3047=>'牌九式:56',
		3048=>'單骰:1', 3049=>'單骰:2', 3050=>'單骰:3', 3051=>'單骰:4', 3052=>'單骰:5', 3053=>'單骰:6',
		4001=>'小', 4002=>'雙', 4003=>'紅', 4004=>'黑', 4005=>'單', 4006=>'大', //輪盤
		4007=>'第一打', 4008=>'第二打', 4009=>'第三打', 4010=>'第一列', 4011=>'第二列', 4012=>'第三列',
		4050=>'三數:(0/1/2)', 4051=>'三數:(0/2/3)', 4052=>'四數:(0/1/2/3)',
		4053=>'分注:(0/1)', 4054=>'分注:(0/2)', 4055=>'分注:(0/3)', 4056=>'分注:(1/2)', 4057=>'分注:(2/3)', 4058=>'分注:(4/5)', 4059=>'分注:(5/6)', 4060=>'分注:(7/8)',
		4061=>'分注:(8/9)', 4062=>'分注:(10/11)', 4063=>'分注:(11/12)', 4064=>'分注:(13/14)', 4065=>'分注:(14/15)', 4066=>'分注:(16/17)', 4067=>'分注:(17/18)', 4068=>'分注:(19/20)',
		4069=>'分注:(20/21)', 4070=>'分注:(22/23)', 4071=>'分注:(23/24)', 4072=>'分注:(25/26)', 4073=>'分注:(26/27)', 4074=>'分注:(28/29)', 4075=>'分注:(29/30)', 4076=>'分注:(31/32)',
		4077=>'分注:(32/33)', 4078=>'分注:(34/35)', 4079=>'分注:(35/36)', 4080=>'分注:(1/4)', 4081=>'分注:(4/7)', 4082=>'分注:(7/10)', 4083=>'分注:(10/13)', 4084=>'分注:(13/16)',
		4085=>'分注:(16/19)', 4086=>'分注:(19/22)', 4087=>'分注:(22/25)', 4088=>'分注:(25/28)', 4089=>'分注:(28/31)', 4090=>'分注:(31/34)', 4091=>'分注:(2/5)', 4092=>'分注:(5/8)',
		4093=>'分注:(8/11)', 4094=>'分注:(11/14)', 4095=>'分注:(14/17)', 4096=>'分注:(17/20)', 4097=>'分注:(20/23)', 4098=>'分注:(23/26)', 4099=>'分注:(26/29)', 4100=>'分注:(29/32)',
		4101=>'分注:(32/35)', 4102=>'分注:(3/6)', 4103=>'分注:(6/9)', 4104=>'分注:(9/12)', 4105=>'分注:(12/15)', 4106=>'分注:(15/18)', 4107=>'分注:(18/21)', 4108=>'分注:(21/24)',
		4109=>'分注:(24/27)', 4110=>'分注:(27/30)', 4111=>'分注:(30/33)', 4112=>'分注:(33/36)',
		4113=>'角注:(1/5)', 4114=>'角注:(2/6)', 4115=>'角注:(4/8)', 4116=>'角注:(5/9)', 4117=>'角注:(7/11)', 4118=>'角注:(8/12)', 4119=>'角注:(10/14)', 4120=>'角注:(11/15)', 4121=>'角注:(13/17)',
		4122=>'角注:(14/18)', 4123=>'角注:(16/20)', 4124=>'角注:(17/21)', 4125=>'角注:(18/23)', 4126=>'角注:(20/24)', 4127=>'角注:(22/26)', 4128=>'角注:(23/27)', 4129=>'角注:(25/29)',
		4130=>'角注:(26/30)', 4131=>'角注:(28/32)', 4132=>'角注:(29/33)', 4133=>'角注:(31/35)', 4134=>'角注:(32/36)', 4135=>'街注:(1~3)', 4136=>'街注:(4~6)', 4137=>'街注:(7~9)', 4138=>'街注:(9~12)',
		4139=>'街注:(13~15)', 4140=>'街注:(16~18)', 4141=>'街注:(19~21)', 4142=>'街注:(22~24)', 4143=>'街注:(25~27)', 4144=>'街注:(28~30)', 4145=>'街注:(31~33)', 4146=>'街注:(34~36)', 4147=>'線注:(1~6)',
		4148=>'線注:(4~9)', 4149=>'線注:(7~12)', 4150=>'線注:(10~15)', 4151=>'線注:(13~18)', 4152=>'線注:(16~21)', 4153=>'線注:(19~24)', 4154=>'線注:(22~27)', 4155=>'線注:(28~33)',
		4156=>'線注:(31~36)', 4157=>'線注:(25~30)'
	);

	/**
	 * 取得投注類型字串，沒比對到者直接回傳$bet_type
	 * @param $bet_type 投注類型 int
	 * @return mixed|string
	 */
	public static function get_bet_type_string($bet_type){
		$str = $bet_type;
		if(isset(self::$bet_types[$bet_type])){
			$str = self::$bet_types[$bet_type];
		}elseif($bet_type >= 4013 && $bet_type <= 4049){ //4013~4049
			$num = $bet_type - 4013;
			$str = '直接注:('.$num.')';
		}
		return $str;
	}

	/**
	 * 取得開牌結果字串
	 * @param $game_type
	 * @param $game_result
	 * @return mixed|string
	 */
	public static function get_game_result_string($game_type, $game_result){
		$str = $game_result;
		$game_result = trim($game_result);
		switch ($game_type){
			case 101:
			case 102:
			case 103:
			case 104: //百家樂
			$str = self::get_baccarat_string($game_result);
				break;
			case 201://骰寶
				$str ='{點數:'.mb_ereg_replace('{|}', '', $game_result).'}';
				break;
			case 301://龍虎
				$str = self::get_drangon_tiger_string($game_result);
				break;
			case 401://輪盤
				$str ='{點數:'.mb_ereg_replace('{|}', '', $game_result).'}';
				break;
		}
		return $str;
	}

	/**
	 * 取得龍虎遊戲結果字串
	 * @param $game_result
	 * @return mixed
	 */
	public static function get_drangon_tiger_string($game_result)
	{
		$str = $game_result;
		preg_match_all('/{[0-9-]*}/', $game_result, $rs_arr);
		if(isset($rs_arr[0]) && count($rs_arr[0]) == 2){
			$rs_arr = $rs_arr[0];
			for ($i = 0;$i < count($rs_arr);$i++){
				$rs_str = ($i == 0) ? '龍:': '虎:';
				$tmp_rs_one = mb_ereg_replace('{|}', '', $rs_arr[$i]);
				$rs_arr[$i] = '{'.$rs_str.self ::get_card_string($tmp_rs_one).'}';
			}
			$str = implode(',', $rs_arr);//合併結果
		}
		return $str;
	}

	/**
	 * 取得百家樂遊戲結果字串
	 * @param $game_result
	 * @return mixed
	 */
	public static function get_baccarat_string($game_result)
	{
		$str = $game_result;
		preg_match_all('/{[0-9,-]*}/', $game_result, $rs_arr);
		if(isset($rs_arr[0]) && count($rs_arr[0]) == 2){
			$rs_arr = $rs_arr[0];
			for ($i = 0;$i < count($rs_arr);$i++){
				$rs_str = ($i == 0) ? '閒:': '庄:';
				$rs_one_arr = array();//儲存牌面結果
				$tmp_rs_one_arr = explode(',', mb_ereg_replace('{|}', '', $rs_arr[$i]));
				foreach ($tmp_rs_one_arr as $tmp_rs_one){
					if($tmp_rs_one != -1){ //-1代表沒牌
						$rs_one_arr[] = self :: get_card_string($tmp_rs_one);
					}
				}
				$rs_arr[$i] = '{'.$rs_str.implode(',', $rs_one_arr).'}';
			}
			$str = implode(',', $rs_arr);//合併結果
		}
		 return $str;
	}

	/**
	 * 取得撲克牌含花色之字串
	 * @param $num 開牌結果 int
	 * @return string
	 */
	public static function get_card_string($num)
	{
		$str = '';
		$f_num = (int)mb_substr((string)$num, 0, 1); //花色
		$c_num = (int)mb_substr((string)$num, 1);//數字
		//取得花色字串
		if($f_num == 1){ $str = '黑桃'; }elseif($f_num == 2){ $str = '紅桃'; }elseif($f_num == 3){ $str = '梅花'; }elseif($f_num == 4){ $str = '方塊'; }

		//花色+數字
		if($c_num <= 10){
			$str .= (string)$c_num;
		}else{
			if($c_num == 11){ $str .= 'J'; }elseif ($c_num == 12){ $str .= 'Q'; }elseif ($c_num == 13){ $str .= 'K';}
		}
		return $str;
	}

	public static function create_bet_content_column($info, $tpl){
		$bet_type = 'betType';
		$game_result = 'gameResult';
		$html3 = $tpl->block('allbet_bet_content')->assign([
			'bet_type' => self::get_bet_type_string($info->betType),
			'game_result' => self::get_game_result_string($info->gameType, $info->gameResult),
		])->render(false);

		return $html3;
	}
	//--------------------------------------------------------------------------------------------------------遊戲說明//
}