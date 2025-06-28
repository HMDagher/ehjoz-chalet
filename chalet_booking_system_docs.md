# 🏡 Chalet Booking System Documentation

## 📋 Table of Contents
- [System Overview](#system-overview)
- [Core Models](#core-models)
- [Time Slot Types](#time-slot-types)
- [Booking Scenarios](#booking-scenarios)
- [Service Architecture](#service-architecture)
- [Availability Logic](#availability-logic)
- [Pricing System](#pricing-system)
- [Search Functionality](#search-functionality)
- [API Usage Examples](#api-usage-examples)
- [Database Relationships](#database-relationships)

## 🎯 System Overview

The Chalet Booking System is designed to handle two distinct booking types:
1. **Day-Use Bookings**: Single or multiple consecutive time slots within one day
2. **Overnight Bookings**: Multi-day stays using overnight time slots

### Key Features
- ✅ Flexible time slot management
- ✅ Custom pricing adjustments
- ✅ Blocked dates for maintenance
- ✅ Consecutive time slot validation
- ✅ Weekend/weekday pricing
- ✅ Commission calculations

## 🗃️ Core Models

### 1. Chalet Model
The main property entity containing all chalet information.

**Key Fields:**
- `owner_id` - Property owner
- `name`, `slug`, `description` - Basic info
- `address`, `city`, `latitude`, `longitude` - Location
- `max_adults`, `max_children` - Capacity limits
- `bedrooms_count`, `bathrooms_count` - Facilities
- `status` - Active/Inactive
- `is_featured`, `featured_until` - Promotional settings
- Financial fields for earnings tracking

**Relationships:**
- Has many `timeSlots`
- Has many `bookings`
- Has many `blockedDates`
- Has many `customPricing`
- Belongs to `owner` (User)

### 2. ChaletTimeSlot Model
Defines available booking periods for each chalet.

**Key Fields:**
- `chalet_id` - Parent chalet
- `name` - e.g., "Morning", "Evening", "Overnight"
- `start_time`, `end_time` - Time boundaries
- `is_overnight` - Boolean flag for overnight slots
- `duration_hours` - Slot duration
- `weekday_price`, `weekend_price` - Base pricing
- `allows_extra_hours`, `extra_hour_price` - Extension options
- `available_days` - JSON array of weekdays
- `is_active` - Enable/disable slot

### 3. Booking Model
Represents customer reservations.

**Key Fields:**
- `chalet_id`, `user_id` - References
- `booking_reference` - Unique identifier
- `start_date`, `end_date` - Booking period
- `adults_count`, `children_count` - Guest counts
- Pricing breakdown fields
- `status` - Confirmed/Pending/Cancelled
- `payment_status` - Payment tracking

**Relationships:**
- Belongs to `chalet` and `user`
- Belongs to many `timeSlots` (pivot: `booking_time_slot`)

### 4. ChaletBlockedDate Model
Prevents bookings on specific dates.

**Key Fields:**
- `chalet_id` - Parent chalet
- `date` - Blocked date
- `time_slot_id` - Specific slot (nullable for full day)
- `reason` - Block reason enum
- `notes` - Additional information

### 5. ChaletCustomPricing Model
Seasonal or special pricing adjustments.

**Key Fields:**
- `chalet_id`, `time_slot_id` - References
- `start_date`, `end_date` - Date range
- `custom_adjustment` - Price modifier (+/-)
- `name` - e.g., "Summer Season", "Holiday Premium"
- `is_active` - Enable/disable

## ⏰ Time Slot Types

### Day-Use Slots (`is_overnight = false`)
Perfect for short-term rentals within a single day.

**Examples:**
- Morning: 08:00 - 12:00 (4 hours)
- Afternoon: 12:00 - 17:00 (5 hours)
- Evening: 17:00 - 22:00 (5 hours)

**Features:**
- Can be booked individually or consecutively
- Multiple slots must be consecutive (no gaps)
- Same-day booking only

### Overnight Slots (`is_overnight = true`)
Designed for multi-day stays.

**Examples:**
- Standard Overnight: 15:00 - 11:00 (next day)
- Extended Stay: 14:00 - 12:00 (next day)

**Features:**
- Spans multiple days
- Check-in and check-out times defined
- Price calculated per night
- Cannot be combined with other slots

## 📅 Booking Scenarios

### Scenario 1: Single Time Slot Booking
**Use Case:** Customer wants morning slot only
```
Date: 2024-07-15
Time Slot: Morning (08:00 - 12:00)
Duration: 4 hours
Price: Base price + any adjustments
```

### Scenario 2: Multiple Consecutive Slots
**Use Case:** Customer wants extended day use
```
Date: 2024-07-15
Time Slots: Morning (08:00 - 12:00) + Afternoon (12:00 - 17:00)
Duration: 9 hours total
Price: Sum of both slot prices
Validation: Must be consecutive (Morning ends where Afternoon starts)
```

### Scenario 3: Overnight Multi-Day Booking
**Use Case:** Weekend getaway
```
Check-in: 2024-07-15 15:00
Check-out: 2024-07-17 11:00
Time Slot: Standard Overnight
Duration: 2 nights
Price: (Night 1 price + Night 2 price) + adjustments
```

### Scenario 4: Blocked Date Handling
**Types of Blocks:**
- **Full Day Block**: No bookings allowed on entire date
- **Slot-Specific Block**: Only specific time slot blocked
- **Maintenance Block**: Property unavailable
- **Owner Block**: Owner using property

## 🔧 Service Architecture

### ChaletAvailabilityService
Handles all availability checking and pricing calculations.

**Key Methods:**
- `isAvailableForDay(date, timeSlotIds)` - Check single day availability
- `isAvailableForOvernightRange(start, end, slotId)` - Check multi-day availability
- `areTimeSlotsConsecutive(slotIds)` - Validate consecutive slots
- `getPrice(date, slotId)` - Calculate price with adjustments
- `getConsecutiveSlotCombinations(date)` - Get all possible combinations

### ChaletSearchService
Provides search functionality for different booking types.

**Key Methods:**
- `search(startDate, endDate, filters)` - Universal search
- `searchForSingleDate(date, filters)` - Day-use search
- `searchForDateRange(start, end, filters)` - Overnight search
- `searchWithSlotRequirements(dates, slotIds, filters)` - Specific slot search

## 🔍 Availability Logic

### Day-Use Availability Check
```php
foreach ($timeSlotIds as $slotId) {
    // 1. Check if entire day is blocked
    if (dayBlocked) return false;
    
    // 2. Check if specific slot is blocked
    if (slotBlocked) return false;
    
    // 3. Check existing bookings
    if (alreadyBooked) return false;
}

// 4. Validate consecutive slots (if multiple)
if (count($timeSlotIds) > 1) {
    return areTimeSlotsConsecutive($timeSlotIds);
}
```

### Overnight Availability Check
```php
$currentDate = $startDate;
while ($currentDate <= $endDate) {
    // Check each date in range
    if (!isSingleSlotAvailable($currentDate, $slotId)) {
        return false;
    }
    $currentDate = $currentDate->addDay();
}
```

## 💰 Pricing System

### Base Pricing Structure
```
Base Price = is_weekend ? weekend_price : weekday_price
```

### Custom Pricing Application
```
Final Price = Base Price + Custom Adjustment
```

**Priority Order:**
1. Custom pricing (if active and date falls within range)
2. Base weekday/weekend pricing

### Multi-Day Pricing
```
Total Price = Σ(Daily Price for each date in range)
```

### Example Pricing Calculation
```php
// Summer season adjustment: +$50
// Weekend premium already included in base price

Date: 2024-07-20 (Saturday)
Base Weekend Price: $200
Custom Adjustment: +$50 (Summer season)
Final Price: $250
```

## 🔎 Search Functionality

### Search Flow Decision
```
if (startDate === endDate) {
    // Single date search - return day-use slots
    return searchForSingleDate();
} else {
    // Date range search - return overnight slots
    return searchForDateRange();
}
```

### Single Date Search Results
```json
{
  "chalet": { "id": 1, "name": "Seaside Villa" },
  "slot_combinations": [
    {
      "slots": [{"id": 1, "name": "Morning"}],
      "total_price": 150,
      "start_time": "08:00",
      "end_time": "12:00"
    },
    {
      "slots": [
        {"id": 1, "name": "Morning"},
        {"id": 2, "name": "Afternoon"}
      ],
      "total_price": 280,
      "start_time": "08:00",
      "end_time": "17:00"
    }
  ]
}
```

### Date Range Search Results
```json
{
  "chalet": { "id": 1, "name": "Seaside Villa" },
  "available_slots": [
    {
      "id": 3,
      "name": "Standard Overnight",
      "total_price": 600,
      "nights": 3,
      "average_per_night": 200
    }
  ]
}
```

## 🛠️ API Usage Examples

### Search for Day-Use Options
```php
$searchService = new ChaletSearchService();

// Single date search
$results = $searchService->search('2024-07-15', '2024-07-15', [
    'city' => 'Beirut',
    'max_adults' => 4,
    'amenities' => [1, 2, 3] // Pool, WiFi, Parking
]);
```

### Search for Overnight Stays
```php
// Multi-day search
$results = $searchService->search('2024-07-15', '2024-07-18', [
    'city' => 'Jounieh',
    'max_adults' => 6,
    'min_bedrooms' => 2
]);
```

### Check Specific Slot Availability
```php
$availabilityService = new ChaletAvailabilityService($chalet);

// Check consecutive morning + afternoon slots
$available = $availabilityService->isAvailableForDay('2024-07-15', [1, 2]);

// Check overnight availability
$available = $availabilityService->isAvailableForOvernightRange(
    '2024-07-15', 
    '2024-07-17', 
    3
);
```

### Get Pricing Information
```php
// Single slot price
$price = $availabilityService->getPrice('2024-07-15', 1);

// Multiple slots total
$totalPrice = $availabilityService->getTotalPriceForDay('2024-07-15', [1, 2]);

// Overnight range total
$totalPrice = $availabilityService->getTotalPriceForOvernightRange(
    '2024-07-15', 
    '2024-07-17', 
    3
);
```

## 🗂️ Database Relationships

### Pivot Tables Required
```sql
-- Booking to Time Slots (Many-to-Many)
CREATE TABLE booking_time_slot (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    chalet_time_slot_id BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (chalet_time_slot_id) REFERENCES chalet_time_slots(id) ON DELETE CASCADE
);

-- Chalet to Amenities (Many-to-Many)
CREATE TABLE amenity_chalet (
    chalet_id BIGINT UNSIGNED NOT NULL,
    amenity_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (chalet_id, amenity_id)
);

-- Chalet to Facilities (Many-to-Many)
CREATE TABLE chalet_facility (
    chalet_id BIGINT UNSIGNED NOT NULL,
    facility_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (chalet_id, facility_id)
);
```

### Key Indexes for Performance
```sql
-- Availability checking
CREATE INDEX idx_blocked_dates_lookup ON chalet_blocked_dates (chalet_id, date, time_slot_id);

-- Booking overlap checking
CREATE INDEX idx_booking_dates ON bookings (chalet_id, start_date, end_date, status);

-- Custom pricing lookup
CREATE INDEX idx_custom_pricing_lookup ON chalet_custom_pricing (chalet_id, time_slot_id, start_date, end_date, is_active);

-- Search filtering
CREATE INDEX idx_chalet_search ON chalets (status, city, max_adults, max_children);
```

## 🚀 Implementation Best Practices

### 1. Validation Rules
- Always validate consecutive slots for day-use bookings
- Ensure overnight slots are not mixed with day-use slots
- Check guest capacity limits
- Validate date ranges (end >= start)

### 2. Error Handling
- Handle edge cases (same start/end times, invalid dates)
- Provide clear error messages for booking conflicts
- Log failed availability checks for debugging

### 3. Performance Optimization
- Use database indexes for common queries
- Cache frequently accessed chalet data
- Batch availability checks when possible
- Use eager loading for relationships

### 4. Business Rules
- Define clear cancellation policies
- Implement commission calculations
- Handle partial payments and refunds
- Set up automated booking confirmations

## 📈 Future Enhancements

### Potential Features
- **Dynamic Pricing**: AI-based price optimization
- **Instant Booking**: Auto-confirm certain bookings
- **Group Bookings**: Handle large party reservations
- **Loyalty Programs**: Reward frequent customers
- **Mobile App**: Native mobile booking experience
- **Calendar Sync**: Integration with external calendars

This documentation provides a comprehensive understanding of how the chalet booking system works, covering all major scenarios and implementation details.