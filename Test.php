<?php

namespace Lib;

class Test
{
	public function __construct(){
		// 建立遊戲帳號
		//\Game\Account::generate_accounts($branch_id, $count, $game_id);
		\App::make('Game\SuperSport')->add_account('terryli', 'qweasdzxc');
		// 獲取並設置報表
		// \App::make('Game\Report\GAME_NAME')->set_report($unix_timestamp_start, $unix_timestamp_end);
		
		// 設置報表快取
		// \App::make('Game\Report\Cache')->set_cache($unix_timestamp_start, $unix_timestamp_end);
		
		//game_id alias     GAME_NAME
		// 1	電子錢包	Wallet
		// 2	超級體育	SuperSport
		// 3	歐博真人	Allbet
		// 4	黃金俱樂部	GoldenClub
		// 5	微妙電子	Microsova
		// 6	康博電子	ComeBets
		// 7	黃金亞洲	GoldenAsia
		// 8	遊聯電子	GlobalGaming
		// 9	沙龍真人	Salon
		// 10	贏家體育	XinXin
		// 11	易博真人	Ebet
		// 12	ＤＧ真人	DreamGame
		// 13	ＯＧ真人	OrientalGame
		// 14	運彩股票	ZhiFuBao
		// 15	ＳＧ彩票	NinetyNine
		// 16	ＬＪ彩票	GlobeBet
		// 17	ＰＳ電子	PlayStar
		// 18	EVO電子		EvoPlay
		// 19	BNG電子		BooonGo
	}
}
