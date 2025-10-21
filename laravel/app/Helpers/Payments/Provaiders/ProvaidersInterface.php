<?php
namespace App\Helpers\Payments\Provaiders;

interface ProvaidersInterface
{

//Создать платежку
public function createPayment($payment);

//Удалить платежку
public function deletePayemnt($payemnt);

//Валидация суммы
function validate_summ($summ);

}