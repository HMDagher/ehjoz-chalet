<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ChaletBlockedDate;
use App\Models\ChaletTimeSlot;
use Carbon\Carbon;

class OverlapDetector
{
    /**
     * Find all conflicting slots for a given time slot and date range
     *
     * @param  object  $targetSlot
     * @param  array  $dateRange  Array of date strings
     * @return array ['blocked' => [...], 'booked' => [...]]
     */
    public static function findConflictingSlots(int $chaletId, $targetSlot, array $dateRange): array
    {
        $conflicts = [
            'blocked' => [],
            'booked' => [],
        ];

        foreach ($dateRange as $date) {
            // Get blocked slots conflicts
            $blockedConflicts = self::getBlockedSlotsConflicts($chaletId, $targetSlot, $date);
            $conflicts['blocked'] = array_merge($conflicts['blocked'], $blockedConflicts);

            // Get booked slots conflicts
            $bookedConflicts = self::getBookedSlotsConflicts($chaletId, $targetSlot, $date);
            $conflicts['booked'] = array_merge($conflicts['booked'], $bookedConflicts);
        }

        // Remove duplicates
        $conflicts['blocked'] = array_unique($conflicts['blocked'], SORT_REGULAR);
        $conflicts['booked'] = array_unique($conflicts['booked'], SORT_REGULAR);

        return $conflicts;
    }

