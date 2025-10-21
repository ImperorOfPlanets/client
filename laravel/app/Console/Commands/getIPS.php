<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Cache;

class getIPS extends Command
{
	protected $signature = 'command:getIPS {type}';
	protected $description = 'update ip list for interaction';

	public function handle()
	{
		//Выводим кэш
		echo "Старый кэш\n";
		$resultCache = Cache::get('ips');
		print_r($resultCache);

        // URL, к которому делается запрос
        $url = 'https://myidon.site/ips';

        // Отправляем запрос с помощью file_get_contents
        $response = file_get_contents($url);

        // Проверяем статус ответа
        if ($response === FALSE)
        {
            echo "Ошибка при получении данных";
        }
        else
        {
            // Преобразуем ответ в массив JSON
            $json = json_decode($response, true);
			Cache::add('ips',$json);
            // Выводим данные
            //print_r($json);
        }
	}
}