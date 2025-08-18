<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimeSlotHelper
{
    /**
     * Convert date string and time string to Carbon datetime
     *
     * @param  string  $date  "2025-08-20"
     * @param  string  $time  "14:00:00" or "14:00"
     */
    public static function convertToDateTime(string $date, string $time): Carbon
    {
        // Ensure time has seconds
        if (substr_count($time, ':') === 1) {
            $time .= ':00';
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$time, config('app.timezone'));
    }

    /**
     * Get the end datetime for a time slot, handling cross-midnight cases
     *
     * @param  string  $endTime  "15:00:00" or "01:00:00"
     */
    public static function getSlotEndDateTime(Carbon $startDateTime, string $endTime): Carbon
    {
        // Ensure time has seconds
        if (substr_count($endTime, ':') === 1) {
            $endTime .= ':00';
        }

        $startTime = $startDateTime->format('H:i:s');
        $endDateTime = $startDateTime->copy()->setTimeFromTimeString($endTime);

        // If end time is less than or equal to start time, it means next day
        if ($endTime <= $startTime) {
            $endDateTime->addDay();
        }

        return $endDateTime;
    }

    /**
     * Get all dates that a time slot booking affects
     * For day-use: only the booking date(s)
     * For overnight: all dates from start to end
     *
     * @param  object  $timeSlot
     * @return array of date strings
     */
    public static function getSlotsDateRange($timeSlot, string $startDate, ?string $endDate = null): array
    {
        $dates = [];
        $current = Carbon::createFromFormat('Y-m-d', $startDate);

        if ($timeSlot->is_overnight && $endDate) {
            // Overnight booking: include all dates from start to end
            $end = Carbon::createFromFormat('Y-m-d', $endDate);

            while ($current->lte($end)) {
                $dates[] = $current->format('Y-m-d');
                $current->addDay();
            }
        } else {
            // Day-use booking: only the start date
            $dates[] = $startDate;

            // If time slot crosses midnight, also include next day
            $startDateTime = self::convertToDateTime($startDate, $timeSlot->start_time);
            $endDateTime = self::getSlotEndDateTime($startDateTime, $timeSlot->end_time);

            if ($endDateTime->format('Y-m-d') !== $startDate) {
                $dates[] = $endDateTime->format('Y-m-d');
            }
        }

        return $dates;
    }

    /**
     * Check if time slots are consecutive for day-use bookings
     */
    public static function isConsecutive(Collection $timeSlots): bool
    {
        if ($timeSlots->count() <= 1) {
            return true;
        }

        // Sort time slots by start time
        $sorted = $timeSlots->sortBy('start_time')->values();

        for ($i = 0; $i < $sorted->count() - 1; $i++) {
            $current = $sorted[$i];
            $next = $sorted[$i + 1];

            // Create sample datetime for comparison
            $sampleDate = '2025-01-01';
            $currentStart = self::convertToDateTime($sampleDate, $current->start_time);
            $currentEnd = self::getSlotEndDateTime($currentStart, $current->end_time);
            $nextStart = self::convertToDateTime($sampleDate, $next->start_time);

            // Handle cross-midnight case for next start time
            if ($nextStart->format('H:i:s') < $currentStart->format('H:i:s')) {
                $nextStart->addDay();
            }

            // Check if current slot ends exactly when next slot starts
            if (! $currentEnd->eq($nextStart)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if two time ranges overlap
     */
    public static function timeRangesOverlap(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): bool
    {
        $overlaps = $start1->lt($end2) && $start2->lt($end1);

        // Debug logging in test environment
        if (app()->environment('testing')) {
            \Log::info('Time range overlap check', [
                'range1' => $start1->format('Y-m-d H:i:s').' to '.$end1->format('Y-m-d H:i:s'),
                'range2' => $start2->format('Y-m-d H:i:s').' to '.$end2->format('Y-m-d H:i:s'),
                'overlaps' => $overlaps,
            ]);
        }

        return $overlaps;
    }

    /**
     * Get the actual datetime range for a time slot on a specific date
     *
     * @param  object  $timeSlot
     * @return array [start_datetime, end_datetime]
     */
    public static function getSlotDateTimeRange($timeSlot, string $date): array
    {
        $startDateTime = self::convertToDateTime($date, $timeSlot->start_time);
        $endDateTime = self::getSlotEndDateTime($startDateTime, $timeSlot->end_time);

        return [$startDateTime, $endDateTime];
    }

    /**
     * Check if a date falls on allowed days for a time slot
     *
     * @param  array  $availableDays  ['friday', 'saturday', 'sunday']
     */
    public static function isDateAllowed(string $date, array $availableDays): bool
    {
        $dayOfWeek = Carbon::createFromFormat('Y-m-d', $date)->format('l'); // Full day name

        return in_array(strtolower($dayOfWeek), array_map('strtolower', $availableDays));
    }

    /**
     * Get all dates in a range
     */
    public static function getDateRange(string $startDate, string $endDate): array
    {
        $dates = [];
        $current = Carbon::createFromFormat('Y-m-d', $startDate);
        $end = Carbon::createFromFormat('Y-m-d', $endDate);

        while ($current->lt($end)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Validate if end_date is required based on booking type
     */
    public static function validateDateRange(string $bookingType, ?string $endDate): bool
    {
        if ($bookingType === 'overnight' && ! $endDate) {
            return false;
        }

        return true;
    }
}
