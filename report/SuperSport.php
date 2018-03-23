<?php

namespace Game\Report;

class SuperSport extends \Game\SuperSport
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
			foreach($report as $key => $val) {
				$info = json_encode($val, JSON_UNESCAPED_UNICODE);
				$account = $val['m_id'];
				$member_id = $tool->get($account)->member_id ?? 0;
				
				$insert[] = [
					'report_uid' => $val['sn'],//單號
					'bet_date' => strtotime($val['m_date']),//投注時間
					'set_date' => strtotime($val['count_date']),//結帳時間
					'bet_amount' => $val['gold'],//投注金額
					'set_amount' => $val['bet_gold'],//有效投注
					'win_amount' => $val['result_gold'],//輸贏結果
					'type_id' => (trim($val['status']) == 'w')? 1: (($val['status'] == 'l')? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_supersport', $insert);
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
			$account = \App::make('Game\Account')->init($this->_lib)->get_recent($val->id);
			$data = @$this->_get_report($sdate, $edate, $account);
			
			$result = array_merge($result, $data['data']);
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 遊戲帳號分組
	public function _get_report($sdate, $edate, $account)
	{
		$tmp = explode(',', $account);
		$arr = array_chunk($tmp, 200);
		
		$result = [];
		
		foreach($arr as $v){
			
			$account = implode(',', $v);
			
			$data = @$this->__get_report($sdate, $edate, $account);
			
			$result = array_merge($result, $data['data']['data'] ?? []);
		}
		
		return ['code' => 0, 'data' => $result];
	}

	// 單次取得報表
	public function __get_report($sdate, $edate, $account)
	{
		$post = [
			'act' => 'detail',
			'account' => $this->_aes_encrypt($account, $this->api['_aes_key'], $this->api['_aes_iv']),
			'level' => '1',
			's_date' => date('Y-m-d', $sdate),
			'e_date' => date('Y-m-d', $edate),
		];
		
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/api/report', $post);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? null) === 999){
			return ['code' => 0, 'data' => $result];
		}else{
			return ['code' => 7, 'text' => ''];
		}
	}


	public static function get_team_name($team_num){
		$teams = array(
			0  => '全部',
			1  => '美棒',
			2  => '日棒',
			3  => '台棒',
			4  => '韓棒',
			5  => '冰球',
			6  => '籃球',
			7  => '彩球',
			8  => '美足',
			9  => '網球',
			10 => '足球',
			11 => '指數',
			12 => '賽馬',
			14 => '其他',
		);

		if(!array_key_exists($team_num, $teams)) 
			return '';

		return ($teams[$team_num]) ? $teams[$team_num] : $team_num;
	}

	public static function get_game_type_string($game_type){
		$game_types = array(
			0  => '全場',
			1  => '上半場',
			2  => '下半場',
			3  => '第一節',
			4  => '第二節',
			5  => '第三節',
			6  => '第四節',
			7  => '滾球',
			8  => '滾球上半場',
			9  => '滾球下半場',
			10 => '多種玩法',
		);

		if(!array_key_exists($game_type, $game_types)) 
			return '';

		return ($game_types[$game_type]) ? $game_types[$game_type] : $game_type;
	}

	public static function get_fashion_string($fashion){
		$fashions = array(
			0  => '全部',
			1  => '讓分',
			2  => '大小',
			3  => '獨贏',
			4  => '單雙',
			5  => '一輸二贏',
			10 => '搶首分',
			11 => '搶尾分',
			12 => '波膽',
			13 => '單節最高分',
			20 => '過關'
		);

		if(!array_key_exists($fashion, $fashions)) 
			return '';

		return ($fashions[$fashion]) ? $fashions[$fashion] : $fashion;
	}

	public static function get_game($info){
		$game_type_string = self::get_team_name($info['team_no'] ?? -1);
		$fashion = self::get_fashion_string($info['fashion'] ?? -1);

		return $game_type_string . ' / ' . $fashion;
	}

	public static function create_bet_content_column($info, $tpl){
		$info = json_decode(json_encode($info), true);
		$detail = []; //detail array in $info
		$bets = ''; //combination of each detail item
		if(isset($info['detail'])){
			$detail = $info['detail'];
		}else{
			$detail[] = $info;
		}
		foreach($detail as $item){
			$note = '';
			switch($item['fashion']){
				case 2:
					$note .= $item['chum_num'] . ' ' . (($item['mv_set'])? '小': '大');
					break;
				case 4:
					$note .= ($item['mv_set'])? '雙': '單';
					break;
				default:
					$note .= ($item['mv_set']? $item['main_team']: $item['visit_team']);
					break;
			}
			$note .= $item['compensate'];

			if($item['visit_team']){
				$bets .= $tpl->block('ss_vs_partial')->assign([
					'visit_team' => $item['visit_team'],
					'chum_num' => ($item['mode']==2? $item['chum_num']: ''),
					'main_team' => $item['main_team'],
					'chum_num2' => ($item['mode']==1? $item['chum_num']: ''),
					'note' => $note,
					'score2' => $item['score2'],
					'score1' => $item['score1'],
				])->render(false);
			}else{
				$bets .= $tpl->block('ss_vs_partial')->assign([
					'main_team' => $item['main_team'],
					'note' => $note,
					'score1' => $item['score1'],
				])->render(false);
			}
			
			if(in_array($item['status'] ?? '', ['f', 'd'])){
				$bets .= $tpl->block('ss_status_partial')->assign([
					'status' => '[退組]',
				])->render(false);
			}
		}//end of foreach detail
		$game_type = self::get_game_type_string($info['g_type']);

		$html3 = $tpl->block('supersport_bet_content')->assign([
			'bet_date' => date('Y-m-d H:i:s', $info['bet_date'] ?? 0),
			'league' => $detail[0]['league'],
			'game_type' => $game_type,
			'bets' => $bets,
		])->render(false);

		return $html3;
	}
}