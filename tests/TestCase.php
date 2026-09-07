<?php

namespace GraphMail\LaravelGraphMail\Tests;

use GraphMail\LaravelGraphMail\GraphMailServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GraphMailServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('graph-mail.tenant_id', 'fake-tenant-id');
        $app['config']->set('graph-mail.client_id', 'fake-client-id');
        $app['config']->set('graph-mail.client_secret', 'fake-client-secret');
        $app['config']->set('graph-mail.default_sender', 'sender@example.com');
        $app['config']->set('graph-mail.token_cache_key', 'ms_graph_token_test');

        $app['config']->set('mail.default', 'graph');
        $app['config']->set('mail.mailers.graph', ['transport' => 'graph']);

        $app['config']->set('cache.default', 'array');
    }
}
