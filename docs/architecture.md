# ehjoz-chalet: Models and Services Architecture Guide

This document explains the core domain models, the service layer, and how they interact to deliver availability checks, booking creation, search, and pricing. It is intended for developers working on the booking/availability domain.

Last updated: 2025-08-16

---

## Core Models

1) Chalet (app/Models/Chalet.php)
- Purpose: The main rentable entity with owner, media, geo-location, limits, and business settings.
- Key relationships:
  - timeSlots(): hasMany(ChaletTimeSlot) — lines 193-196
  - blockedDates(): hasMany(ChaletBlockedDate) — lines 201-204
  - customPricing(): hasMany(ChaletCustomPricing) — lines 209-212
  - bookings(): hasMany(Booking) — lines 217-220
  - owner(): belongsTo(User) — lines 230-233
- Notable attributes:
  - weekend_days cast to array — lines 241-255
  - Computed location attribute for lat/lng with accessors/mutators — lines 102-129

2) ChaletTimeSlot (app/Models/ChaletTimeSlot.php)
- Purpose: Defines an available time window with pricing, availability days, and whether it is overnight.
- Notable fields: is_overnight, weekday_price, weekend_price, available_days[], allows_extra_hours, max_extra_hours.
- Relationships:
  - chalet(): belongsTo(Chalet) — lines 29-32
  - bookings(): belongsToMany(Booking) via pivot booking_time_slot — lines 34-37

3) ChaletCustomPricing (app/Models/ChaletCustomPricing.php)
- Purpose: Per-slot pricing adjustments for date ranges.
- Fields: start_date, end_date, custom_adjustment, is_active.
- Relationships: chalet(), timeSlot() — lines 26-34.

4) ChaletBlockedDate (app/Models/ChaletBlockedDate.php)
- Purpose: Full-day or per-slot blocks with a reason.
- Fields: date, time_slot_id (nullable for full-day blocks), reason (enum), notes.
- Relationships: chalet(), timeSlot() — lines 23-31.
- Guard: booted() ensures only the owner can save blocks for their chalet — lines 33-48.

5) Booking (app/Models/Booking.php)
- Purpose: Represents a user reservation with date range, totals, and statuses.
- Relationships:
  - chalet(): belongsTo(Chalet) — lines 76-79
  - user(): belongsTo(User) — lines 55-58
  - timeSlots(): belongsToMany(ChaletTimeSlot) — lines 96-99
  - chaletOwner(): hasOneThrough(User, Chalet) — lines 84-94
- Casting includes status, payment_status enums and monetary fields — lines 106-126.

---

## Utility Services

TimeSlotHelper (app/Services/TimeSlotHelper.php)
- Converts date and time strings to Carbon and resolves cross-midnight end datetimes — convertToDateTime(), getSlotEndDateTime() — lines 17-50.
- Derives date ranges a slot booking affects — getSlotsDateRange() — lines 62-89.
- Validates consecutive day-use slots — isConsecutive() — lines 97-128.
- Computes time range overlaps on actual datetimes — timeRangesOverlap(), getSlotDateTimeRange() — lines 139-168.
- Validates booking date requirements (overnight requires end_date) — validateDateRange() — lines 211-218.

OverlapDetector (app/Services/OverlapDetector.php)
- Aggregates conflicts with blocked dates and existing bookings for a given slot/date — findConflictingSlots() — lines 22-44.
- Block checks:
  - Full-day blocks: ChaletBlockedDate with null time_slot_id on target date — getBlockedSlotsConflicts() — lines 54-111.
  - Per-slot blocks resolved to real datetime overlap using TimeSlotHelper — slotsOverlapOnDates() — lines 185-192.
- Booking checks:
  - Looks up confirmed bookings across extended date window (+/- 1 day) — getBookedSlotsConflicts() — lines 121-174.
  - Compares actual datetime ranges using TimeSlotHelper.
- Helpers:
  - getExtendedDateRange(): pads date by a day on both sides to catch overnight spill — lines 200-208.
  - isSlotAvailableOnDate(): verifies available_days and absence of conflicts — lines 218-232.
  - getAffectedSlots(): ripple effect of blocking a slot — lines 243-273.

