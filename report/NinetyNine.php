<?php

namespace Game\Report;

class NinetyNine extends \Game\NinetyNine
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
			$result = $this->get_report($sdate, $edate, $account);
		}

		if($result['data'] ?? 0){
			
			$report = $result['data'];
			$insert = [];
			
			$tool = \App::make('Game\Account')->init($this->_lib);
			foreach($report as $val){
				$info = json_encode($val, JSON_UNESCAPED_UNICODE);
				$account = $val['username'];
				$member_id = $tool->get($account)->member_id ?? 0;
				
				$insert[] = [
					'report_uid' => ($val['bid'] . '_' . substr(md5($val['content']), -5)),//單號
					'bet_date' => strtotime($val['time']),//投注時間
					'set_date' => strtotime($val['time']),//結帳時間
					'bet_amount' => $val['tm'],//投注金額
					'set_amount' => $val['tm'],//有效投注
					'win_amount' => $val['wl'],//輸贏結果
					'type_id' => ($val['win'] > 0? 1: 0),//1贏 0輸
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_ninetynine', $insert);
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
			
			$account = @$this->get_active_accounts($sdate, $edate);
			
			$data = @$this->_get_report($sdate, $edate, $account);
			$result = array_merge($result, $data['data'] ?? []);
		}
		
		return ['code' => 0, 'data' => $result];
	}

	public function _get_report($sdate, $edate, $account=null)
	{
		$arr = explode(',', $account);
		
		$result = [];
		
		foreach($arr as $account){
			$data = @$this->__get_report($sdate, $edate, $account);
			$result = array_merge($result, $data['data'] ?? []);
		}
		
		return ['code' => 0, 'data' => $result];
	}

	public function __get_report($sdate, $edate, $account)
	{
		$parame = [
			'start' => date('Y-m-d', $sdate),
			'end' => date('Y-m-d', $edate),
			'acc' => $account,
			'lv' => 6,
			'station' => $this->api['_station'],
		];
		
		$post = [
			'code' => 'EXT_REPORT_DTL',
			'parame' => json_encode($parame),
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'], $post);
		$result = json_decode($result, true);
		
		if(($result['status'] ?? null) === 1){
			return ['code' => 0, 'data' => $this->adjust($result['data'], $account)];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}

	public function adjust($arr, $account)
	{
		foreach($arr ?? [] as $key=>$val){
			$arr[$key]['username'] = $account;
		}
		return $arr;
	}
	
	public function get_active_accounts($sdate, $edate)
	{
		$parame = [
			'start' => date('Y-m-d', $sdate),
			'end' => date('Y-m-d', $edate),
			'station' => $this->api['_station'],
		];

		$post = [
			'code' => 'EXT_REPORT',
			'parame' => json_encode($parame),
		];

		$result = \App::make('Lib\Curl')->curl($this->api['_url'], $post);
		$result = json_decode($result, true);
		
		$arr = [];
		foreach($result['data'] ?? [] as $account){
			$arr[] = $account['user'];
		}

		return implode(',', $arr);
	}

	public static $cs = array(
		1 => '六和',
		2 => '大樂',
		3 => '539',
	);

	public static $play = array(
		1 => '全車',
		2 => '特別號',
		3 => '雙面',
		4 => '台號',
		5 => '特尾三',
		6 => '二星',
		7 => '三星',
		8 => '四星',
		9 => '天碰二',
		10 => '天碰三',
	);

	/**
	 * 取得遊戲類型字串
	 * @param $game_type 遊戲類型 int
	 * @return mixed
	 */
	public static function get_game_type_string($cs){
		return self::$cs[$cs] ?? $cs;
	}

	public static function get_game($info){
		return self::get_game_type_string($info['cs'] ?? '');
	}

	public static function get_play_string($play){
		return self::$play[$play] ?? $play;
	}

	public static function get_content_string($content){
		$result = '';
		
		$arr = explode('&', $content);
		if($arr[1] ?? 0){
			$i = 1;
			foreach($arr as $v){
				$result .= '第' . ($i++) . '柱:' . $v . '<br>';
			}
		}else{
			$result = $arr[0];
		}
		
		return $result;
	}

	public static function create_bet_content_column($info, $tpl){
		$html3 = $tpl->block('ninetynine_bet_content')->assign([
			'play' => self::get_play_string($info->play),
			'content' => self::get_content_string($info->content),
		])->render(false);
		
		return $html3;
	}
}