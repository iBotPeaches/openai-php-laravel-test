<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenAI;
use OpenAI\Client;
use OpenAI\Contracts\ClientContract;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientContract::class, static function (): Client {
            return OpenAI::factory()
                ->withApiKey(config('openai.api_key'))
                ->withOrganization(config('openai.organization'))
                ->withHttpClient(new \Swis\Laravel\Bridge\PsrHttpClient\Client)
                ->make();
        });

        $this->app->alias(ClientContract::class, 'openai');
        $this->app->alias(ClientContract::class, Client::class);
    }

    public function boot(): void
    {
        //
    }
}