---

## Core Domain Services

AvailabilityService (app/Services/AvailabilityService.php)
- Entry point: checkAvailability(chaletId, startDate, endDate?, bookingType, timeSlotIds?) — lines 28-109.
  - Validates inputs — validateInputs() — lines 362-397.
  - For day-use without end_date, sets end_date = start_date — lines 45-48.
  - Caches results by chalet/date/type/slots — generateCacheKey() — lines 409-414.
  - Fetches candidate slots via getTimeSlotsToCheck() (active, filtered by type and IDs) — lines 278-296.
  - Delegates to checkSlotsAvailability() — lines 120-179.
- checkSlotsAvailability():
  - Early rejection if any date is full-day blocked (ChaletBlockedDate with null time_slot_id) — lines 126-141.
  - For each slot: checkSingleSlotAvailability() determines per-date availability and pricing — lines 143-159.
  - For day-use with multiple available slots: builds consecutive combinations — findConsecutiveCombinations() — lines 162-171, 244-268.
- checkSingleSlotAvailability():
  - Ensures date falls within slot.available_days — TimeSlotHelper::isDateAllowed() — lines 196-201.
  - Uses OverlapDetector::findConflictingSlots() to detect blocked/booked conflicts — lines 203-219.
  - Calculates pricing per date — calculateSlotPricing() — lines 221-227.
- calculateSlotPricing():
  - Determines weekday/weekend base price (note: currently assumes Fri/Sat as weekend) and applies ChaletCustomPricing adjustment — lines 305-329.
- calculateTotalDuration():
  - Returns minutes between first.start_time and last.end_time over the date, handling cross-midnight — lines 338-351.
- clearAvailabilityCache(): helper to invalidate availability cache when data changes — lines 424-449.

PricingCalculator (app/Services/PricingCalculator.php)
- Calculates booking totals and per-slot breakdowns for both overnight and day-use scenarios.
- Key functions (see file structure):
  - calculateBookingPrice(chaletId, timeSlotIds, startDate, endDate?, bookingType) — lines 24-67.
  - Overnight vs day-use paths — lines 80-171.
  - Calculates slot price for specific date considering weekend config and custom pricing — lines 181-204.
  - isWeekendDate(Chalet, date): respects chalet->weekend_days — lines 213-219.
  - getCustomPricingForDate(): pulls ChaletCustomPricing entries — lines 228-237.
  - generateBreakdownSummary(): aggregates slot details — lines 245-292.
  - calculateEstimatedPrice(): used by search/availability to estimate — lines 304-363.

BookingService (app/Services/BookingService.php)
- Orchestrates booking creation, validation, pricing, and persistence.
- Dependencies: AvailabilityService (for validation), PricingCalculator (for totals).
- Flow:
  - createBooking(): validates input, wraps process in DB transaction, and clears related caches — lines 34-72.
  - processBookingCreation(): SELECT FOR UPDATE on chalet row, re-validates availability, calculates price, creates Booking, attaches time slots (pivot), logs — lines 80-157.
  - createBookingDatetimes(): computes actual start/end datetimes using availability data — lines 166-193.
  - attachTimeSlotsToBooking(): persists pivot and price metadata (if applicable) — lines 202-206.
  - Validation helpers: validateBookingRequest/dates/timeSlots/optional fields — lines 214-378.
  - Status and lifecycle: updateBookingStatus(), cancelBooking(), checkCancellationPolicy() — lines 478-646.
  - Queries: getBookingDetails(), getUserBookings() — lines 655-785.

ChaletSearchService (app/Services/ChaletSearchService.php)
- Searches chalets then filters by real availability using AvailabilityService.
- Flow:
  - searchAvailableChalets(): validates params, caches overall search result — lines 29-76.
  - performSearch(): builds base query, iterates chalets, checks availability, formats results, and sorts — lines 84-138.
  - getBaseChaletsQuery(): applies static filters (city, capacity, etc.) — lines 146-174.
  - addDateAvailabilityFilter(): optional query-time date filters — lines 182-207.
  - formatChaletResult(): combines Chalet entity data with availability summary, and attaches a pricing summary (often via PricingCalculator.calculateEstimatedPrice) — lines 217-257, 266-303.
  - generateSearchCacheKey(), clearSearchCache(), getSearchSuggestions() — lines 390-451.

