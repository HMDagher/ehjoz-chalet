@component('mail::message')
# Payment Update

Dear {{ $customer->name }},

@if($payment->status === 'paid')
## Payment Complete - Booking Confirmed! 🎉

We have received your **full payment** for your booking at {{ $booking->chalet->name }}.

**Booking Reference:** {{ $booking->booking_reference }}

- **Amount Paid:** ${{ number_format($payment->amount, 2) }}
- **Total Amount:** ${{ number_format($booking->total_amount, 2) }}
- **Remaining Amount:** $0.00

Your booking is now **fully confirmed** and ready for your stay!

@elseif($payment->status === 'partial')
## Partial Payment Received - Booking Confirmed! ✅

We have received a **partial payment** for your booking at {{ $booking->chalet->name }}.

**Booking Reference:** {{ $booking->booking_reference }}

- **Amount Paid:** ${{ number_format($payment->amount, 2) }}
- **Total Amount:** ${{ number_format($booking->total_amount, 2) }}
- **Remaining Amount:** ${{ number_format($booking->total_amount - $payment->amount, 2) }}

Your booking status is now **confirmed**.

@component('mail::panel')
Please pay the remaining amount of **${{ number_format($booking->total_amount - $payment->amount, 2) }}** upon arrival at the chalet.
@endcomponent

@elseif($payment->status === 'pending')
## Payment Pending - Action Required ⏳

Your payment for booking at {{ $booking->chalet->name }} is currently **pending**.

**Booking Reference:** {{ $booking->booking_reference }}

- **Amount Expected:** ${{ number_format($payment->amount, 2) }}
- **Total Amount:** ${{ number_format($booking->total_amount, 2) }}

@component('mail::panel')
Please complete your payment to confirm your booking. You can pay using Whish Money or OMT to {{ $settings->support_phone ?? '+961 70 123456' }}.
@endcomponent

@elseif($payment->status === 'refunded')
## Payment Refunded - Booking Updated 🔄

Your payment for booking at {{ $booking->chalet->name }} has been **refunded**.

**Booking Reference:** {{ $booking->booking_reference }}

- **Amount Refunded:** ${{ number_format($payment->amount, 2) }}
- **Total Amount:** ${{ number_format($booking->total_amount, 2) }}

@component('mail::panel')
If you have any questions about this refund, please contact us at {{ $settings->support_email ?? 'info@ehjozchalet.com' }}.
@endcomponent

@else
## Payment Update

Your payment status for booking at {{ $booking->chalet->name }} has been updated.

**Booking Reference:** {{ $booking->booking_reference }}

- **Payment Status:** {{ ucfirst($payment->status) }}
- **Amount:** ${{ number_format($payment->amount, 2) }}
- **Total Amount:** ${{ number_format($booking->total_amount, 2) }}

@endif

**Payment Method:** {{ ucfirst($payment->payment_method) }}
**Payment Reference:** {{ $payment->payment_reference }}
**Payment Date:** {{ \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y H:i') }}

@if($payment->notes)
**Payment Notes:** {{ $payment->notes }}
@endif

---

@component('mail::message')
# Guest Payment Notification

Dear {{ $owner->name }},

Your guest {{ $customer->name }} has made a payment for booking {{ $booking->booking_reference }} at your chalet ({{ $booking->chalet->name }}).

**Payment Details:**
- **Payment Status:** {{ ucfirst($payment->status) }}
- **Amount:** ${{ number_format($payment->amount, 2) }}
- **Total Amount:** ${{ number_format($booking->total_amount, 2) }}
@if($payment->status === 'partial')
- **Remaining Amount:** ${{ number_format($booking->total_amount - $payment->amount, 2) }}
@endif
- **Payment Method:** {{ ucfirst($payment->payment_method) }}
- **Payment Reference:** {{ $payment->payment_reference }}

@if($payment->status === 'paid')
The booking is now **fully confirmed** and paid.
@elseif($payment->status === 'partial')
The booking is **confirmed** with partial payment. The guest will pay the remaining amount upon arrival.
@elseif($payment->status === 'pending')
The payment is **pending**. The booking will be confirmed once payment is received.
@elseif($payment->status === 'refunded')
The payment has been **refunded**. Please check with the guest for any issues.
@endif

@endcomponent 