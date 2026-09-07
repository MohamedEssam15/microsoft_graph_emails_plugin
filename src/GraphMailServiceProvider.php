<?php

namespace GraphMail\LaravelGraphMail;

use GraphMail\LaravelGraphMail\Console\TestGraphMailCommand;
use GraphMail\LaravelGraphMail\Services\MicrosoftGraphTokenService;
use GraphMail\LaravelGraphMail\Transport\GraphTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class GraphMailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/graph-mail.php', 'graph-mail');

        $this->app->singleton(MicrosoftGraphTokenService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/graph-mail.php' => config_path('graph-mail.php'),
            ], 'graph-mail-config');

            $this->commands([
                TestGraphMailCommand::class,
            ]);
        }

        Mail::extend('graph', function () {
            return new GraphTransport(
                $this->app->make(MicrosoftGraphTokenService::class),
                config('graph-mail.default_sender'),
                (bool) config('graph-mail.save_to_sent_items', true),
            );
        });
    }
}
