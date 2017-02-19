<?php
/**
 * AppServiceProvider.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Providers;

use Illuminate\Support\ServiceProvider;
use URL;

/**
 * Class AppServiceProvider
 *
 * @package FireflyIII\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        // force root URL.
        $forcedUrl = env('APP_FORCE_ROOT', '');
        if (strlen(strval($forcedUrl)) > 0) {
            URL::forceRootUrl($forcedUrl);
        }

        // force https urls
        if (env('APP_FORCE_SSL', false)) {
            URL::forceSchema('https');
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
