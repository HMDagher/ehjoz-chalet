<div class="room__card is__style__three">
    <div class="room__card__top">
        <div class="room__card__image">
            <a href="{{ route('chalet-details', $chalet_slug) }}">
                <img src="{{ $thumb }}" width="645" height="415" alt="{{ $title ?? 'Chalet Image' }}">
            </a>
        </div>
        <div class="room__price__tag">
            <span class="h6 d-block">{{$price ?? ''}}</span>
        </div>
    </div>
    <div class="room__card__meta">
        <a href="{{route('chalet-details', $chalet_slug)}}" class="room__card__title h4">{{$title ?? ''}}</a>
        <div class="room__card__meta__info">
            <span><i class="flaticon-construction"></i>35 sqm</span>
            <span><i class="flaticon-user"></i>5 Person</span>
        </div>
        <p class="font-sm">{{$desc ?? ''}}</p>

        @if(!empty($slots))
        <ul class="list-unstyled mb-0 mt-3">
            @foreach($slots as $slot)
                <li class="py-2 px-3 rounded bg-light d-flex align-items-center border mb-2">
                    <div class="text-dark">
                        <p class="mb-0 fw-bold small">{{ $slot['name'] }} 
                            @if(isset($slot['start_time']))
                            <span class="small">({{ \Carbon\Carbon::parse($slot['start_time'])->format('g:i A') }} - {{ \Carbon\Carbon::parse($slot['end_time'])->format('g:i A') }}, {{ $slot['duration_hours'] }} hrs)</span>
                            @endif
                        </p>
                    </div>
                    <span class="ms-auto small mb-0 text-dark fw-bold">
                        {{ number_format($slot['price']) }}$
                    </span>
                </li>
            @endforeach
        </ul>
        @endif
    </div>
</div>