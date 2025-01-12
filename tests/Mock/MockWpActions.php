<?php

declare(strict_types=1);

namespace MyParcelNL\WooCommerce\Tests\Mock;

use MyParcelNL\Pdk\Base\Support\Arr;
use MyParcelNL\Pdk\Base\Support\Collection;

final class MockWpActions implements StaticMockInterface
{
    /**
     * @var \MyParcelNL\Pdk\Base\Support\Collection
     */
    public static $actions;

    /**
     * @param  string          $tag
     * @param  callable|string $functionToAdd
     * @param  int             $priority
     * @param  int             $acceptedArgs
     *
     * @return void
     */
    public static function add(string $tag, $functionToAdd, int $priority = 10, int $acceptedArgs = 1): void
    {
        $existing = array_filter(Arr::wrap(self::get($tag)));

        self::$actions->put(
            $tag,
            array_merge($existing, [
                [
                    'function'     => $functionToAdd,
                    'priority'     => $priority,
                    'acceptedArgs' => $acceptedArgs,
                ],
            ])
        );
    }

    /**
     * @return \MyParcelNL\Pdk\Base\Support\Collection
     */
    public static function all(): Collection
    {
        return self::getActions();
    }

    /**
     * @template T of mixed
     * @param  string $tag
     * @param  T      $value
     * @param  mixed  ...$args
     *
     * @return T
     */
    public static function applyFilters(string $tag, $value, ...$args)
    {
        return self::execute($tag, $value, ...$args) ?? $value;
    }

    /**
     * @param  string $tag
     *
     * @return bool
     */
    public static function didAction(string $tag): bool
    {
        return self::getActions()
            ->has($tag);
    }

    /**
     * @param  string $tag
     * @param  mixed  ...$args
     *
     * @return mixed
     */
    public static function execute(string $tag, ...$args)
    {
        $actions = self::get($tag);
        $value   = null;

        foreach ($actions as $action) {
            $value = call_user_func_array($action['function'], $args);
        }

        if (! empty($actions)) {
            self::$actions->put($tag, []);
        }

        return $value;
    }

    /**
     * @param  string $tag
     *
     * @return array
     */
    public static function get(string $tag): array
    {
        return self::getActions()
            ->get($tag, []);
    }

    public static function reset(): void
    {
        self::$actions = new Collection();
    }

    public static function toArray(): array
    {
        return self::getActions()
            ->map(static function (array $actions) {
                return (new Collection(Arr::pluck($actions, 'function')))->map(static function ($function) {
                    if (is_array($function)) {
                        return implode('::', [get_class($function[0]), $function[1]]);
                    }

                    return $function;
                });
            })
            ->toArray();
    }

    /**
     * @return \MyParcelNL\Pdk\Base\Support\Collection
     */
    protected static function getActions(): Collection
    {
        if (null === self::$actions) {
            self::reset();
        }

        return self::$actions;
    }
}
