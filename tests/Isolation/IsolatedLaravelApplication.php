<?php

namespace Tests\Isolation;

use Illuminate\Foundation\Application;

final class IsolatedLaravelApplication extends Application
{
    public function getNamespace(): string
    {
        return 'App\\';
    }
}
