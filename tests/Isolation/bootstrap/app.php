<?php

use Tests\Isolation\IsolatedApplication;

require_once dirname(__DIR__, 2).'/bootstrap.php';

return IsolatedApplication::make();
