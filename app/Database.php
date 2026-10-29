<?php
declare(strict_types=1);

// Compatibility adapter for scripts and integrations that still include app/Database.php.
require_once (dirname(__DIR__, 1) . '/app/Core/Database/Database.php');
