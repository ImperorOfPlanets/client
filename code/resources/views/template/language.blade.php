<button class="navbar-toggler btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#languageModal">
    <span class="fi fi-globe">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/>
            <path d="M3.172 12C4.908 5.214 9.658 2 12 2c5.514 0 8.274 7.102 4.852 11m-5.515-3.099A9.01 9.01 0 0 1 2 12"/>
        </svg>
    </span>
</button>

<div class="modal fade" id="languageModal" tabindex="-1" aria-labelledby="languageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="languageModalLabel">{{ __('Выберите язык') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <a href="/locale/ru" class="list-group-item list-group-item-action d-flex align-items-center">
                        <span class="fi fi-ru me-2"></span> Русский
                    </a>
                    <a href="/locale/cn" class="list-group-item list-group-item-action d-flex align-items-center">
                        <span class="fi fi-cn me-2"></span> 中国人
                    </a>
                    <a href="/locale/en" class="list-group-item list-group-item-action d-flex align-items-center">
                        <span class="fi fi-eu me-2"></span> English
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
   