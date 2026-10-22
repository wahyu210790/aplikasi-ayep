<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage Path
    |--------------------------------------------------------------------------
    |
    | Direktori privat tempat file JSON hasil backup:products disimpan.
    | Secara default berada di storage/app/private/backups (di luar folder public).
    |
    */

    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),

    /*
    |--------------------------------------------------------------------------
    | Maximum Backups to Keep
    |--------------------------------------------------------------------------
    |
    | Jumlah file backup terakhir yang disimpan sebelum file yang lebih lama
    | dihapus otomatis oleh perintah backup:products.
    |
    */

    'max_files' => 14,

];
