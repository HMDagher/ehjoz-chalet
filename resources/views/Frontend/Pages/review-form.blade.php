@extends('Frontend.Layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Leave a Review</h4>
                </div>
                <div class="card-body">
                    @if(isset($review->chalet))
                        <div class="mb-3">
                            <strong>Chalet:</strong> {{ $review->chalet->name ?? 'N/A' }}<br>
                        </div>
                    @endif
                    <form method="POST" action="{{ url()->current() }}">
                        @csrf
                        <div class="mb-3">
                            <label for="overall_rating" class="form-label">Overall Rating <span class="text-danger">*</span></label>
                            <select name="overall_rating" id="overall_rating" class="form-select" required>
                                <option value="">Select</option>
                                @for($i=1; $i<=5; $i++)
                                    <option value="{{ $i }}" {{ old('overall_rating', $review->overall_rating) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                            @error('overall_rating')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="cleanliness_rating" class="form-label">Cleanliness</label>
                            <select name="cleanliness_rating" id="cleanliness_rating" class="form-select">
                                <option value="">Select</option>
                                @for($i=1; $i<=5; $i++)
                                    <option value="{{ $i }}" {{ old('cleanliness_rating', $review->cleanliness_rating) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                            @error('cleanliness_rating')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="location_rating" class="form-label">Location</label>
                            <select name="location_rating" id="location_rating" class="form-select">
                                <option value="">Select</option>
                                @for($i=1; $i<=5; $i++)
                                    <option value="{{ $i }}" {{ old('location_rating', $review->location_rating) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                            @error('location_rating')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="value_rating" class="form-label">Value</label>
                            <select name="value_rating" id="value_rating" class="form-select">
                                <option value="">Select</option>
                                @for($i=1; $i<=5; $i++)
                                    <option value="{{ $i }}" {{ old('value_rating', $review->value_rating) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                            @error('value_rating')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="communication_rating" class="form-label">Communication</label>
                            <select name="communication_rating" id="communication_rating" class="form-select">
                                <option value="">Select</option>
                                @for($i=1; $i<=5; $i++)
                                    <option value="{{ $i }}" {{ old('communication_rating', $review->communication_rating) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                            @error('communication_rating')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comment</label>
                            <textarea name="comment" id="comment" class="form-control" rows="4" maxlength="2000">{{ old('comment', $review->comment) }}</textarea>
                            @error('comment')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Review</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 