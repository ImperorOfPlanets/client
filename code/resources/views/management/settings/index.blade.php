@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
    <div id="settingsTab" class="d-flex align-items-start">
        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
            <button data-url="/management/settings/basic" class="nav-link active" id="v-pills-basic-tab" data-bs-toggle="pill" data-bs-target="#v-pills-basic" type="button" role="tab" aria-controls="v-pills-basic" aria-selected="true">
                Базовые
            </button>
            <button data-url="/management/settings/socials" class="nav-link" id="v-pills-socials-tab" data-bs-toggle="pill" data-bs-target="#v-pills-socials" type="button" role="tab" aria-controls="v-pills-socials" aria-selected="false">
                Соц сети
            </button>
        </div>
        <div class="tab-content w-100 p-2" id="v-pills-tabContent">
            <div class="tab-pane fade show active" id="v-pills-basic" role="tabpanel" aria-labelledby="v-pills-basic-tab" tabindex="0">
                @include('management.settings.basic.index')
            </div>
            <div class="tab-pane fade" id="v-pills-socials" role="tabpanel" aria-labelledby="v-pills-socials-tab" tabindex="0">
                include('management.settings.socials.index')
            </div>
        </div>
    </div>
@endsection