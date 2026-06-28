<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:prune-cook-description-images')
    ->weeklyOn(2)
    ->description('Delete unreferenced cook description images');
