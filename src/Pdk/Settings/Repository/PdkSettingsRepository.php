<?php

declare(strict_types=1);

namespace MyParcelNL\WooCommerce\Pdk\Settings\Repository;

use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Repository\AbstractSettingsRepository;
use MyParcelNL\Sdk\src\Support\Str;

class PdkSettingsRepository extends AbstractSettingsRepository
{
    /**
     * @param  string $namespace
     *
     * @return mixed
     */
    public function getGroup(string $namespace)
    {
        return $this->retrieve($namespace, function () use ($namespace) {
            return get_option($this->getOptionName($namespace), null);
        });
    }

    /**
     * @param  string $key
     * @param  mixed  $value
     *
     * @return void
     */
    protected function store(string $key, $value): void
    {
        update_option($this->getOptionName($key), $value);
    }

    /**
     * @param  string $key
     *
     * @return string
     */
    private function getOptionName(string $key): string
    {
        return Pdk::get('settingKeyPrefix') . Str::snake($key);
    }
}