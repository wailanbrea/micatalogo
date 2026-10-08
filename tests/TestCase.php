<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $sqliteInMemory = $app->environment('testing')
            && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:';
        $isolatedMysql = $app->environment('testing')
            && env('QA_ALLOW_MYSQL') === '1'
            && config('database.default') === 'mysql'
            && str_starts_with((string) config('database.connections.mysql.database'), 'micatalogo_qa_');

        if (! $sqliteInMemory && ! $isolatedMysql) {
            throw new \RuntimeException(
                'Las pruebas requieren SQLite en memoria o una base MySQL QA aislada con QA_ALLOW_MYSQL=1.'
            );
        }

        config(['bspos.android_update.minimum_supported_version_code' => 0]);

        return $app;
    }
}
