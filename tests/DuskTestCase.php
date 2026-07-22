<?php

declare(strict_types=1);

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\TestCase as BaseTestCase;
use RuntimeException;

abstract class DuskTestCase extends BaseTestCase
{
    private const DUSK_DATABASE = 'foliotrak_dusk';

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDuskDatabase();
    }

    /**
     * @return RemoteWebDriver
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(array_filter([
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--ignore-certificate-errors',
            env('DUSK_HEADLESS_DISABLED') ? null : '--headless=new',
        ]));

        return RemoteWebDriver::create(
            env('DUSK_DRIVER_URL', 'http://selenium:4444'),
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options,
            ),
        );
    }

    /** @return void */
    private function assertDuskDatabase(): void
    {
        $current = config('database.connections.mysql.database');

        if ($current !== self::DUSK_DATABASE) {
            throw new RuntimeException(
                'Dusk tests must run against "' . self::DUSK_DATABASE . '" '
                . 'but the connection targets "' . $current . '".',
            );
        }
    }
}
