<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\DuskTestCase;

pest()->extend(DuskTestCase::class)->use(DatabaseTruncation::class);
