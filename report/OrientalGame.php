<?php

namespace Game\Report;

class OrientalGame extends \Game\OrientalGame
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
					'report_uid' => $val['orderNumber'],//單號
					'bet_date' => $val['addTime'] / 1000,//投注時間
					'set_date' => $val['instime'] / 1000,//結帳時間
					'bet_amount' => $val['bettingAmount'],//投注金額
					'set_amount' => $val['validAmount'],//有效投注
					'win_amount' => $val['winLoseAmount'],//輸贏結果
					'type_id' => ($val['winLoseAmount'] > 0)? 1: (($val['winLoseAmount'] < 0)? 2: 3),//1贏 2輸 3和
					'info' => $info,//原始資料 json_encode
					'member_id' => $member_id,
					'account' => $account,//遊戲帳號
				];
			}
			\App::make('Lib\Mix')->insert_duplicate('t_report_orientalgame', $insert);
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
			'gamePlatform' => 'OG', 
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

	public static function get_game($arr)
	{
		$game = $arr['gameNameID'] ?? '';
		$type = array(
			11 => '百家樂',
			12 => '龍虎',
			13 => '輪盤',
			14 => '骰寶',
			15 => '德州撲克',
			16 => '番攤'
		);
		
		return $type[$game] ?? '';
	}

	public static function parse_game_bet($id = -1){
		$type = [
			//百家樂
			101 => '閑',
			102 => '莊',
			103 => '和',
			104 => '閑對',
			105 => '莊對',
			126 => 'Super6',
			//龍虎
			201 => '龍',
			202 => '虎',
			203 => '和',
			//番攤
			742 => '正 1',
			743 => '正 2',
			744 => '正 3',
			745 => '正 4',
			746 => '角1_2',
			747 => '角2_3',
			748 => '角3_4',
			749 => '角4_1',
			750 => '1 番',
			751 => '2 番',
			752 => '3 番',
			753 => '4 番',
			754 => '1 念2',
			755 => '1 念3',
			756 => '1 念4',
			757 => '2 念1',
			758 => '2 念3',
			759 => '2 念4',
			760 => '3 念1',
			761 => '3 念2',
			762 => '3 念4',
			763 => '4 念1',
			765 => '4 念2  764 4 念3',
			766 => '單',
			767 => '雙',
			768 => '1,2 四 通',
			769 => '1,2 三 通',
			770 => '1,3 四 通',
			771 => '1,3 二 通',
			772 => '1,4 三 通',
			773 => '1,4 二 通',
			774 => '2,3 四 通',
			775 => '2,3 一 通',
			776 => '2,4 三 通',
			777 => '2,4 一 通',
			778 => '3,4 二 通',
			779 => '3,4 一 通',
			780 => '三門(3,2,1)',
			781 => '三門(2,1,4)',
			782 => '三門(1,4,3)',
			783 => '三門(4,3,2)',
			//骰寶
			401 => '點數4',
			402 => '點數5',
			403 => '點數6',
			404 => '點數7',
			405 => '點數8',
			406 => '點數9',
			407 => '點數10',
			408 => '點數11',
			409 => '點數12',
			410 => '點數13',
			411 => '點數14',
			412 => '點數15',
			413 => '點數16',
			414 => '點數17',
			415 => '小',
			416 => '大',
			417 => '三軍1',
			418 => '三軍2',
			419 => '三軍3',
			420 => '三軍4',
			421 => '三軍5',
			422 => '三軍6',
			423 => '短牌1',
			424 => '短牌2',
			425 => '短牌3',
			426 => '短牌4',
			427 => '短牌5',
			428 => '短牌6',
			429 => '圍骰1',
			430 => '圍骰2',
			431 => '圍骰3',
			432 => '圍骰4',
			433 => '圍骰5',
			434 => '圍骰6',
			435 => '全骰',
			436 => '長牌1~2',
			437 => '長牌1~3',
			438 => '長牌1~4',
			439 => '長牌1~5',
			440 => '長牌1~6',
			441 => '長牌2~3',
			442 => '長牌2~4',
			443 => '長牌2~5',
			444 => '長牌2~6',
			445 => '長牌3~4',
			446 => '長牌3~5',
			447 => '長牌3~6',
			448 => '長牌4~5',
			449 => '長牌4~6',
			450 => '長牌5~6',
			451 => '單',
			452 => '雙',
			453 => '112',
			454 => '113',
			455 => '114',
			456 => '115',
			457 => '116',
			458 => '122',
			459 => '133',
			460 => '144',
			461 => '155',
			462 => '166',
			463 => '223',
			464 => '224',
			465 => '225',
			466 => '226',
			467 => '233',
			468 => '244',
			469 => '255',
			470 => '266',
			471 => '334',
			472 => '335',
			473 => '336',
			474 => '344',
			475 => '355',
			476 => '366',
			477 => '445',
			478 => '446',
			479 => '455',
			480 => '466',
			481 => '556',
			482 => '566',
			483 => '123',
			484 => '124',
			485 => '125',
			486 => '126',
			487 => '134',
			488 => '135',
			489 => '136',
			490 => '145',
			491 => '146',
			492 => '156',
			493 => '234',
			494 => '235',
			495 => '236',
			496 => '245',
			497 => '246',
			498 => '256',
			499 => '345',
			4100 => '346',
			4101 => '356',
			4102 => '456',
			//輪盤
			501 => '1',
			502 => '2',
			503 => '3',
			504 => '4',
			505 => '5',
			506 => '6',
			507 => '7',
			508 => '8',
			509 => '9',
			510 => '10',
			511 => '11',
			512 => '12',
			513 => '13',
			514 => '14',
			515 => '15',
			516 => '16',
			517 => '17',
			518 => '18',
			519 => '19',
			520 => '20',
			521 => '21',
			522 => '22',
			523 => '23',
			524 => '24',
			525 => '25',
			526 => '26',
			527 => '27',
			528 => '28',
			529 => '29',
			530 => '30',
			531 => '31',
			532 => '32',
			533 => '33',
			534 => '34',
			535 => '35',
			536 => '36',
			537 => '0',
			538 => '3,37',
			539 => '3,6',
			540 => '6,9',
			541 => '9,12',
			542 => '12,15',
			543 => '15,18',
			544 => '18,21',
			545 => '21,24',
			546 => '24,27',
			547 => '27,30',
			548 => '30,33',
			549 => '33,36',
			550 => '2,3',
			551 => '5,6',
			552 => '8,9',
			553 => '11,12',
			554 => '14,15',
			555 => '17,18',
			556 => '20,21',
			557 => '23,24',
			558 => '26,27',
			559 => '29,30',
			560 => '32,33',
			561 => '35,36',
			562 => '2,37',
			563 => '2,5',
			564 => '5,8',
			565 => '8,11',
			566 => '11,14',
			567 => '14,17',
			568 => '17,20',
			569 => '20,23',
			570 => '23,26',
			571 => '26,29',
			572 => '29,32',
			573 => '32,35',
			574 => '1,2',
			575 => '4,5',
			576 => '7,8',
			577 => '10,11',
			578 => '13,14',
			579 => '16,17',
			580 => '19,20',
			581 => '22,23',
			582 => '25,26',
			583 => '28,29',
			584 => '31,32',
			585 => '34,35',
			586 => '1,37',
			587 => '1,4',
			588 => '4,7',
			589 => '7,10',
			590 => '10,13',
			591 => '13,16',
			592 => '16,19',
			593 => '19,22',
			594 => '22,25',
			595 => '25,28',
			596 => '28,31',
			597 => '31,34',
			598 => '2,3,37',
			599 => '1,2,37',
			600 => '1,2,3',
			601 => '4,5,6',
			602 => '7,8,9',
			603 => '10,11,12',
			604 => '13,14,15',
			605 => '16,17,18',
			606 => '19,20,21',
			607 => '22,23,24',
			608 => '25,26,27',
			609 => '28,29,30',
			610 => '31,32,33',
			611 => '34,35,36',
			612 => '2,5,3,6',
			613 => '5,8,6,9',
			614 => '8,11,9,12',
			615 => '11,14,12,15',
			616 => '14,17,15,18',
			617 => '17,20,18,21',
			618 => '20,23,21,24',
			619 => '23,26,24,27',
			620 => '26,29,27,30',
			621 => '29,32,30,33',
			622 => '32,35,33,36',
			623 => '1,4,2,5',
			624 => '4,7,5,8',
			625 => '7,10,8,11',
			626 => '10,13,11,14',
			627 => '13,16,14,17',
			628 => '16,19,17,20',
			629 => '19,22,20,23',
			630 => '22,25,23,26',
			631 => '25,28,26,29',
			632 => '28,31,29,32',
			633 => '31,34,32,35',
			634 => '1,2,3,37',
			635 => '1,2,3,4,5,6',
			636 => '4,5,6,7,8,9',
			637 => '7,8,9,10,11,12',
			638 => '10,11,12,13,14,15',
			639 => '13,14,15,16,17,18',
			640 => '16,17,18,19,20,21',
			641 => '19,20,21,22,23,24',
			642 => '22,23,24,25,26,27',
			643 => '25,26,27,28,29,30',
			644 => '28,29,30,31,32,33',
			645 => '31,32,33,34,35,36',
			646 => '3,6,9,12,15,18,21,24,27,30,33,36',
			647 => '2,5,8,11,14,17,20,23,26,29,32,35',
			648 => '1,4,7,10,13,16,19,22,25,28,31,34',
			649 => '1,2,3,4,5,6,7,8,9,10,11,12',
			650 => '13,14,15,16,17,18,19,20,21,22,23,24',
			651 => '25,26,27,28,29,30,31,32,33,34,35,36',
			652 => '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18',
			653 => '2,4,6,8,10,12,14,16,18,20,22,24,26,28,30,32,34,36',
			654 => '1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36',
			655 => '2,4,6,8,10,11,13,15,17,20,22,24,26,28,29,31,33,35',
			656 => '1,3,5,7,9,11,13,15,17,19,21,23,25,27,29,31,33,35',
			657 => '19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36'
		];
		return $type[$id] ?? '';
	}

	public static function create_bet_content_column($info){
		//Game Result
		$bet = self::parse_game_bet($info->gameBettingKind);
		$amount = $info->bettingAmount;
		return "{$bet}: {$amount}";
	}
}