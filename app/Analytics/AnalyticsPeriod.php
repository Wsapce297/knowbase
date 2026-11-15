<?php

namespace App\Analytics;

use Carbon\CarbonImmutable;

/**
 * The time ranges the analytics page offers. "Last 7 days" means today plus
 * the 6 days before it, counted in the viewer's time zone.
 */
enum AnalyticsPeriod: string
{
    case Last7Days = '7d';
    case Last30Days = '30d';
    case Last90Days = '90d';

    public const DEFAULT = self::Last30Days;

    /**
     * The period from a query string value; anything unknown gives the default.
     */
    public static function fromInput(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::DEFAULT;
    }

    public function days(): int
    {
        return match ($this) {
            self::Last7Days => 7,
            self::Last30Days => 30,
            self::Last90Days => 90,
        };
    }

    public function label(): string
    {
        return "Last {$this->days()} days";
    }

    /**
     * Midnight at the start of the first day, in $timezone.
     */
    public function start(CarbonImmutable $now, string $timezone): CarbonImmutable
    {
        return $now->setTimezone($timezone)->startOfDay()->subDays($this->days() - 1);
    }
}
