@extends('layouts.app')

@section('content')
    <div class="container-fluid full-page">

        <!-- start of page title section -->
        <div class="row text-center mt-4">
            <div class="page-title">
                {{ $story->name }}
            </div>
        </div>
        <!-- end of page title section -->

        <!-- Story information section -->
        <div class="row mt-4 justify-content-center">
            <div class="col-md-12">
                <div class="list-title py-3 pe-4 mb-4">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="me-3">معلومات القصة</h4>
                        </div>
                        <div class="col-md-4 text-left d-flex justify-content-end">
                            <a href="{{ route('ai-stories.index') }}"
                                class="btn btn-secondary rounded-pill shadow-sm px-4 py-2">
                                <i class="fas fa-arrow-right ms-2"></i>
                                <span>العودة للقائمة</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center mb-5">
            <div class="col-md-12">
                <div class="list-content py-4 pe-4">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <img src="{{ asset('storage/ai_stories/' . $story->cover_photo) }}"
                                class="img-fluid rounded story-cover" alt="{{ $story->name }}" style="max-height: 300px;"
                                onerror="this.src='{{ asset('storage/img/placeholder.svg') }}'; this.onerror='';">
                        </div>
                        <div class="col-md-8">
                            <table class="table table-hover">
                                <tr>
                                    <th class="table-secondary" width="30%">العنوان:</th>
                                    <td>{{ $story->name }}</td>
                                </tr>
                                <tr>
                                    <th class="table-secondary">اسم البطل:</th>
                                    <td>{{ $story->hero_name }}</td>
                                </tr>
                                <tr>
                                    <th class="table-secondary">موضوع القصة:</th>
                                    <td>{{ $story->story_topic }}</td>
                                </tr>
                                <tr>
                                    <th class="table-secondary">النمط الفني:</th>
                                    <td>{{ $story->painting_style }}</td>
                                </tr>
                                <tr>
                                    <th class="table-secondary">الحالة:</th>
                                    <td><span class="badge bg-success">{{ $story->status }}</span></td>
                                </tr>
                                <tr>
                                    <th class="table-secondary">تاريخ الإنشاء:</th>
                                    <td>{{ $story->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slides Section -->

        <div class="row">
            <div class="col-md-12">
                <div class="list-title py-3 pe-4 mb-4">
                    <h4 class="me-3">شرائح القصة</h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="list-content py-4 pe-4 mb-4">
                    <div class="row">
                        <!-- Left column - Slide thumbnails -->
                        <div class="col-md-4" style="border-left: 1px solid #ddd;">
                            <div class="slides-part">
                                <div class="slides" style="max-height: 550px; overflow-y: auto;">
                                    <!-- Include the JavaScript file and pass the slides data -->
                                    <script>
                                        var slides = @json($story->slides);
                                        var currentSlide = 0;

                                        function showSlide(index) {
                                            currentSlide = index;

                                            // Update active class
                                            document.querySelectorAll('.card_slide').forEach(card => {
                                                card.classList.remove('active');
                                            });
                                            document.getElementById('card_slide_' + index).classList.add('active');

                                            // Update main content
                                            const slide = slides[index];
                                            document.getElementById('main-slide-image').src = '/storage/ai_stories/' + slide.image;
                                            document.getElementById('main-slide-image').onerror = function () {
                                                this.src = '{{ asset("storage/img/placeholder.svg") }}';
                                                this.onerror = '';
                                            };
                                            document.getElementById('main-slide-text').innerText = slide.text;
                                            document.getElementById('main-slide-page').innerText =
                                                'الصفحة ' + slide.page_no + (slide.page_no === 0 ? ' (الغلاف)' : '');
                                        }
                                    </script>

                                    <div class="slides-container">
                                        @foreach ($story->slides as $index => $slide)
                                            <div class="card_slide card shadow-sm mb-3 {{ $index === 0 ? 'active' : '' }}"
                                                id="card_slide_{{ $index }}" onclick="showSlide({{ $index }})"
                                                style="cursor: pointer; border-radius: 8px; overflow: hidden;">
                                                <div class="row px-1 py-2 align-items-center">
                                                    <div class="col-4 card-image my-1 p-0 pe-2">
                                                        <img id="thumb-image-{{ $slide->id }}"
                                                            src="{{ asset('storage/ai_stories/' . $slide->image) }}"
                                                            class="img-fluid rounded" alt="صورة {{  $slide->page_no }}"
                                                            onerror="this.src='{{ asset('storage/img/placeholder.svg') }}'; this.onerror='';">
                                                    </div>
                                                    <div class="col-8 pe-3 card-text">
                                                        <small class="text-muted">صفحة
                                                            {{ $slide->page_no }}{{ $slide->page_no === 0 ? ' (الغلاف)' : '' }}</small>
                                                        <p class="mb-0 small" style="max-height: 40px; overflow: hidden;">
                                                            {{ \Illuminate\Support\Str::limit($slide->text, 50) }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right column - Current slide details -->
                        <div class="col-md-8">
                            <div class="view-slide">
                                @if(count($story->slides) > 0)
                                    <!-- Display selected slide -->
                                    <div class="card shadow-sm">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <h5 class="m-0" id="main-slide-page">
                                                الصفحة
                                                {{ $story->slides[0]->page_no }}{{ $story->slides[0]->page_no === 0 ? ' (الغلاف)' : '' }}
                                            </h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="row image p-0">
                                                <img id="main-slide-image"
                                                    src="{{ asset('storage/ai_stories/' . $story->slides[0]->image) }}"
                                                    class="img-fluid w-100" alt="صورة القصة"
                                                    style="max-height: 400px; object-fit: contain;">
                                            </div>

                                            <div class="row text align-items-center py-4 px-4">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <p id="main-slide-text" class="mb-0 text-right">
                                                                {{ $story->slides[0]->text }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="alert alert-info text-center">
                                        لا توجد شرائح لهذه القصة.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .story-cover {
            max-height: 350px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
        }

        .card_slide {
            transition: all 0.3s ease;
            border-right: 3px solid #ddd;
        }

        .card_slide.active {
            border-right: 3px solid #E86565;
            background-color: #fff9f9;
        }

        .card_slide:hover:not(.active) {
            transform: translateX(-5px);
            border-right-color: #faa;
        }

        .slides-part {
            position: relative;
        }

        .slides {
            direction: rtl;
            padding: 10px;
        }

        .view-slide {
            background-color: #fff;
            border-radius: 8px;
        }

        /* Scrollbar styling */
        .slides::-webkit-scrollbar {
            width: 8px;
        }

        .slides::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .slides::-webkit-scrollbar-thumb {
            background: #E86565;
            border-radius: 10px;
        }

        .slides::-webkit-scrollbar-thumb:hover {
            background: #d45454;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize the first slide as active
            if (slides.length > 0) {
                showSlide(0);
            }
        });
    </script>
@endsection
