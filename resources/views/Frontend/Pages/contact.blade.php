@extends('Frontend.Layouts.app')
@section('page_title', 'Hotel and Resort Laravel 12 Template')

@section('content')
    @include('Frontend.Header.header')
    
    @php 
        $title = "Contact Us";
        $desc = "Whether you have questions, need assistance, or simply want to share.";
    @endphp
    @include('Frontend.Components.page-hero',compact('title','desc'))
    
    <!-- contact area -->
    <div class="rts__section section__padding">
        <div class="container">
            <div class="row g-30 align-items-center">
                <div class="col-lg-6">
                    <div class="rts__contact">
                        <span class="h4 d-block mb-30 text-center">Love to hear from you
                            Get in touch!</span>
                        <form action="mailer.php" method="post" class="rts__contact__form" id="contact-form">
                            <div class="form-input">
                                <label for="name">Your Name</label>
                                <div class="pr">
                                    <input type="text" id="name" name="name" placeholder="Your Name" required>
                                    <i class="flaticon-user"></i>
                                </div>
                            </div>
                            <div class="form-input">
                                <label for="email">Your Email</label>
                                <div class="pr">
                                    <input type="email" id="email" name="email" placeholder="Your Email" required>
                                    <i class="flaticon-envelope"></i>
                                </div>
                            </div>
                            <div class="form-input">
                                <label for="msg">Your Message</label>
                                <div class="pr">
                                    <textarea id="msg" name="msg" placeholder="Message" required></textarea>
                                    <img src="{{asset('assets/images/icon/message.svg')}}" width="20" height="20" alt="">
                                </div>
                            </div>
                            <button type="submit" class="theme-btn btn-style fill w-100"><span>Send Message</span></button>
                        </form>
                        <div id="form-messages" class="mt-20"></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="contact__image">
                        <img class="rounded-2 w-100 img-fluid" src="{{asset('assets/images/pages/contact.webp')}}" width="645" height="560" alt="contact__image">
                    </div>
                </div>
            </div>
        </div>
        <div class="container pt-120">
            <div class="row g-30 align-items-center">
                <div class="col-lg-6">
                    <div class="contact__map">
                        <!-- <iframe class="w-100" height="560"  src="https://maps.google.com/maps?width=100%25&amp;height=600&amp;hl=en&amp;q=phuket+(My%20Business%20Name)&amp;t=&amp;z=14&amp;ie=UTF8&amp;iwloc=B&amp;output=embed"></iframe> -->
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="contact__info">

                        <div class="contact__info__item">
                            <h4>Hotel Info Center</h4>
                            <p>
                                Open Hours: Monday – Sunday <br>
                                Telephone: +12505550199 <br>
                                Fax: +12505550199 <br>
                                Email: info@moonlit.com
                            </p>
                        </div>
                        <div class="contact__info__item">
                            <h4>Hotel location</h4>
                            <p>
                                Address: The Ritz-Carlton (California, USA) <br>
                                Telephone: +12505550199 <br>
                                Fax: +12505550199 <br>
                                Email: info@moonlit.com
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- contact area end -->
    @include('Frontend.Footer.footer__common')
@endsection
