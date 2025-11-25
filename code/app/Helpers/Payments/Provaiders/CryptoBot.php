<?php
namespace App\Helpers\Payments\Provaiders;

use App\Models\Payments\ProvaidersModel;

use Klev\CryptoPayApi\Methods\CreateInvoice;

use \Klev\CryptoPayApi\CryptoPay;

class CryptoBot implements ProvaidersInterface
{
	//Объект
	public $objectID=40;

	public $object=null;

	//Настройки сайта
	public $YOUR_APP_TOKEN=null;

	//Подключение API
	public $api = null;

	//Опубликовать в группе
	public function createPayment($payment)
	{
		/*
		//Получаем провайдера
		$this->object =  ProvaidersModel::find($this->objectID);

		//Получаем токен
		$this->YOUR_APP_TOKEN = $this->object->propertyById(45)->pivot->value;

		//Проверяем подключение
		if(is_null($this->api))
		{
			//Проверяем на тестовый режим
			$isTest = $this->object->propertyById(127)->pivot->value;

			//Если тестовый режим
			if(filter_var($isTest, FILTER_VALIDATE_BOOLEAN))
			{
				$this->api = new CryptoPay($this->YOUR_APP_TOKEN,true);
			}
			//Рабочий режим
			else
			{
				$this->api = new CryptoPay($this->YOUR_APP_TOKEN);
			}
		}

		//$result = $this->api->getMe();
		*/
		//Получаем сумму
		$summ = $payment->propertyById(120)->pivot->value;

		//Получаем ID валюты в нащей системе
		$currencyID = $payment->propertyById(121)->pivot->value;

		//Так как в API указывается валюта названием и она совпадает то получаем название валюты
		$currency = \App\Models\Payments\CurrencysModel::find($currencyID)->propertyById(1)->pivot->value;


					/*   СОЗДАЕМ ПЛАТЕЖ   */
		$summForQuery = $this->validate_summ($summ);
		$invoice = new CreateInvoice($currency,$this->validate_summ($summForQuery));

		//currency_type (String) - Необязательно . Тип цены может быть «crypto» или «fiat». По умолчанию используется крипто .
		$invoice->currency_type = 'crypto';

		//asset (String) - Необязательно . Требуется, если тип_валюты имеет значение «крипто». Буквенный код криптовалюты. Поддерживаемые активы: «USDT», «TON», «BTC», «ETH», «LTC», «BNB», «TRX» и «USDC».
		//$invoice->asset = ;

		//fiat (Strting) - Необязательно . Требуется, если тип валюты имеет значение «фиат». Код фиатной валюты. Поддерживаемые фиатные валюты: «USD», «EUR», «RUB», «BYN», «UAH», «GBP», «CNY», «KZT», «UZS», «GEL», «TRY», «AMD». », «THB», «INR», «BRL», «IDR», «AZN», «AED», «PLN» и «ILS».
		//$invoice->fiat = ;

		//accepted_assets (Строка)   - Необязательно . Список буквенных кодов криптовалют, разделенных запятой. Активы, которые можно использовать для оплаты счета. Доступно только в том случае, если тип валюты имеет значение «фиат». Поддерживаемые активы: «USDT», «TON», «BTC», «ETH», «LTC», «BNB», «TRX» и «USDC» (и «JET» для тестовой сети). По умолчанию для всех валют.
		//$invoice->accepted_assets = ;

		//amount (String) - Сумма счета в плавающем состоянии. Например:125.50
		$invoice->amount = $summForQuery;

		//desc (строка) - Необязательно. Описание счета-фактуры. Пользователь увидит это описание при оплате счета. До 1024 символов.
		$invoice->desc = '';

		//hidden_message (Строка) - Необязательно. Текст сообщения, которое будет отправлено пользователю после оплаты счета. До 2048 символов.
		$invoice->hidden_message = $this;

		//pay_btn_name (Строка) - Необязательно. Метка кнопки, которая будет представлена ​​пользователю после оплаты счета. Поддерживаемые имена:
			//viewItem – «Просмотр элемента» 
				//openChannel– «Просмотр канала» 
				//openBot– «Открыть бот» 
				//callback– «Возврат»
		//$invoice->pay_btn_name = '';

		//pay_btn_url (Строка) - Необязательно. Требуется, если указано pay_btn_name . URL-адрес, открываемый с помощью кнопки, которая будет представлена ​​пользователю после оплаты счета. Вы можете установить любую ссылку обратного вызова (например, ссылку на успех или ссылку на домашнюю страницу). Начинается с https или http .
		$invoice->pay_btn_url = '';

		//payload (строка) - Необязательно. Любые данные, которые вы хотите прикрепить к счету (например, идентификатор пользователя, идентификатор платежа и т. д.). До 4кб.
		$invoice->payload = '';

		//allow_comments (логическое значение) - Необязательно. Разрешить пользователю добавлять комментарий к платежу. По умолчанию true .
		$invoice->allow_comments = '';

		//allow_anonymous (логическое значение) - Необязательно. Разрешить пользователю оплатить счет анонимно. По умолчанию true .
		$invoice->allow_anonymous = '';

		//expires_in (число) - Необязательно. Вы можете установить срок оплаты счета в секундах. Принимаются значения от 1 до 2678400.
		$invoice->expires_in = $payment->propertyById();

		$invoice->allow_anonymous = false;
		$invoice->allow_comments = false;
		$invoice->paid_btn_name = 'openChannel';
		$invoice->paid_btn_url = 'https://t.me/your-channel-link';
		$invoice->description = 'Pay and go)';
		$invoice->hidden_message = 'Any secret text';
	}

	//Валидация суммы
	function validate_summ($summ)
	{
		//Заменяем запятые на точку.
		$summ = (float)str_replace(',', '.',$summ);
		return $summ;
	}

	public function deletePayemnt($payemnt){}
}