<div class="room__card is__style__four">
    <div class="room__card__top">
        <div class="room__card__image">
            <a href="#">
                <img src="{{asset('assets/images/pages/room/' . ($thumb ?? ''))}}" width="645" height="438" alt="room card">
            </a>
        </div>
    </div>
    <div class="room__card__meta">
        <a href="#" class="room__card__title h4">{{$title ?? ''}}</a>
        <div class="room__card__meta__info">
            <span><i class="flaticon-construction"></i>Size: 35 sqm</span>
            <span><i class="flaticon-user"></i>Person: 5 Person</span>
        </div>
        <p class="font-sm">{{$desc ?? ''}}</p>
        <div class="room__price__tag">
            <span class="h5 d-block">{{$price ?? ''}}</span>
        </div>
        <a href="#" class="room__card__link">Discover More</a>

    </div>
</div>