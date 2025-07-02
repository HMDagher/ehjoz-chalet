@component('mail::message')
# Booking Confirmation

Dear {{ $booking->user->name }},

Thank you for your booking at {{ $booking->chalet->name }}!

**Booking Reference:** {{ $booking->booking_reference }}

Your booking has been received and is currently **pending**. To secure your reservation, please complete the payment within **30 minutes**. If payment is not received in this timeframe, your booking will be automatically deleted.

## Payment Instructions

You can pay using **Whish Money** or **OMT**:

- **Send payment to:** <strong>{{ $settings->support_phone ?? '+961 70 123456' }}</strong>
- **Include booking reference:** <strong>{{ $booking->booking_reference }}</strong> in the note

@component('mail::panel')
After sending payment, please take a screenshot of the payment confirmation and send it to WhatsApp ({{ $settings->support_phone ?? '+961 70 123456' }}) or email ({{ $settings->support_email ?? 'info@ehjozchalet.com' }}) with your booking reference.
@endcomponent

**Total Amount Due:** ${{ number_format($booking->total_amount, 2) }}

If you have any questions, reply to this email or contact us at {{ $settings->support_email ?? 'info@ehjozchalet.com' }}.

Thanks for choosing us!
@endcomponent 