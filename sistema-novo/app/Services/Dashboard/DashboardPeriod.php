<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;

/**
 * Resolve a string de periodo escolhida no dashboard (Etapa 5 #27) em um
 * intervalo de datas concreto [start, end].
 */
class DashboardPeriod
{
    public const HOJE = 'hoje';

    public const ULTIMOS_7_DIAS = '7dias';

    public const ULTIMOS_30_DIAS = '30dias';

    public const MES_ATUAL = 'mes_atual';

    public const PERSONALIZADO = 'personalizado';

    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly string $label,
    ) {}

    public static function options(): array
    {
        return [
            self::HOJE => 'Hoje',
            self::ULTIMOS_7_DIAS => 'Últimos 7 dias',
            self::ULTIMOS_30_DIAS => 'Últimos 30 dias',
            self::MES_ATUAL => 'Mês atual',
            self::PERSONALIZADO => 'Personalizado',
        ];
    }

    public static function resolve(string $period, ?string $customStart = null, ?string $customEnd = null): self
    {
        return match ($period) {
            self::HOJE => new self(now()->startOfDay(), now()->endOfDay(), 'Hoje'),
            self::ULTIMOS_7_DIAS => new self(now()->copy()->subDays(6)->startOfDay(), now()->endOfDay(), 'Últimos 7 dias'),
            self::ULTIMOS_30_DIAS => new self(now()->copy()->subDays(29)->startOfDay(), now()->endOfDay(), 'Últimos 30 dias'),
            self::PERSONALIZADO => self::resolveCustom($customStart, $customEnd),
            default => new self(now()->startOfMonth(), now()->endOfDay(), 'Mês atual'),
        };
    }

    private static function resolveCustom(?string $customStart, ?string $customEnd): self
    {
        try {
            $start = $customStart ? Carbon::parse($customStart)->startOfDay() : now()->startOfMonth();
            $end = $customEnd ? Carbon::parse($customEnd)->endOfDay() : now()->endOfDay();

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }
        } catch (\Throwable) {
            $start = now()->startOfMonth();
            $end = now()->endOfDay();
        }

        return new self($start, $end, 'Personalizado');
    }
}
