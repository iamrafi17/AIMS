<?php

use Illuminate\Foundation\Inspiring;
use App\Models\StudentLiveLocation;
use App\Support\DeploymentActivation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => StudentLiveLocation::where('captured_at', '<', now()->subHours(config('attendance.gps_retention_hours', 24)))->delete())
    ->daily()->name('prune-student-live-locations');

Schedule::call(fn () => DeploymentActivation::activateDue())
    ->dailyAt('00:05')->name('activate-approved-deployments');
