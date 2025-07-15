@extends('Frontend.Layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-danger text-center shadow">
                <h4 class="mb-3">Invalid or Expired Link</h4>
                <p>This review link is invalid, expired, or has already been used. If you believe this is a mistake, please contact support.</p>
            </div>
        </div>
    </div>
</div>
@endsection 