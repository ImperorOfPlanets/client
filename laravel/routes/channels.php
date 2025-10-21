<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('chat.{channel_id}', function ($user_id,$channel_id) {
    try
    {
        $sessionID = session()->getId();

        // Инвертируем полную строку сессии
        $reversedSessionID = strrev($sessionID);

        if ($channel_id === $reversedSessionID)
        {
            return true;
        }
    }
    catch (\Exception $e)
    {
        Log::error('Decryption error: ' . $e->getMessage());
        return false;
    }

    return false;
});

Broadcast::channel('public-new', function () {
    return true; // Публичный канал доступен для всех
});