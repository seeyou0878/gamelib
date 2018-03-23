<?php

namespace Game\Report;

class GoldenAsia extends \Game\GoldenAsia
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
                    'report_uid' => $val['betId'],//單號
                    'bet_date' => strtotime($val['betTime']),//投注時間
                    'set_date' => strtotime($val['calTime']),//結帳時間
                    'bet_amount' => $val['betPoints'],//投注金額
                    'set_amount' => $val['availableBet'],//有效投注
                    'win_amount' => $val['winorloss'] - $val['betPoints'],//輸贏結果
                    'type_id' => ($val['winorloss'] > $val['betPoints'])? 1: (($val['winorloss'] < $val['betPoints'])? 2: 3),//1贏 2輸 3和
                    'info' => $info,//原始資料 json_encode
                    'member_id' => $member_id,
                    'account' => $account,//遊戲帳號
                ];
            }
			\App::make('Lib\Mix')->insert_duplicate('t_report_goldenasia', $insert);
        }
        // 標記注單
		//$this->mark_report();
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
			
			$result = array_merge($result, $data['data']['obj'] ?? []);
		}
		
		if(($result['code'] ?? false) === 0){
            return ['code' => 0, 'data' => $result];
        }else{
            return ['code' => 7, 'data' => ''];
        }
	}
	
    // 查詢特定會員投注紀錄 30秒只能請求1次, 不用任何參數
    public function _get_report()
    {
        $acc = $this->api['_agent'];
		$key = $this->api['_api_key'];
		
		$url = $this->api['_url'] . $acc . '/' . md5($key . $acc) . '/getBetSheet';
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, '', false);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? false) === 0){
            return ['code' => 0, 'data' => $this->adjust($result)];
        }else{
            return ['code' => 7, 'data' => ''];
        }
    }
	
	public function adjust($arr)
	{
		$this->mark = [];
		foreach($arr['obj'] ?? [] as $key=>$val){
			$this->mark[] = $val['betId'];
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
		$key = $this->api['_api_key'];
		$post = json_encode($arr);
		$url = $this->api['_url'] . $acc . '/' . md5($key . $acc) . '/markBetSheet';
		$result = \App::make('Lib\Curl')->set_header('Content-Type:application/json')->curl($url, $post);
		$result = json_decode($result, true);
		
		if(($result['code'] ?? false) === 0){
            return ['code' => 0, 'data' => $result];
        }else{
            return ['code' => 7, 'data' => ''];
        }
    }

    //遊戲說明--------------------------------------------------------------------------------------------------------//
	public static function get_game($arr)
	{
		$game = $arr['gameId'] ?? '';
		
		$type = array(
			1 => '百家樂',
			2 => '保險百家樂',
			3 => '龍虎',
			4 => '輪盤',
		);
		
		return $type[$game] ?? '';
	}
	
	public static function get_game_result_string($arr)
	{
		$result = '';
		$game = $arr['gameId'] ?? '';
        
        switch($game){
            case 1:
			case 2:
				$result .= self::get_bac($arr);
				break;
			case 3:
				$result .= self::get_dtx($arr);
				break;
			case 4:
				$result .= self::get_rot($arr);
				break;
        }
		
        return $result;
	}
	
	public static function get_rot($arr)
	{
		$result = '';
		$type = array(
			'direct'   => '直注',
			'separate' => '分注',
			'street'   => '街注',
			'angle'    => '角注',
			'line'     => '線注',
			'three'    => '三數注',
			'four'     => '四個號碼',
			'fristRow' => '行注一',
			'sndRow'   => '行注二',
			'thrRow'   => '行注三',
			'fristCol' => '打注一',
			'sndCol'   => '打注二',
			'thrCol'   => '打注三',
			'red'      => '紅色',
			'black'    => '黑色',
			'odd'      => '單',
			'even'     => '雙',
			'low'      => '小',
			'high'     => '大',
		);
		
		foreach($arr['betDetail'] as $key=>$val){
			$result .= $type[$key] ?? '';
		}
		
		$result .= '<br>結果:' . $arr['result']['result'] ?? '';
		
		return $result;
	}
	
	public static function get_dtx($arr)
	{
		$result = '';
		$type = array(
			'dragon' => '龍',
			'tiger'  => '虎',
			'tie'    => '和',
		);
		
		foreach($arr['betDetail'] as $key=>$val){
			$result .= $type[$key] ?? '';
		}
		
		$result .= '<br>{龍:' . self::get_card($arr['result']['poker'][0] ?? '') . '}, {虎:' . self::get_card($arr['result']['poker'][1] ?? '') . '}';
		
		return $result;
	}
	
	public static function get_bac($arr)
	{
		$result = '';
		$type = array(
			'banker' => '莊',
			'player' => '閑',
			'tie'    => '和',
			'pPair'  => '閑對',
			'bPair'  => '莊對',
			'big'    => '大',
			'small'  => '小',
		);
		
		foreach($arr['betDetail'] as $key=>$val){
			$result .= $type[$key] ?? '';
		}
		$result .= '<br>';
		$tmp = array();
		$tmp[] = self::get_card($arr['result']['poker'][0] ?? '');
		$tmp[] = self::get_card($arr['result']['poker'][2] ?? '');
		$tmp[] = self::get_card($arr['result']['poker'][4] ?? '');
		$result .= '{閑:' . trim(implode(',', $tmp), ',') . '}, ';
		
		$tmp = array();
		$tmp[] = self::get_card($arr['result']['poker'][1] ?? '');
		$tmp[] = self::get_card($arr['result']['poker'][3] ?? '');
		$tmp[] = self::get_card($arr['result']['poker'][5] ?? '');
		$result .= '{莊:' . trim(implode(',', $tmp), ',') . '}';
		
		return $result;
	}
	
    public static function get_card($card)
	{
        $type = array('', '黑桃', '紅桃', '梅花', '方塊');
		$alias = array(1 => 'A', 11 => 'J', 12 => 'Q', 0 => 'K');
		$result = $type[ceil($card/13)] . ($alias[$card % 13] ?? $card % 13);
		
		return ($card > 0 && $card < 53)? $result: '';
    }
    //--------------------------------------------------------------------------------------------------------遊戲說明//
}