    /**
     * Get conflicts with blocked dates/slots
     *
     * @param  object  $targetSlot
     */
    private static function getBlockedSlotsConflicts(int $chaletId, $targetSlot, string $date): array
    {
        $conflicts = [];

        // Get blocked dates for this chalet around the target date
        $extendedDateRange = self::getExtendedDateRange($date);

        $blockedDates = ChaletBlockedDate::where('chalet_id', $chaletId)
            ->whereDate('date', '>=', $extendedDateRange['start'])
            ->whereDate('date', '<=', $extendedDateRange['end'])
            ->get();

        foreach ($blockedDates as $blocked) {
            $blockedDate = $blocked->date->format('Y-m-d');

            // If no specific time slot is blocked, all slots are blocked that day
            if (! $blocked->time_slot_id) {
                if ($blockedDate === $date) {
                    $conflicts[] = [
                        'type' => 'blocked',
                        'date' => $blockedDate,
                        'reason' => 'full_day_blocked',
                        'slot_id' => null,
                    ];
                }

                continue;
            }

            // Get the blocked time slot details
            $blockedSlot = ChaletTimeSlot::find($blocked->time_slot_id);
            if (! $blockedSlot) {
                continue;
            }

            // Check for overlaps
            $overlaps = self::slotsOverlapOnDates($targetSlot, $date, $blockedSlot, $blockedDate);

            // Debug logging in test environment
            if (app()->environment('testing')) {
                \Log::info('Overlap check', [
                    'target_slot' => $targetSlot->id ?? 'unknown',
                    'target_date' => $date,
                    'blocked_slot' => $blockedSlot->id,
                    'blocked_date' => $blockedDate,
                    'overlaps' => $overlaps,
                ]);
            }

            if ($overlaps) {
                // Normalize enum reason to string if necessary
                $reason = $blocked->reason;
                if (is_object($reason)) {
                    if (method_exists($reason, 'value')) {
                        $reason = $reason->value;
                    } elseif (method_exists($reason, 'name')) {
                        $reason = $reason->name;
                    } else {
                        $reason = (string) json_encode($reason);
                    }
                }
                $conflicts[] = [
                    'type' => 'blocked',
                    'date' => $blockedDate,
                    'reason' => $reason,
                    'slot_id' => $blocked->time_slot_id,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Get conflicts with existing bookings
     *
     * @param  object  $targetSlot
     */
    private static function getBookedSlotsConflicts(int $chaletId, $targetSlot, string $date): array
    {
        $conflicts = [];

        // Get extended date range to catch overnight bookings
        $extendedDateRange = self::getExtendedDateRange($date);

        // Get confirmed bookings that might conflict
        $bookings = Booking::where('chalet_id', $chaletId)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function ($query) use ($extendedDateRange) {
                $query->whereBetween('start_date', [
                    $extendedDateRange['start'].' 00:00:00',
                    $extendedDateRange['end'].' 23:59:59',
                ])
                    ->orWhereBetween('end_date', [
                        $extendedDateRange['start'].' 00:00:00',
                        $extendedDateRange['end'].' 23:59:59',
                    ])
                    ->orWhere(function ($q) use ($extendedDateRange) {
                        $q->where('start_date', '<=', $extendedDateRange['start'].' 00:00:00')
                            ->where('end_date', '>=', $extendedDateRange['end'].' 23:59:59');
                    });
            })
            ->with('timeSlots')
            ->get();

        foreach ($bookings as $booking) {
            foreach ($booking->timeSlots as $bookedSlot) {
                // Get all dates this booking affects
                $bookingStartDate = Carbon::parse($booking->start_date)->format('Y-m-d');
                $bookingEndDate = Carbon::parse($booking->end_date)->format('Y-m-d');

                $bookedDates = TimeSlotHelper::getSlotsDateRange(
                    $bookedSlot,
                    $bookingStartDate,
                    $bookingEndDate
                );

                foreach ($bookedDates as $bookedDate) {
                    if (self::slotsOverlapOnDates($targetSlot, $date, $bookedSlot, $bookedDate)) {
                        $conflicts[] = [
                            'type' => 'booked',
                            'date' => $bookedDate,
                            'booking_id' => $booking->id,
                            'slot_id' => $bookedSlot->id,
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if two time slots overlap on their respective dates
     *
     * @param  object  $slot1
     * @param  object  $slot2
     */
    private static function slotsOverlapOnDates($slot1, string $date1, $slot2, string $date2): bool
    {
        // Get actual datetime ranges
        [$start1, $end1] = TimeSlotHelper::getSlotDateTimeRange($slot1, $date1);
        [$start2, $end2] = TimeSlotHelper::getSlotDateTimeRange($slot2, $date2);

        return TimeSlotHelper::timeRangesOverlap($start1, $end1, $start2, $end2);
    }

    /**
     * Get extended date range to catch overnight slots from previous/next days
     */
    private static function getExtendedDateRange(string $date): array
    {
        $targetDate = Carbon::createFromFormat('Y-m-d', $date);

        return [
            'start' => $targetDate->copy()->subDay()->format('Y-m-d'),
            'end' => $targetDate->copy()->addDay()->format('Y-m-d'),
        ];
    }

    /**
     * Check if a time slot is available on a specific date
     */
    public static function isSlotAvailableOnDate(int $chaletId, int $slotId, string $date): bool
    {
        $slot = ChaletTimeSlot::find($slotId);
        if (! $slot) {
            return false;
        }

        // Check if date is in available_days
        if (! TimeSlotHelper::isDateAllowed($date, $slot->available_days)) {
            return false;
        }

        // Check for conflicts
        $conflicts = self::findConflictingSlots($chaletId, $slot, [$date]);

        return empty($conflicts['blocked']) && empty($conflicts['booked']);
    }

    /**
     * Get all slots that would be affected if a specific slot gets blocked/booked
     * This is useful for understanding the ripple effect
     */
    public static function getAffectedSlots(int $chaletId, int $targetSlotId, string $date): array
    {
        $targetSlot = ChaletTimeSlot::find($targetSlotId);
        if (! $targetSlot) {
            return [];
        }

        $affected = [];

        // Get all active slots for this chalet
        $allSlots = ChaletTimeSlot::where('chalet_id', $chaletId)
            ->where('is_active', true)
            ->where('id', '!=', $targetSlotId)
            ->get();

        $extendedDateRange = self::getExtendedDateRange($date);
        $datesToCheck = TimeSlotHelper::getDateRange($extendedDateRange['start'], $extendedDateRange['end']);

        foreach ($allSlots as $slot) {
            foreach ($datesToCheck as $checkDate) {
                if (self::slotsOverlapOnDates($targetSlot, $date, $slot, $checkDate)) {
                    $affected[] = [
                        'slot_id' => $slot->id,
                        'affected_date' => $checkDate,
                        'slot_time' => $slot->start_time.' - '.$slot->end_time,
                        'is_overnight' => $slot->is_overnight,
                    ];
                }
            }
        }

        return $affected;
    }
}
