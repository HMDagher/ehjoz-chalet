<div class="card rts__card {{ $class ?? '' }}">
    <div class="card-body">
        <div class="icon">
            <img src="{{ asset($icon ?? '') }}" alt="">
        </div>
        <a href="{{ $link ?? '#' }}">
            <h6 class="card-title h6 mb-15">{{ $title ?? '' }}</h6>
        </a>
        <p class="card-text">{{ $desc ?? '' }}</p>
    </div>
</div>
