<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use App\Observers\MessageObserver;
use App\Observers\UpdateObserver;
use App\Observers\RequestObserver;
use App\Observers\AiEmbeddingsObserver;
use App\Models\Assistant\MessagesModel;
use App\Models\Socials\UpdatesModel;
use App\Models\Ai\AiRequest;
use App\Models\Ai\AiEmbeddings;



class EventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    public function boot()
    {
        parent::boot();

        // Явная регистрация после parent::boot()
        MessagesModel::observe(MessageObserver::class);
        UpdatesModel::observe(UpdateObserver::class);
        AiRequest::observe(RequestObserver::class);
        AiEmbeddings::observe(AiEmbeddingsObserver::class);
    }
}