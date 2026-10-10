<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app->make('config');

        if (! $app->environment('testing') || $config->get('database.default') !== 'sqlite' || $config->get('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('A suíte deve usar APP_ENV=testing e SQLite em memória. Nenhuma migration de teste deve atingir o banco local.');
        }

        return $app;
    }
}
