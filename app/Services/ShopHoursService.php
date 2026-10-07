<?php

namespace App\Services;

use Carbon\CarbonInterface;

class ShopHoursService
{
    /** @var array<string, string> */
    public const DAYS = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo',
    ];

    /** @return array<string, array{open: string, close: string, all_day: bool, closed: bool}> */
    public function defaults(): array
    {
        return collect(array_keys(self::DAYS))
            ->mapWithKeys(fn (string $day): array => [$day => [
                'open' => $day === 'sunday' ? null : '09:00',
                'close' => $day === 'sunday' ? null : '18:00',
                'all_day' => false,
                'closed' => $day === 'sunday',
            ]])
            ->all();
    }

    /** @return array<string, array{open: ?string, close: ?string, all_day: bool, closed: bool}> */
    public function forForm(?array $hours): array
    {
        $defaults = $this->defaults();
        $hours ??= [];

        return collect($defaults)->mapWithKeys(function (array $default, string $day) use ($hours): array {
            $value = is_array($hours[$day] ?? null) ? $hours[$day] : [];

            return [$day => [
                'open' => $value['open'] ?? $default['open'],
                'close' => $value['close'] ?? $default['close'],
                'all_day' => filter_var($value['all_day'] ?? $default['all_day'], FILTER_VALIDATE_BOOLEAN),
                'closed' => filter_var($value['closed'] ?? $default['closed'], FILTER_VALIDATE_BOOLEAN),
            ]];
        })->all();
    }

    /** @return array<string, array{open: ?string, close: ?string, all_day: bool, closed: bool}> */
    public function normalize(?array $hours): array
    {
        return collect($this->forForm($hours))->mapWithKeys(function (array $value, string $day): array {
            $closed = (bool) $value['closed'];
            $allDay = ! $closed && (bool) $value['all_day'];

            return [$day => [
                'open' => $closed || $allDay ? null : $value['open'],
                'close' => $closed || $allDay ? null : $value['close'],
                'all_day' => $allDay,
                'closed' => $closed,
            ]];
        })->all();
    }

    public function isConfigured(?array $hours): bool
    {
        return is_array($hours) && collect(array_keys(self::DAYS))->every(fn (string $day): bool => array_key_exists($day, $hours));
    }

    /** @return array{open: bool, label: string, today: string}|null */
    public function currentStatus(?array $hours, ?CarbonInterface $now = null): ?array
    {
        if (! $this->isConfigured($hours)) {
            return null;
        }

        $now ??= now();
        $day = strtolower($now->englishDayOfWeek);
        $value = $this->normalize($hours)[$day] ?? null;

        if (! $value || $value['closed']) {
            return ['open' => false, 'label' => 'Cerrado hoy', 'today' => self::DAYS[$day] ?? 'Hoy'];
        }

        if ($value['all_day']) {
            return ['open' => true, 'label' => 'Abierto hoy · 24 horas', 'today' => self::DAYS[$day] ?? 'Hoy'];
        }

        $current = $now->format('H:i');
        $isOpen = $value['open'] !== null && $value['close'] !== null
            && $current >= $value['open']
            && $current < $value['close'];

        return [
            'open' => $isOpen,
            'label' => $isOpen ? "Abierto ahora · hasta {$value['close']}" : "Cerrado ahora · abre {$value['open']}",
            'today' => self::DAYS[$day] ?? 'Hoy',
        ];
    }
}
