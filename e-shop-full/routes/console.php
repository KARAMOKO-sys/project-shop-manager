<?php

use App\Services\Erp\OutboxPublisher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('erp:outbox:publish', function (OutboxPublisher $publisher) {
    $result = $publisher->publishPending();
    $this->info("ESB : {$result['published']} événement(s) publié(s), {$result['failed']} en échec.");
})->purpose('Relay pending business events from the outbox to the ESB');

Schedule::command('erp:outbox:publish')->everyMinute()->withoutOverlapping();
