<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. A "local" driver, as well as a variety of cloud
    | based drivers are available for your choosing. Just store away!
    |
    | Supported: "local", "s3", "qiniu"
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 'qiniu'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
        ],

        'qiniu' => [
            'driver'  => 'qiniu',
            'buckets' => [
                'image'=> [
                    'domain' => env('QINIU_IMAGE_DOMAIN'),
                    'name' => env('QINIU_IMAGE_NAME')
                ],
                #'file' => [
                #    'domain' => env('QINIU__FILE_DOMAIN'),
                #    'name' => env('QINIU_FILE_NAME')
                #]
            ],
            'access_key'=> env('QINIU_ACCESS_KEY'),  //AccessKey
            'secret_key'=> env('QINIU_SECRET_KEY'),  //SecretKey
        ]
    ],

];
