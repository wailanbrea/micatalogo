<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Las pruebas solo pueden ejecutarse con SQLite en memoria.');
        }

        config(['bspos.android_update.minimum_supported_version_code' => 0]);

        return $app;
    }
}
