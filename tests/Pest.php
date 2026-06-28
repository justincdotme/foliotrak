<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\DuskTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(DuskTestCase::class)->use(DatabaseTruncation::class)->in('Browser');
