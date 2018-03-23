<?php

namespace Lib;

class Curl
{
	public $httpheader = null;

	public function __construct(){
		if(!file_exists('cookie')){
			mkdir('cookie', 0755);
		}
	}

	public function curl($url, $send_data = [], $post = true, $cookie_jar = false)
	{
		$cookie_file = 'cookie/curl.txt';

		$options = [
			CURLOPT_URL => $url,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.4) Gecko/20060508 Firefox/1.5.0.4',
			CURLOPT_HEADER => false,
			CURLOPT_POST => $post,
			CURLOPT_ENCODING => 'gzip,deflate',
			CURLOPT_FOLLOWLOCATION => true,//是否抓取轉址
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FRESH_CONNECT => false,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_COOKIESESSION => false,
			CURLOPT_COOKIEFILE => $cookie_file,
			CURLOPT_REFERER => $url,
		];

		if($send_data){
			$options[CURLOPT_POSTFIELDS] = is_array($send_data)? http_build_query($send_data): $send_data;
		}

		if($this->httpheader){
			$options[CURLOPT_HTTPHEADER] = $this->httpheader;
		}

		if($cookie_jar){
			$options[CURLOPT_COOKIEJAR] = $cookie_file;
		}

		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$response = curl_exec($ch);
		curl_close($ch);
		
		// curl debug
		// \DB::table('t_log_system')->insert([
			// 'cdate' => time(),
			// 'title' => '[curl]',
			// 'content' => 'SEND: ' . json_encode($options) . ' RESPOND: ' . $response,
		// ]);
		
		return $response;
	}

	public function set_header($header)
	{
		$this->httpheader[] = $header;
		return $this;
	}
}
