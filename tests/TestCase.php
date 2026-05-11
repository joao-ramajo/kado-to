<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
            'logging.channels.auth.driver' => 'null',
            'logging.channels.dashboard.driver' => 'null',
            'logging.channels.expense.driver' => 'null',
            'logging.channels.category.driver' => 'null',
            'logging.channels.source.driver' => 'null',
            'logging.channels.xlsx.driver' => 'null',
            'logging.channels.import.driver' => 'null',
            'logging.channels.export.driver' => 'null',
            'logging.channels.mail.driver' => 'null',
        ]);

        $this->artisan('migrate');
    }
}
