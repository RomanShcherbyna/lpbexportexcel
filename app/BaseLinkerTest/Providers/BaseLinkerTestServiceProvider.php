<?php

namespace App\BaseLinkerTest\Providers;

use App\BaseLinkerTest\Services\BaseLinkerService;
use Illuminate\Support\ServiceProvider;

final class BaseLinkerTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BaseLinkerService::class, function () {
            return BaseLinkerService::fromConfig();
        });
    }
}

