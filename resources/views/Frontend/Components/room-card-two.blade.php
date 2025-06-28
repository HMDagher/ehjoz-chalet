<div class="room__card">
    <div class="room__card__top">
        <div class="room__card__image">
            <a href="{{route('room-details-2')}}">
                <img src="{{asset('assets/images/pages/room/' . ($thumb ?? ''))}}" width="420" height="310" alt="room card">
            </a>
        </div>
    </div>
    <div class="room__card__meta">
        <a href="{{route('room-details-2')}}" class="room__card__title h5">{{$title ?? ''}}</a>
        <div class="room__card__meta__info">
            <span><i class="flaticon-construction"></i>35 sqm</span>
            <span><i class="flaticon-user"></i>5 Person</span>
        </div>
        <div class="room__price__tag">
            <span class="h6 d-block">{{$price ?? ''}}</span>
        </div>
        <a href="{{route('room-details-2')}}" class="room__card__link">Discover More</a>

    </div>
</div>