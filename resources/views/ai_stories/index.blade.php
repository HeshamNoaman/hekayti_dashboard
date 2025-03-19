@extends('layouts.app')

@section('content')
    <div class="container-fluid full-page">
        <!-- start of loading overlay element -->
        <div id="loading-overlay">
            <div class="spinner"></div>
        </div>
        <!-- end of loading overlay element -->

        <!-- start of page title section -->
        <div class="row text-center mt-4">
            <div class="page-title">
                القصص المولدة بالذكاء الاصطناعي
            </div>
        </div>
        <!-- end of page title section -->

        <div class="row mt-4 d-flex justify-content-center align-items-center">
            @if (session('success'))
                <div class="col-md-9">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="col-md-9">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            @endif
        </div>

        <!-- start of stories card section -->
        <div class="row mt-4 justify-content-center">
            <div class="cards text-center" id="cards_container">
                @if ($stories->isEmpty())
                    <!-- display a message if there is no story  -->
                    <div class="container text-center">
                        <h1 class="mb-0 pb-0 pt-5 text-muted">لم يتم إنشاء أي قصص بالذكاء الاصطناعي حتى الآن !!</h1>
                        <img src="{{ asset('storage/upload/No_data.svg') }}" class="img-fluid w-75 w">
                    </div>
                @else
                    <!-- display all stories -->
                    @foreach ($stories as $story)
                        <div class="out-card m-2">
                            <div class="card card-story">
                                <img src="{{ asset('storage/ai_stories/' . $story->cover_photo) }}" class="img-fluid rounded"
                                    alt="{{ $story->name }}"
                                    onerror="this.src='{{ asset('storage/img/placeholder.svg') }}'; this.onerror='';">
                                <a href="{{ route('ai-stories.show', $story->id) }}" title="{{ $story->name }}"
                                    class="hover-background"></a>

                                <ul class="story-links">
                                    <!-- delete icon -->
                                    <li>
                                        <a class="shadow" id="delete_icon"
                                            onclick="deletePopup({{ $story->id }},'delete_ai_story','story_id')"
                                            data-tip="حذف القصة">
                                            <i class="fa fa-trash-can"></i>
                                        </a>
                                    </li>

                                    <!-- view details icon -->
                                    <li>
                                        <a class="shadow" id="view_icon" href="{{ route('ai-stories.show', $story->id) }}"
                                            data-tip="عرض التفاصيل">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </li>
                                </ul>

                                <!-- show story name and hero name -->
                                <div class="card-story-body">
                                    <h4 class="card-story-title text-center pt-3">
                                        {{ \Illuminate\Support\Str::limit($story->name, 30) }}
                                    </h4>
                                    <h6 class="card-story-text text-center pb-2">
                                        {{ \Illuminate\Support\Str::limit($story->hero_name, 18) }}
                                    </h6>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
        <!-- end of stories card section -->

        <!-- add story btn -->
        <a class="btn overflow-visible add-btn text-white shadow" href="{{ route('ai-stories.create') }}" role="button">
            <i class="fa fa-add"></i>
            <span style="display: none;">
                إنشاء قصة جديدة
            </span>
        </a>
    </div>

    {{-- delete popup --}}
    <div class="modal fade" tabindex="-1" id="delete_ai_story" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">حذف قصة</h5>
                    <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('ai-stories.destroy') }}" method="POST" id="delete_ai_story_form">
                    @csrf
                    <input type="hidden" name="story_id" id="story_id" value="">
                    <div class="modal-body">
                        <p class="text-center delete-text">هل أنت متأكد من حذف هذه القصة؟</p>
                    </div>
                    <div class="modal-footer justify-content-evenly">
                        <button type="submit" class="btn save">حذف</button>
                        <button type="button" class="btn btn-secondary cancel" data-bs-dismiss="modal">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Function to handle story deletion popup
        function deletePopup(id, modal_id, input_id) {
            var url = $('#delete_ai_story_form').attr('action');
            url = url.replace(':id', id);
            $('#delete_ai_story_form').attr('action', url);
            $('#' + input_id).val(id);
            $('#' + modal_id).modal('show');
        }
    </script>
@endsection
