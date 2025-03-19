@extends('layouts.app')

@section('content')
    <div class="container-fluid full-page">
        <!-- start of page title section -->
        <div class="row text-center mt-4">
            <div class="page-title">
                إنشاء قصة جديدة بالذكاء الاصطناعي
            </div>
        </div>
        <!-- end of page title section -->

        <div class="row mt-4 justify-content-center">
            <div class="col-md-8">
                <div class="list-title py-3 pe-4 mb-4">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="me-3">إدخال بيانات القصة</h4>
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

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="list-content py-4 pe-4 mb-4">
                    <div class="alert alert-info">
                        <strong>ملاحظة:</strong> قد يستغرق إنشاء قصة جديدة ما يصل إلى 3-5 دقائق حيث يتضمن
                        عدة طلبات لواجهات برمجة التطبيقات لتوليد النصوص والصور. يرجى عدم إغلاق الصفحة أو تحديثها أثناء
                        المعالجة.
                    </div>

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('ai-stories.store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <label for="hero_name" class="form-label">اسم البطل</label>
                                <input type="text" class="form-control @error('hero_name') is-invalid @enderror"
                                    id="hero_name" name="hero_name" value="{{ old('hero_name') }}" required>
                                @error('hero_name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <div class="form-text">أدخل اسم الشخصية الرئيسية (مثال: "سارة أحمد"، "محمد علي")</div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label for="painting_style" class="form-label">النمط الفني</label>
                                <select class="form-select @error('painting_style') is-invalid @enderror"
                                    id="painting_style" name="painting_style" required>
                                    <option value="" disabled {{ !old('painting_style') ? 'selected' : '' }}>اختر النمط
                                        الفني للرسومات</option>
                                    <option value="رسوم مائية" {{ old('painting_style') == 'رسوم مائية' ? 'selected' : '' }}>
                                        رسوم مائية</option>
                                    <option value="رسوم كرتونية" {{ old('painting_style') == 'رسوم كرتونية' ? 'selected' : '' }}>رسوم كرتونية</option>
                                    <option value="رسوم زيتية" {{ old('painting_style') == 'رسوم زيتية' ? 'selected' : '' }}>
                                        رسوم زيتية</option>
                                    <option value="رسوم ديزني" {{ old('painting_style') == 'رسوم ديزني' ? 'selected' : '' }}>
                                        رسوم ديزني</option>
                                    <option value="رسوم بيكسار" {{ old('painting_style') == 'رسوم بيكسار' ? 'selected' : '' }}>رسوم بيكسار</option>
                                </select>
                                @error('painting_style')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <div class="form-text">حدد النمط الفني للرسومات التي سيتم إنشاؤها</div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label for="story_topic" class="form-label">موضوع القصة</label>
                                <select class="form-select @error('story_topic') is-invalid @enderror" id="story_topic"
                                    name="story_topic" required>
                                    <option value="" disabled {{ !old('story_topic') ? 'selected' : '' }}>اختر موضوع القصة
                                    </option>
                                    <option value="عالم البحار" {{ old('story_topic') == 'عالم البحار' ? 'selected' : '' }}>
                                        عالم البحار</option>
                                    <option value="الفضاء" {{ old('story_topic') == 'الفضاء' ? 'selected' : '' }}>الفضاء
                                    </option>
                                    <option value="الغابة" {{ old('story_topic') == 'الغابة' ? 'selected' : '' }}>الغابة
                                    </option>
                                    <option value="المدرسة" {{ old('story_topic') == 'المدرسة' ? 'selected' : '' }}>المدرسة
                                    </option>
                                    <option value="المستقبل" {{ old('story_topic') == 'المستقبل' ? 'selected' : '' }}>المستقبل
                                    </option>
                                    <option value="التاريخ" {{ old('story_topic') == 'التاريخ' ? 'selected' : '' }}>التاريخ
                                    </option>
                                    <option value="المغامرات" {{ old('story_topic') == 'المغامرات' ? 'selected' : '' }}>
                                        المغامرات</option>
                                </select>
                                @error('story_topic')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <div class="form-text">حدد الموضوع الرئيسي للقصة</div>
                            </div>
                        </div>

                        <div class="modal-footer justify-content-evenly mt-4">
                            <button type="submit" class="save btn" onclick="this.disabled=true;this.form.submit();">
                                <span class="spinner-border spinner-border-sm d-none" role="status"
                                    aria-hidden="true"></span>
                                إنشاء القصة
                            </button>
                            <a href="{{ route('ai-stories.index') }}" class="cancel btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            const submitButton = form.querySelector('button[type="submit"]');
            const spinner = submitButton.querySelector('.spinner-border');

            form.addEventListener('submit', function () {
                submitButton.disabled = true;
                spinner.classList.remove('d-none');
            });
        });
    </script>
@endsection
