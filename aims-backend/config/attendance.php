<?php

return [
    'use_server_time' => env('ATTENDANCE_USE_SERVER_TIME', true),
    'gps_online_seconds' => (int) env('ATTENDANCE_GPS_ONLINE_SECONDS', 30),
    'gps_stale_seconds' => (int) env('ATTENDANCE_GPS_STALE_SECONDS', 120),
    'gps_retention_hours' => (int) env('ATTENDANCE_GPS_RETENTION_HOURS', 24),
];
