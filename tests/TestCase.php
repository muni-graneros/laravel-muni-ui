<?php

namespace Muni\Ui\Tests;

use Muni\Ui\MuniUiServiceProvider;
use Orchestra\Testbench\TestCase as Base;

abstract class TestCase extends Base
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [MuniUiServiceProvider::class];
    }
}
