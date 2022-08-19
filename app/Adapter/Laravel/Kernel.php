<?php

namespace Gino\Jobs\Adapter\Laravel;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


class Kernel extends ConsoleKernel {

    /**
     * Bootstrap the application for artisan commands.
     *
     * @return void
     */
    public function bootstrap() {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }

        $this->app->loadDeferredProviders();

    }

}