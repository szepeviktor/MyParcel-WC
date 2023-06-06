<?php

declare(strict_types=1);

namespace MyParcelNL\WooCommerce\Tests\Mock;

use MyParcelNL\WooCommerce\Pdk\WcPdkBootstrapper;

final class MockWcPdkBootstrapper extends WcPdkBootstrapper
{
    /**
     * @var array
     */
    private static $config;

    /**
     * @return void
     */
    public static function reset(): void
    {
        self::setConfig([]);
        self::$initialized = false;
    }

    /**
     * @param  array $config
     *
     * @return void
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * @param  string $name
     * @param  string $title
     * @param  string $version
     * @param  string $path
     * @param  string $url
     *
     * @return array
     */
    protected function getAdditionalConfig(
        string $name,
        string $title,
        string $version,
        string $path,
        string $url
    ): array {
        return array_replace(parent::getAdditionalConfig($name, $title, $version, $path, $url), self::$config);
    }
}
