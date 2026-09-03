<?php

$defaultDisk = env('FILESYSTEM_DISK', 'local');
$usesS3CompatibleStorage = $defaultDisk === 's3';
$objectStorageProvider = env('OBJECT_STORAGE_PROVIDER', 'aws');

$objectStorage = $objectStorageProvider === 'r2'
    ? [
        'key' => env('R2_ACCESS_KEY_ID'),
        'secret' => env('R2_SECRET_ACCESS_KEY'),
        'token' => null,
        'region' => env('R2_DEFAULT_REGION', 'auto'),
        'private_bucket' => env('R2_BUCKET'),
        'private_url' => env('R2_URL'),
        'public_bucket' => env('R2_PUBLIC_BUCKET'),
        'public_url' => env('R2_PUBLIC_URL'),
        'endpoint' => env('R2_ENDPOINT'),
        'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
    ]
    : [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'token' => env('AWS_SESSION_TOKEN'),
        'region' => env('AWS_DEFAULT_REGION'),
        'private_bucket' => env('AWS_BUCKET'),
        'private_url' => env('AWS_URL'),
        'public_bucket' => env('AWS_PUBLIC_BUCKET'),
        'public_url' => env('AWS_PUBLIC_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    ];

/*
 * Both backends are always configured so either side is addressable no matter
 * which one is active. "local"/"s3" hold private files, "local_public"/
 * "s3_public" hold web-served assets, and "public" aliases whichever public
 * backend FILESYSTEM_DISK selects. The storage:sync command reads and writes
 * the inactive side by its explicit name to move files between backends.
 */
$privateLocalDisk = [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => true,
    'throw' => false,
    'report' => false,
];

$privateS3Disk = [
    'driver' => 's3',
    'key' => $objectStorage['key'],
    'secret' => $objectStorage['secret'],
    'token' => $objectStorage['token'],
    'region' => $objectStorage['region'],
    'bucket' => $objectStorage['private_bucket'],
    'url' => $objectStorage['private_url'],
    'endpoint' => $objectStorage['endpoint'],
    'use_path_style_endpoint' => $objectStorage['use_path_style_endpoint'],
    'throw' => false,
    'report' => false,
];

$publicLocalDisk = [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => '/storage',
    'visibility' => 'public',
    'throw' => false,
    'report' => false,
];

/*
 * No 'visibility' key on purpose: setting it makes Flysystem send an object ACL
 * on every write, which AWS buckets with ACLs disabled and Cloudflare R2 both
 * reject. Public reads come from a bucket policy, a CDN, or an R2 custom domain
 * fronted by the selected provider's public URL setting.
 */
$publicS3Disk = [
    'driver' => 's3',
    'key' => $objectStorage['key'],
    'secret' => $objectStorage['secret'],
    'token' => $objectStorage['token'],
    'region' => $objectStorage['region'],
    'bucket' => $objectStorage['public_bucket'],
    'url' => $objectStorage['public_url'],
    'endpoint' => $objectStorage['endpoint'],
    'use_path_style_endpoint' => $objectStorage['use_path_style_endpoint'],
    'throw' => false,
    'report' => false,
];

return [

    'object_storage_provider' => $objectStorageProvider,

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Set FILESYSTEM_DISK to "local" for single-server storage or "s3" for AWS
    | S3 and compatible services such as Cloudflare R2. This picks where private
    | uploads live and which backend the "public" disk points at.
    |
    */

    'default' => $defaultDisk,

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => $privateLocalDisk,

        's3' => $privateS3Disk,

        'local_public' => $publicLocalDisk,

        's3_public' => $publicS3Disk,

        'public' => $usesS3CompatibleStorage ? $publicS3Disk : $publicLocalDisk,

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
