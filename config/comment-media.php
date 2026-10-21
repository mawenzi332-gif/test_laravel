<?php

return [
    'image_max_bytes' => (int) env('MEDIA_IMAGE_MAX_MB', 10) * 1024 * 1024,
    'text_max_bytes' => 100 * 1024,
    'max_attachments' => 3,
    'preview_width' => 320,
    'preview_height' => 240,
];
