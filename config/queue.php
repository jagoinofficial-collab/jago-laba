<?php
declare(strict_types=1);
return [
    // Describes the existing runners; does not install or enable a scheduler.
    'auto_sync_driver' => 'browser-pulse',
    'manual_background_driver' => 'windows-process',
    // Conservative Jago Laba concurrency policy, not an official API quota.
    'platform_concurrency' => ['Shopee'=>2,'TikTok Shop'=>2],
    'worker_directory' => dirname(__DIR__) . '/bin/workers',
    'interval_seconds' => ['status' => 300, 'new' => 60, 'unpaid' => 60, 'finance' => 300],
];
