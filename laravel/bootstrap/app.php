<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Helpers\Logs\Logs as Logator;

use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            '/gateway',
            '/broadcasting/auth',
            '/dns/update'
        ]);
        $middleware->preventRequestsDuringMaintenance(except: [
            '/gateway',
            '/broadcasting/auth'
        ]);
        $middleware->web([ // Добавляем ваш middleware в группу web
            \App\Http\Middleware\Locale::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Illuminate\Queue\MaxAttemptsExceededException $e)
        {
            $logator = new Logator();
            $logator->setType('danger');
            $logator->setText('MaxAttemptsExceededException: '.$e->getMessage().' uuid: '.$e->job->uuid());
            $logator->write();
            echo 'MaxAttemptsExceededException: '.$e->getMessage();
            //ID - JOB $e->job->getJobId()
            dd($e->job->uuid());
            //$logator->write();
        });
        $exceptions->shouldRenderJsonWhen(function (Request $request){
            if ($request->is('control/*')) {
                return true;
            }
        });
    })->create();
