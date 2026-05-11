<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\UserRegistered;
use App\Listeners\SendWelcomeMail;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> */
    protected $listen = [
        UserRegistered::class => [
            SendWelcomeMail::class,
        ],
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
