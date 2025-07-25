    <!-- page header -->
    <div class="rts__section page__hero__height page__hero__bg no__shadow" style="background-image: url({{ $headerImage ?? asset('assets/images/pages/header__bg.webp') }});">
        <div class="container">
            <div class="row align-items-center justify-content-center">
                <div class="col-lg-12">
                    <div class="page__hero__content visually-hidden">
                        <h1 class="wow fadeInUp">{{$title ?? ''}}</h1>
                        <p class="wow fadeInUp font-sm">{{$desc ?? ''}}</p>
                    </div>
                </div>
            </div>
        </div>
     </div>
    <!-- page header end -->