---

## How Things Connect (Data Flows)

1) Availability Check
- Input: chaletId, date range, bookingType, optional slot IDs.
- AvailabilityService:
  - queries ChaletTimeSlot (active/type filters), checks ChaletBlockedDate full-day blocks, and per-slot blocks & existing Booking overlaps via OverlapDetector, using TimeSlotHelper for real datetime comparisons.
  - returns available slots, per-date pricing (base + custom adjustment), and consecutive combinations for day-use.

2) Booking Creation
- BookingService:
  - validate -> lock chalet row -> AvailabilityService.validateBookingRequest re-check -> PricingCalculator.calculateBookingPrice -> create Booking -> attach time slots (pivot) -> clear caches.
  - The created booking’s start/end datetimes reflect the chosen slots and cross-midnight behavior via TimeSlotHelper logic (embedded in Availability/validation pipeline).

3) Search
- ChaletSearchService:
  - Builds a list of chalets by filters, then for each calls AvailabilityService.checkAvailability.
  - Uses PricingCalculator.calculateEstimatedPrice to present total/summary pricing in search results.

4) Blocks/Custom Pricing
- ChaletBlockedDate:
  - Full-day block (time_slot_id null) makes any availability check fail for that date immediately.
  - Per-slot blocks are detected only if they overlap in actual time with the target slot on the target date.
- ChaletCustomPricing:
  - Applied either in AvailabilityService.calculateSlotPricing (per-date slot price) or PricingCalculator for final totals.

---

## Edge Cases and Notes

- Cross-midnight slots: handled via TimeSlotHelper.getSlotEndDateTime() and downstream overlap logic. OverlapDetector extends the query window by +/- 1 day to catch overnight spill.
- Overnight bookings: TimeSlotHelper.getSlotsDateRange() includes all dates from start to end; used to compare against bookings/blocks.
- available_days enforcement: A slot won’t show as available on dates outside its allowed weekdays.
- Weekend logic: PricingCalculator.isWeekendDate honors chalet->weekend_days, whereas AvailabilityService.calculateSlotPricing currently assumes Fri/Sat as weekend (comment hints future enhancement). Align if needed.
- Caching: Availability and Search cache rely on composite keys. Consider explicit cache invalidation via AvailabilityService.clearAvailabilityCache and ChaletSearchService.clearSearchCache after writes.

---

## Quick Reference (Line Numbers)
- AvailabilityService: 28-109 entry, 120-179 slots, 190-235 per-slot, 244-268 combos, 278-296 fetching slots, 305-329 pricing, 338-351 duration, 362-397 validate, 409-414 cache key, 424-449 clear cache.
- BookingService: 34-72 create, 80-157 process, 166-193 datetimes, 202-206 attach, 214-378 validation, 478-646 lifecycle, 655-785 queries.
- ChaletSearchService: 29-76 search API, 84-138 search core, 146-174 base query, 182-207 date filters, 217-303 result formatting/pricing, 390-451 cache/suggestions.
- OverlapDetector: 22-44 conflicts, 54-111 blocks, 121-174 bookings, 185-192 overlap, 200-208 extended range.
- TimeSlotHelper: 17-50 conversions, 62-89 affected dates, 97-128 consecutive, 139-168 overlap helpers, 177-181 allowed days, 190-202 date range, 211-218 range validation.

---

## Suggested Reading Order (for new contributors)
1. Models: Chalet, ChaletTimeSlot, ChaletBlockedDate, ChaletCustomPricing, Booking.
2. Utilities: TimeSlotHelper, OverlapDetector.
3. Services: AvailabilityService (core), PricingCalculator, BookingService, ChaletSearchService.

