<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class InspectionOperatingHoursService
{
    public function startHour(): int
    {
        return config('inspection.operating_start_hour', 0);
    }

    public function endHour(): int
    {
        return config('inspection.operating_end_hour', 24);
    }

    public function isWithinOperatingHours(Carbon $hourSlot): bool
    {
        $hour = (int) $hourSlot->format('G');

        return $hour >= $this->startHour() && $hour < $this->endHour();
    }

    public function isOperatingNow(?Carbon $asOf = null): bool
    {
        $asOf ??= now();

        return $this->isWithinOperatingHours($asOf->copy()->startOfHour());
    }

    public function firstHourSlotForDay(Carbon $date): Carbon
    {
        return $date->copy()->startOfDay()->addHours($this->startHour());
    }

    public function lastOperatingHourSlotForDay(Carbon $date): Carbon
    {
        return $date->copy()->startOfDay()->addHours(max($this->startHour(), $this->endHour() - 1));
    }

    public function lastDueHourSlot(Carbon $date, Carbon $asOf): Carbon
    {
        $lastOperating = $this->lastOperatingHourSlotForDay($date);

        if (! $date->isSameDay($asOf)) {
            return $lastOperating;
        }

        $currentHour = $asOf->copy()->startOfHour();

        if ((int) $currentHour->format('G') < $this->startHour()) {
            return $this->firstHourSlotForDay($date)->subHour();
        }

        if ((int) $currentHour->format('G') >= $this->endHour()) {
            return $lastOperating;
        }

        return $currentHour->lt($lastOperating) ? $currentHour : $lastOperating;
    }

    public function formattedWindow(): string
    {
        return sprintf(
            '%02d:00 – %02d:00',
            $this->startHour(),
            $this->endHour() % 24 === 0 ? 24 : $this->endHour(),
        );
    }
}
