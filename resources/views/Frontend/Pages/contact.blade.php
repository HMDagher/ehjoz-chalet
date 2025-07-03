@extends('Frontend.Layouts.app')
@section('page_title', 'Contact Us | Lebanon Chalet Booking Support & Inquiries')

@section('content')
    @include('Frontend.Header.header')
    
    @php 
        $title = "Contact Us";
        $desc = "Have questions about booking a chalet in Lebanon? Need assistance or want to share your feedback? Our team is here to help you with all your chalet rental inquiries.";
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
                        @if(session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <form action="{{ route('contact.send') }}" method="post" class="rts__contact__form" id="contact-form">
                            @csrf
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
    </div>
    <!-- contact area end -->
    @include('Frontend.Footer.footer__common')
@endsection
