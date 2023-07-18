<?php

declare(strict_types=1);

/**
 * Global Pest test configuration.
 *
 * @see https://pestphp.com/docs/underlying-test-case#testspestphp
 */

use MyParcelNL\Pdk\Tests\Uses\ClearContainerCache;
use function MyParcelNL\Pdk\Tests\usesShared;

require __DIR__ . '/../vendor/myparcelnl/pdk/tests/Pest.php';
require __DIR__ . '/mock_class_map.php';

usesShared(new ClearContainerCache())->in(__DIR__);
