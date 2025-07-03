@extends('Frontend.Layouts.app')

@section('title', 'Booking Confirmation')

@section('content')
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center">Booking Confirmation</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Booking Confirmation</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Booking Confirmation Section -->
<section class="booking-confirmation py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Success Alert -->
                <div class="alert alert-success text-center mb-4">
                    <i class="fas fa-check-circle fa-2x mb-3"></i>
                    <h4>Booking Confirmed!</h4>
                    <p class="mb-0">Your booking has been successfully created. Please complete the payment to secure your reservation.</p>
                </div>

                <!-- Booking Details Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Booking Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Booking Reference:</strong><br>
                                    <span class="text-primary fw-bold">{{ $booking->booking_reference }}</span>
                                </p>
                                <p><strong>Chalet:</strong><br>
                                    {{ $booking->chalet->name }}
                                </p>
                                <p><strong>Address:</strong><br>
                                    {{ $booking->chalet->address }}
                                </p>
                                @if($booking->chalet->latitude && $booking->chalet->longitude)
                                    <p>
                                        <a href="https://www.google.com/maps/search/?api=1&query={{ $booking->chalet->latitude }},{{ $booking->chalet->longitude }}" target="_blank" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-map-marker-alt me-1"></i>View on Google Maps
                                        </a>
                                    </p>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <p><strong>Check-in Date & Time:</strong><br>
                                    {{ $booking->start_date->format('l, F j, Y \a\t g:i A') }}
                                </p>
                                <p><strong>Check-out Date & Time:</strong><br>
                                    {{ $booking->end_date->format('l, F j, Y \a\t g:i A') }}
                                </p>
                                <p><strong>Booking Type:</strong><br>
                                    <span class="badge bg-info">{{ $booking->booking_type === 'day-use' ? 'Day Use' : 'Overnight' }}</span>
                                </p>
                                <p class="text-muted"><small>All times are shown in Beirut time (Asia/Beirut).</small></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Details Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Details</h5>
                    </div>
                    <div class="card-body">
                        @if($booking->booking_type === 'day-use')
                            <!-- Day-use Pricing Details -->
                            <div class="mb-3">
                                <h6><i class="fas fa-clock me-2"></i>Time Slots Pricing:</h6>
                                @foreach($booking->timeSlots as $slot)
                                    @php
                                        $slotDate = \Carbon\Carbon::parse($booking->start_date);
                                        $isWeekend = in_array($slotDate->dayOfWeek, [5, 6, 0]); // Friday, Saturday, Sunday
                                        $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;
                                        
                                        // Check for custom pricing
                                        $customPricing = $booking->chalet->customPricing()
                                            ->where('time_slot_id', $slot->id)
                                            ->where('start_date', '<=', $slotDate->format('Y-m-d'))
                                            ->where('end_date', '>=', $slotDate->format('Y-m-d'))
                                            ->where('is_active', true)
                                            ->latest('created_at')
                                            ->first();
                                        
                                        $adjustment = $customPricing ? $customPricing->custom_adjustment : 0;
                                        $finalPrice = $basePrice + $adjustment;
                                    @endphp
                                    <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-2">
                                        <div>
                                            <strong>{{ $slot->name }}</strong><br>
                                            <small class="text-muted">
                                                {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}
                                                ({{ $slot->duration_hours }} hrs)
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-muted small">
                                                {{ $isWeekend ? 'Weekend' : 'Weekday' }}: ${{ number_format($basePrice, 2) }}
                                                @if($adjustment != 0)
                                                    <br><span class="text-info">{{ $customPricing ? $customPricing->name : 'Custom' }}: ${{ number_format($adjustment, 2) }}</span>
                                                @endif
                                            </div>
                                            <strong>${{ number_format($finalPrice, 2) }}</strong>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <!-- Overnight Pricing Details -->
                            <div class="mb-3">
                                <h6><i class="fas fa-moon me-2"></i>Nightly Pricing:</h6>
                                @php
                                    $startDate = \Carbon\Carbon::parse($booking->start_date);
                                    $endDate = \Carbon\Carbon::parse($booking->end_date);
                                    $nights = $startDate->diffInDays($endDate);
                                    $nights = max(1, $nights);
                                    
                                    $slot = $booking->timeSlots->first();
                                    $totalPrice = 0;
                                    $currentDate = $startDate->copy();
                                @endphp
                                
                                @for($i = 0; $i < $nights; $i++)
                                    @php
                                        $isWeekend = in_array($currentDate->dayOfWeek, [5, 6, 0]); // Friday, Saturday, Sunday
                                        $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;
                                        
                                        // Check for custom pricing
                                        $customPricing = $booking->chalet->customPricing()
                                            ->where('time_slot_id', $slot->id)
                                            ->where('start_date', '<=', $currentDate->format('Y-m-d'))
                                            ->where('end_date', '>=', $currentDate->format('Y-m-d'))
                                            ->where('is_active', true)
                                            ->latest('created_at')
                                            ->first();
                                        
                                        $adjustment = $customPricing ? $customPricing->custom_adjustment : 0;
                                        $nightPrice = $basePrice + $adjustment;
                                        $totalPrice += $nightPrice;
                                    @endphp
                                    <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-2">
                                        <div>
                                            <strong>Night {{ $i + 1 }}</strong><br>
                                            <small class="text-muted">
                                                {{ $currentDate->format('l, F j, Y') }}
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-muted small">
                                                {{ $isWeekend ? 'Weekend' : 'Weekday' }}: ${{ number_format($basePrice, 2) }}
                                                @if($adjustment != 0)
                                                    <br><span class="text-info">{{ $customPricing ? $customPricing->name : 'Custom' }}: ${{ number_format($adjustment, 2) }}</span>
                                                @endif
                                            </div>
                                            <strong>${{ number_format($nightPrice, 2) }}</strong>
                                        </div>
                                    </div>
                                    @php
                                        $currentDate->addDay();
                                    @endphp
                                @endfor
                            </div>
                        @endif
                        
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Subtotal:</strong><br>
                                    ${{ number_format($booking->base_slot_price, 2) }}
                                </p>
                                @if($booking->seasonal_adjustment != 0)
                                <p><strong>Seasonal Adjustment:</strong><br>
                                    ${{ number_format($booking->seasonal_adjustment, 2) }}
                                </p>
                                @endif
                                @if($booking->extra_hours_amount != 0)
                                <p><strong>Extra Hours:</strong><br>
                                    ${{ number_format($booking->extra_hours_amount, 2) }}
                                </p>
                                @endif
                                @if($booking->discount_percentage > 0)
                                <p><strong class="text-success">{{ $booking->discount_reason ?? 'Launch Promotion' }}:</strong><br>
                                    <span class="text-success">- ${{ number_format($booking->discount_amount, 2) }} ({{ $booking->discount_percentage }}% off)</span>
                                </p>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <p class="h5"><strong>Total Amount:</strong><br>
                                    <span class="text-success fw-bold">${{ number_format($booking->total_amount, 2) }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Instructions Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payment Instructions</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Important Payment Information</h6>
                            <p class="mb-2">To secure your booking, please complete the payment within <strong>30 minutes</strong>. Your booking will be automatically deleted if payment is not received within this timeframe.</p>
                            <p class="mb-0"><strong>Total Amount Due: ${{ number_format($booking->total_amount, 2) }}</strong></p>
                            @if($booking->discount_amount > 0)
                            <p class="mb-0 text-success"><strong><i class="fas fa-tags me-1"></i> You saved ${{ number_format($booking->discount_amount, 2) }} with our {{ $booking->discount_reason ?? 'Launch Promotion' }}!</strong></p>
                            @endif
                        </div>

                        <h6>Payment Methods:</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <img src="/assets/images/whish.jpg" alt="Whish Money" style="height:40px;width:auto;object-fit:contain;" class="me-3">
                                    <div>
                                        <h6 class="mb-1">Whish Money</h6>
                                        <p class="mb-0 small">
                                            Send payment to: <strong>{{ $settings->support_phone ?? '+961 70 123456' }}</strong><br>
                                            Include booking reference <strong>{{ $booking->booking_reference }}</strong> in the note
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <img src="/assets/images/omt.png" alt="OMT" style="height:40px;width:auto;object-fit:contain;" class="me-3">
                                    <div>
                                        <h6 class="mb-1">OMT</h6>
                                        <p class="mb-0 small">
                                            Send payment to: <strong>{{ $settings->support_phone ?? '+961 70 123456' }}</strong><br>
                                            Include booking reference <strong>{{ $booking->booking_reference }}</strong> in the note
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <h6 class="mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
                            <p class="mb-0">After sending payment, please take a screenshot of the payment confirmation and send it to <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->support_phone ?? '+96170123456') }}" target="_blank">WhatsApp</a> or email to <a href="mailto:{{ $settings->support_email ?? 'info@ehjozchalet.com' }}">{{ $settings->support_email ?? 'info@ehjozchalet.com' }}</a> with your booking reference.</p>
                        </div>

                        <!-- Online Payment (hidden for now) -->
                        <div style="display:none">
                            <h6>Online Payment:</h6>
                            <p>Click the button below to proceed with online payment:</p>
                            <button class="btn btn-primary btn-lg" onclick="processPayment()">
                                <i class="fas fa-credit-card me-2"></i>Pay Now - ${{ number_format($booking->total_amount, 2) }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Next Steps Card -->
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Next Steps</h5>
                    </div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li class="mb-2">Complete the payment using one of the methods above within 30 minutes</li>
                            <li class="mb-2">You will receive a payment confirmation email immediately</li>
                            <li class="mb-2">Your booking will be confirmed instantly upon payment</li>
                            <li class="mb-0">For any questions, contact us at <a href="mailto:{{ $settings->support_email ?? 'info@ehjozchalet.com' }}">{{ $settings->support_email ?? 'info@ehjozchalet.com' }}</a></li>
                        </ol>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center mt-4">
                    <a href="{{ route('index') }}" class="btn btn-outline-primary me-2">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                    <a href="{{ route('chalets') }}" class="btn btn-outline-success me-2">
                        <i class="fas fa-search me-2"></i>Browse More Chalets
                    </a>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Confirmation
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function processPayment() {
    // This would integrate with your payment gateway
    alert('Payment gateway integration would go here. For now, please use bank transfer.');
}
</script>

<style>
@media print {
    .btn, .breadcrumb, .alert {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
}
</style>
@endsection
