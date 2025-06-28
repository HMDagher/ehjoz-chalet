<div class="section__topbar">
    <span class="h6 subtitle__icon__four mx-auto">{{$top ?? ''}}</span>
    <h2 class="section__title mb-20">{{$title ?? ''}}</h2>
    @if($subtitle ?? false)
    <span class="font-sm">{{$subtitle ?? ''}}</span>
    @endif
</div>