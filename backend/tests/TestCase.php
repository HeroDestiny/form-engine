<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        if (($_SERVER['TEST_DB_PROFILE'] ?? '') === 'isolated-postgres') {
            $testHost = getenv('TEST_PG_HOST') ?: 'postgres-test';
            $_ENV['DB_HOST'] = $_SERVER['DB_HOST'] = $testHost;
            putenv('DB_HOST='.$testHost);
        }
        $app = parent::createApplication();
        $config = $app->make('config');

        $sqlite = $config->get('database.default') === 'sqlite' && $config->get('database.connections.sqlite.database') === ':memory:'
            && empty($config->get('database.connections.sqlite.url'));
        $postgres = ($_SERVER['TEST_DB_PROFILE'] ?? '') === 'isolated-postgres'
            && $config->get('database.default') === 'pgsql'
            && $config->get('database.connections.pgsql.database') === 'form_engine_testing'
            && $config->get('database.connections.pgsql.username') === 'form_engine_testing'
            && in_array($config->get('database.connections.pgsql.host'), ['postgres-test', '127.0.0.1'], true)
            && empty($config->get('database.connections.pgsql.url'));

        if (! $app->environment('testing') || (! $sqlite && ! $postgres)) {
            throw new LogicException('A suíte exige SQLite em memória ou o perfil PostgreSQL exclusivo form_engine_testing. Nenhuma migration de teste deve atingir o banco local.');
        }

        return $app;
    }
}
