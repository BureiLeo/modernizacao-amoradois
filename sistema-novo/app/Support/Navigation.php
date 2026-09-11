<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Resolve os itens de config/navigation.php em algo pronto para o Blade
 * renderizar: cada item ganha 'enabled', 'href' e 'active', calculados a
 * partir das rotas realmente registradas (Etapa 5 #54).
 */
class Navigation
{
    /**
     * @param  array<int, array{key: string, label: string, icon: string, route: string}>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function resolve(array $items): array
    {
        return array_map(function (array $item) {
            $enabled = ! empty($item['route']) && Route::has($item['route']);

            return array_merge($item, [
                'enabled' => $enabled,
                'href' => $enabled ? route($item['route']) : null,
                'active' => $enabled && request()->routeIs($item['route']),
            ]);
        }, $items);
    }

    public static function primary(): array
    {
        return static::resolve(config('navigation.primary'));
    }

    public static function secondary(): array
    {
        return static::resolve(config('navigation.secondary'));
    }

    /**
     * Itens da bottom nav mobile, na ordem definida em 'bottom_nav'.
     */
    public static function bottom(): array
    {
        $primary = collect(static::primary())->keyBy('key');

        return collect(config('navigation.bottom_nav'))
            ->map(fn (string $key) => $primary->get($key))
            ->filter()
            ->values()
            ->all();
    }

    public static function primaryAction(): array
    {
        $action = config('navigation.primary_action');
        $enabled = ! empty($action['route']) && Route::has($action['route']);

        return array_merge($action, [
            'enabled' => $enabled,
            'href' => $enabled ? route($action['route']) : null,
        ]);
    }
}
