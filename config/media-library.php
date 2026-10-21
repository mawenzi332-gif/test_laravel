<?php

return [
    'disk_name' => env('MEDIA_DISK', 'local'),
    'conversions_disk_name' => env('MEDIA_CONVERSIONS_DISK', 'local'),
    'queue_conversions_by_default' => false,
    'image_driver' => env('IMAGE_DRIVER', 'imagick'),
];
