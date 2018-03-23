<?php

namespace Game\Report;

class BooonGo extends \Game\BooonGo
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

				$insert[] = [
					'report_uid' => $val['bet_id'],//單號
					'bet_date' => strtotime($val['game_time']),//投注時間
					'set_date' => strtotime($val['report_date']),//結帳時間
					'bet_amount' => $val['bet'],//投注金額
					'set_amount' => $val['bet'],//有效投注
					'win_amount' => $val['win'],//輸贏結果
					'type_id' => ($val['win'] > 0? 1: 2),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_booongo', $insert);
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

			$result = array_merge($result, $data['data'] ?? []);
		}

		return ['code' => 0, 'data' => $result];
	}

	public function _get_report($sdate, $edate, $page = 1)
	{
		$result = [];
		
		while(1){// 取得下一頁
			$data = $this->__get_report($sdate, $edate, $page++);
			$max = $data['data']['total'] ?? 0;
			$result = array_merge($result, $data['data']['bets'] ?? []);
			
			if(count($result) >= $max){
				break;
			}
		}
		
		return ['code' => 0, 'data' => $result];
	}
	
	public function __get_report($sdate, $edate, $page = 1)
	{
		$post = [
			'partner_id' => $this->api['_partner_id'],
			'starttime' => date('Y-m-d H:i:s', $sdate),
			'endtime' => date('Y-m-d H:i:s', $edate),
			'page' => $page,
			'rows' => 1000,
		];
		$post['hash'] = @$this->getSignature($post);
		$result = \App::make('Lib\Curl')->curl($this->api['_url'] . '/game/history.html', json_encode($post));
		$result = json_decode($result, true);
		
		if($result ?? 0){
			return ['code' => 0, 'data' => $result];
		}else{ //錯誤回傳
			return ['code' => 7, 'text' => ''];
		}
	}
	
	public static function get_game($info = [])
	{
		$game = $info['game_code'] ?? 0;

		$type = [
			1 => 'BG12_animals',
			2 => 'BG15_golden_eggs',
			3 => 'BG88_wild_dragon',
			4 => 'BGafrican_spirit',
			5 => 'BGart_of_the_heist',
			6 => 'BGchristmas_charm',
			7 => 'BGcrazy_gems',
			8 => 'BGdancing_dragon',
			9 => 'BGdiego_fortune',
			10 => 'BGfruiterra',
			11 => 'BGfruiterra_fortune',
			12 => 'BGfruits_of_the_nile',
			13 => 'BGfruity_frost',
			14 => 'BGgnomes_gems',
			15 => 'BGgods_temple',
			16 => 'BGhalloween_witch',
			17 => 'BGhappy_chinese_new_year',
			18 => 'BGhells_band',
			19 => 'BGhunting_party',
			20 => 'BGjuice_and_fruits',
			21 => 'BGkailash_mystery',
			22 => 'BGkangaliens',
			23 => 'BGlucky_pirates',
			24 => 'BGlucky_xmas',
			25 => 'BGpatricks_pub',
			26 => 'BGpoisoned_apple',
			27 => 'BGsecret_of_nefertiti',
			28 => 'BGsingles_day',
			29 => 'BGthe_witch',
			30 => 'BGthunder_reels',
			31 => 'BGthunder_zeus',
			32 => 'BGwild_galaxy',
			'BG12_animals' => '12发财星',
			'BG15_golden_eggs' => '快乐鸟',
			'BG88_wild_dragon' => '走运一路8',
			'BGafrican_spirit' => '非洲之魂',
			'BGart_of_the_heist' => '艺术之劫',
			'BGchristmas_charm' => '圣诞快乐',
			'BGcrazy_gems' => '疯狂水晶洞',
			'BGdancing_dragon' => '春节舞龙',
			'BGdiego_fortune' => '失落的乐园',
			'BGfruiterra' => '欢乐水果盘',
			'BGfruiterra_fortune' => '经典小玛莉',
			'BGfruits_of_the_nile' => '尼罗河水果',
			'BGfruity_frost' => '果冻',
			'BGgnomes_gems' => '小矮人寻宝记',
			'BGgods_temple' => '上帝的殿堂',
			'BGhalloween_witch' => '万圣美魔女',
			'BGhappy_chinese_new_year' => '恭喜大发财',
			'BGhells_band' => '摇滚地狱',
			'BGhunting_party' => '就爱打猎趴',
			'BGjuice_and_fruits' => '果汁与水果',
			'BGkailash_mystery' => '神灵之山的传奇',
			'BGkangaliens' => '袋鼠怪客',
			'BGlucky_pirates' => '幸运海盗',
			'BGlucky_xmas' => '淘气圣诞',
			'BGpatricks_pub' => '牛逼爱尔兰大佬',
			'BGpoisoned_apple' => '致命毒苹果',
			'BGsecret_of_nefertiti' => '埃及艳后的任务',
			'BGsingles_day' => '11.11光棍节',
			'BGthe_witch' => '女巫的青蛙',
			'BGthunder_reels' => '打雷卷轴',
			'BGthunder_zeus' => '希腊神话',
			'BGwild_galaxy' => '怪客星球',
		];

		return $game? ($type[$game] ?? $game): $type;
	}
}