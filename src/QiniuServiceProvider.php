<?php

namespace Espier\Qiniu;

use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Storage;
use Espier\Qiniu\Adapter as QiuniuAdapter;

class QiniuServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('qiniu', function($app, $config) {
            $client = new QiuniuAdapter(
                $config['access_key'],
                $config['secret_key'],
                $config['buckets']
            );

            return new Filesystem($client);
        });
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
