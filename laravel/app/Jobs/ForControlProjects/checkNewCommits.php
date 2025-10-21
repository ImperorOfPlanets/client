<?php
namespace App\Jobs\ForControlProjects;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

use App\Helpers\Logs\Logs as Logator;

use App\Models\Core\Objects;

use Illuminate\Support\Facades\Http;

use Carbon\Carbon;

class checkNewCommits implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable;

    public $logator;

	public function handle()
	{
        $object = Objects::find(2);

		$url = 'gitflic.myidon.site/rest-api';
        //$access_token = ;
		//Логатор
		$this->logator = new Logator;

		$this->logator->setAuthor('checkNewCommits');
		$this->logator->setType('success');
		$this->logator->setText('Запущен процесс проверки коммитов');
		$this->logator->write();

        //Получаем комиты
        // url - /project/{ownerAlias}/{projectAlias}/commits
            //Переменная пути запроса	Тип	Описание
            //ownerAlias	String	Псевдоним владельца проекта
            //projectAlias	String	Псевдоним проекта
        
        $urlREST =  "http://".$object->propertyById(9)->pivot->value."/rest-api";
        
        $ownerAlias = 'adminuser';
        $projectAlias = 'yadro';
        $token = $object->propertyById(45)->pivot->value;
        
        $urlMethod = "/project/$ownerAlias/$projectAlias/commits";

        $fullUrl = $urlREST.$urlMethod;

        $response = Http::withHeaders([
            'Authorization' => 'token '.$token,
            'Accept' => 'application/json'
        ])->get($fullUrl);

        // Convert the response body to a string
        $bodyString = $response->body();
        //var_dump($response);
        // Parse the JSON response
        $responseData = json_decode($bodyString, true);

        // Now you can work with the parsed data
        //var_dump($responseData);
        if(isset($responseData['_embedded']))
        {
            //var_dump($responseData['_embedded']);
            if(isset($responseData['_embedded']['commitList']))
            {
                $getLast = $object->propertyById(51)->pivot->value ?? null;
                //Получаем дату последнего комита
                $lastUpdated = Carbon::parse($getLast);

                $i = -1;
                foreach($responseData['_embedded']['commitList'] as $commit)
                {
                    if(is_null($getLast))
                    {
                        // Check if the pivot record exists
                        if($object->propertyById(51)===null)
                        {$object->propertys()->attach(51, ['value' => $commit['createdAt']]);}
                        else
                        {$object->propertys()->updateExistingPivot(51,['value'=>$commit['createdAt']]);}
                    }
                    //Получаем дату
                    $dateCreated = Carbon::parse($commit['createdAt']);
                    //var_dump($lastUpdated->diffInSeconds($dateCreated));
                    if($lastUpdated->diffInSeconds($dateCreated)>0)
                    {
                        echo "Увеличиваю счетчик\n";
                        $i++;
                    }
                }
                if($i>-1)
                {
                    echo "Запускаю обратный цикл и проверку в важных файлах\n";
                    for($i;$i>-1;$i--)
                    {
                        //dd($responseData['_embedded']['commitList'][$i]['hash']);
                        $urlMethod = "/project/$ownerAlias/$projectAlias/commit/".$responseData['_embedded']['commitList'][$i]['hash']."/file";
                        $fullUrl = $urlREST.$urlMethod;
                        $responseCommit = Http::withHeaders([
                            'Authorization' => 'token '.$token,
                            'Accept' => 'application/json'
                        ])->get($fullUrl);
                        $bodyStringCommit = $responseCommit->body();
                        $responseDataCommit = json_decode($bodyStringCommit, true);
                        var_dump($responseDataCommit);
                    }
                }
                else
                {
                    echo "Новых коммитов нет\n";
                }
            }
            else
            {
                echo "Отсуствует элемент commitList";
            }
        }
        else
        {
            echo "Отсуствует элемент _embedded";
        }
    }
}