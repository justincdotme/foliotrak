<?php

declare(strict_types=1);

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions())->addArguments(array_filter([
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
                $options
            )
        );
    }
}
