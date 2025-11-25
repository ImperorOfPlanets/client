<?php
namespace App\Helpers\Servers;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Log;

class Servers
{
	
	public function check_server_availability($url, $timeout = 5) {
		$fp = @fsockopen($url, 80, $errno, $errstr, $timeout);
		
		if (!$fp) {
			return false;
		}
		
		fclose($fp);
		return true;
	}
	
	/*$url = 'https://example.com';
	$is_available = check_server_availability($url);
	
	if ($is_available) {
		echo "Сервер доступен";
	} else {
		echo "Сервер недоступен";
	}*/
}