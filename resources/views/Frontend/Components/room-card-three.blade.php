<div class="room__card is__style__three">
    <div class="room__card__top">
        <div class="room__card__image">
            <a href="{{ route('chalet-details', $chalet_slug) }}">
                <img src="{{ $thumb }}" width="645" height="415" alt="{{ $title ?? 'Chalet Image' }}">
            </a>
        </div>
    </div>
    <div class="room__card__meta">
        <a href="{{route('chalet-details', $chalet_slug)}}" class="room__card__title h4">Chalet</a>
        <div class="room__card__meta__info">
            @if(isset($max_adults) && $max_adults > 0)
            <span><i class="flaticon-user"></i>{{ $max_adults }} {{ Str::plural('Adult', $max_adults) }}</span>
            @endif
            @if(isset($max_children) && $max_children > 0)
            <span><i class="flaticon-user"></i>{{ $max_children }} {{ Str::plural('Child', $max_children) }}</span>
            @endif
        </div>
        <div class="font-sm">{!! $desc ?? '' !!}</div>

{{--        @if(!empty($slots))--}}
{{--        <ul class="list-unstyled mb-0 mt-3">--}}
{{--            @foreach($slots as $slot)--}}
{{--                <li class="py-2 px-3 rounded bg-light d-flex align-items-center border mb-2">--}}
{{--                    <div class="text-dark">--}}
{{--                        <p class="mb-0 fw-bold small">--}}
{{--                            {{ $slot['name'] ?? 'Time Slot' }}--}}
{{--                            @if(isset($slot['start_time']))--}}
{{--                            <span class="small">({{ \Carbon\Carbon::parse($slot['start_time'])->format('g:i A') }} - {{ \Carbon\Carbon::parse($slot['end_time'])->format('g:i A') }}, {{ $slot['duration_hours'] }} hrs)</span>--}}
{{--                            @endif--}}

{{--                            @if(isset($slot['nights']))--}}
{{--                            <span class="small">({{ $slot['nights'] }} {{ Str::plural('night', $slot['nights']) }})</span>--}}
{{--                            @endif--}}
{{--                        </p>--}}
{{--                    </div>--}}
{{--                    <span class="ms-auto small mb-0 text-dark fw-bold">--}}
{{--                        @if(array_key_exists('price', $slot) && $slot['price'])--}}
{{--                            {{ number_format($slot['price']) }}$--}}
{{--                        @elseif(array_key_exists('price_per_night', $slot) && $slot['price_per_night'])--}}
{{--                            {{ number_format($slot['price_per_night']) }}$ / night--}}
{{--                        @elseif(array_key_exists('total_price', $slot) && $slot['total_price'])--}}
{{--                            {{ number_format($slot['total_price']) }}$ total--}}
{{--                        @else--}}
{{--                            Price on request--}}
{{--                        @endif--}}
{{--                    </span>--}}
{{--                </li>--}}
{{--            @endforeach--}}
{{--        </ul>--}}
{{--        @endif--}}
    </div>
</div>
