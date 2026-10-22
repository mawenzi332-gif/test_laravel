<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('comments', fn (): bool => true);
