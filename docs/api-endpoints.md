# Chalet API Endpoints

This document describes the API endpoints available for chalet operations in the ehjoz-chalet application.

## Base URL

All API endpoints are prefixed with `/api/chalet/{slug}` where `{slug}` is the unique identifier (slug) of the chalet.

## Authentication

Most endpoints do not require authentication, but some may require user login for certain operations.

## Endpoints

### 1. Get Chalet Availability

**Endpoint:** `GET /api/chalet/{slug}/availability`

**Description:** Check availability and get pricing for a chalet on specific dates.

**Query Parameters:**
- `booking_type` (required): Either `day-use` or `overnight`
- `start_date` (required): Start date in `YYYY-MM-DD` format (must be today or future)
- `end_date` (optional): End date in `YYYY-MM-DD` format (required for overnight bookings)

**Example Request:**
```bash
GET /api/chalet/beach-house/availability?booking_type=day-use&start_date=2025-01-15
```

**Example Response (Day-use):**
```json
{
  "success": true,
  "data": {
    "start_date": "2025-01-15",
    "end_date": "2025-01-15",
    "booking_type": "day-use",
    "slots": [
      {
        "id": 1,
        "name": "09:00 - 17:00",
        "start_time": "09:00:00",
        "end_time": "17:00:00",
        "price": 100.00,
        "weekend_price": 150.00,
        "final_price": 100.00,
        "has_discount": false,
        "original_price": 100.00,
        "discount_percentage": 0
      }
    ],
    "total_price": 100.00,
    "currency": "USD"
  }
}
```

**Example Response (Overnight):**
```json
{
  "success": true,
  "data": {
    "start_date": "2025-01-15",
    "end_date": "2025-01-17",
    "booking_type": "overnight",
    "slots": [
      {
        "id": 2,
        "name": "Overnight Stay",
        "is_overnight": true,
        "price_per_night": 200.00,
        "weekend_price": 250.00,
        "total_price": 400.00,
        "has_discount": false,
        "original_price": 400.00,
        "discount_percentage": 0
      }
    ],
    "total_price": 400.00,
    "currency": "USD",
    "nights_count": 2,
    "nightly_breakdown": [
      {
        "date": "2025-01-15",
        "night_number": 1,
        "base_price": 200.00,
        "adjustment": 0,
        "final_price": 200.00,
        "is_weekend": false,
        "custom_pricing_applied": false
      },
      {
        "date": "2025-01-16",
        "night_number": 2,
        "base_price": 200.00,
        "adjustment": 0,
        "final_price": 200.00,
        "is_weekend": false,
        "custom_pricing_applied": false
      }
    ]
  }
}
```

**Error Responses:**
- `400 Bad Request`: Invalid parameters (e.g., missing end_date for overnight)
- `404 Not Found`: Chalet not found or no availability
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: Server error

### 2. Get Unavailable Dates

**Endpoint:** `GET /api/chalet/{slug}/unavailable-dates`

**Description:** Get a list of dates when the chalet is not available for a specific booking type.

**Query Parameters:**
- `booking_type` (required): Either `day-use` or `overnight`
- `start_date` (required): Start date in `YYYY-MM-DD` format (must be today or future)
- `end_date` (required): End date in `YYYY-MM-DD` format (max 90 days from start)

**Example Request:**
```bash
GET /api/chalet/beach-house/unavailable-dates?booking_type=day-use&start_date=2025-01-15&end_date=2025-01-22
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "fully_blocked_dates": ["2025-01-18", "2025-01-19"],
    "unavailable_day_use_dates": ["2025-01-20"],
    "unavailable_overnight_dates": [],
    "date_range": {
      "start_date": "2025-01-15",
      "end_date": "2025-01-22",
      "total_days": 8
    },
    "summary": {
      "total_dates_checked": 8,
      "fully_blocked_count": 2,
      "unavailable_count": 1,
      "available_count": 5
    }
  }
}
```

**Error Responses:**
- `400 Bad Request`: Date range exceeds 90 days or invalid parameters
- `404 Not Found`: Chalet not found
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: Server error

## Validation Rules

### Availability Endpoint
- `start_date` must be today or future
- `end_date` must be after `start_date` for overnight bookings
- Overnight bookings cannot exceed 30 nights
- `booking_type` must be either `day-use` or `overnight`

### Unavailable Dates Endpoint
- `start_date` must be today or future
- `end_date` must be after `start_date`
- Date range cannot exceed 90 days
- `booking_type` must be either `day-use` or `overnight`

## Rate Limiting

These endpoints may be subject to rate limiting to prevent abuse. Check response headers for rate limit information.

## Caching

Availability data is cached for 1 hour by default to improve performance. Cache is automatically invalidated when:
- New bookings are created
- Blocked dates are added/removed
- Time slot configurations change

## Error Handling

All endpoints return consistent error responses with:
- `success`: Boolean indicating if the request was successful
- `error`: Human-readable error message
- `details`: Additional error details when available

## Usage Examples

### Frontend JavaScript

```javascript
// Check availability for day-use
async function checkDayUseAvailability(chaletSlug, date) {
  const response = await fetch(`/api/chalet/${chaletSlug}/availability?` + 
    new URLSearchParams({
      booking_type: 'day-use',
      start_date: date
    }));
  
  const data = await response.json();
  if (data.success) {
    return data.data;
  } else {
    throw new Error(data.error);
  }
}

// Get unavailable dates for calendar
async function getUnavailableDates(chaletSlug, startDate, endDate, bookingType) {
  const response = await fetch(`/api/chalet/${chaletSlug}/unavailable-dates?` + 
    new URLSearchParams({
      booking_type: bookingType,
      start_date: startDate,
      end_date: endDate
    }));
  
  const data = await response.json();
  if (data.success) {
    return data.data;
  } else {
    throw new Error(data.error);
  }
}
```

### cURL Examples

```bash
# Check day-use availability
curl "https://yourdomain.com/api/chalet/beach-house/availability?booking_type=day-use&start_date=2025-01-15"

# Check overnight availability
curl "https://yourdomain.com/api/chalet/beach-house/availability?booking_type=overnight&start_date=2025-01-15&end_date=2025-01-17"

# Get unavailable dates
curl "https://yourdomain.com/api/chalet/beach-house/unavailable-dates?booking_type=day-use&start_date=2025-01-15&end_date=2025-01-22"
```

## Notes

- All dates should be in `YYYY-MM-DD` format
- Times are in 24-hour format (`HH:MM:SS`)
- Prices are in USD by default
- Weekend pricing is determined by the chalet's `weekend_days` configuration
- Custom pricing adjustments may apply based on specific dates
- The system automatically handles timezone conversions
