<?php
namespace App\Helpers\Socials;

interface SocialInterface
{
    //Отправить сообщение
    public function sendMessage($chat_id,$text,$params=null);

    //Проверить проверить сообщение в группе или боту
    public function isGroup($params);

    //Опубликовать пост
    public function publishPost($params);

    // Проверяет, может ли соцсеть получить длительность голосового сообщения без скачивания
    public function checkGetVoiceMessageDuration(array $params): bool;

    // Проверяет, может ли соцсеть получить размер голосового сообщения без скачивания
    public function checkGetVoiceMessageFileSize(array $params): bool;

    // Получить длительность голосового сообщения в секундах (если доступна)
    public function getVoiceMessageDuration(array $params): ?int;

    // Получить размер голосового сообщения в байтах (если доступен)
    public function getVoiceMessageFileSize(array $params): ?int;

    //Получить голосовое сообщение
    public function getVoiceMessage($params);

    //Функция проверки установки
    public function checkInstall();

    //Проверяет поддерживает ли редактирование сообщений
    public function checkEditMessage();

    //Проверяет поддерживает ли удаление сообщений
    public function checkDeleteMessage();

    //Редактировать сообщение
    public function editMessage($chatId, $messageId, $text,$params);

    //Удалить сообщение
    public function deleteMessage($chatId, $messageId, $params);

    //Проверяет выполненые стартовая инициализация, если нет то в ней же и описать
    public function checkDefaultinitialized();

    //Обратать результат запроса и вернуть важные данные - Отправка сообщения
	public function processResultSendMessage($result);

    //Обратать результат запроса и вернуть важные данные - Удаление сообщения
	public function processResultDeleteMessage($result);

    //Обратать результат запроса и вернуть важные данные  - Редактирование сообщения
	public function processResultEditMessage($result);

    //Добавить обновление в таблицу updates
	//public function setUpdate($update);

    //Обработать сообщение
    public function processUpdate(array $updateData): ?array;

    //Проверякекм можем ли мы получить дату создания сообщения
    public function checkMessageCreatedDate(array $messageInfo): bool;

    //Проверякекм можем ли мы получить дату обновления или изменения
    public function checkMessageUpdatedDate(array $messageInfo): bool;  
}