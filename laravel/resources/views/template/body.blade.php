<body>
<div class="wrapper d-flex align-items-stretch">
	<nav id="sidebar">
		<div class="p-2 pt-2">
			<ul class="list-unstyled components mb-5">
				@include('template.sidebar')
			</ul>
			<div class="footer">
				<div class="d-grid gap-2 btn-install">
					<button type="button" class="btn btn-primary d-none" data-action="installApp">Установить приложение</button>
				</div>
			</div>
		</div>
	</nav>
	<!-- Page Content  -->
	<div id="content">
		<nav class="navbar bg-body-tertiary">
			<div class="container-fluid">
				<button type="button" id="sidebarCollapse" class="btn btn-primary">
					{{__('Menu')}}
				</button>
				@include('template.language')
			</div>
		</nav>
		<div>
			@yield('content')
		</div>
	</div>
	<!-- notification  -->
	<div id="notificationModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
		  <div class="modal-content">
			<div class="modal-header">
			  <h5 class="modal-title text-white" id="modalTitle">Уведомление</h5>
			  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="modalBody">
			  Сообщение уведомления.
			</div>
			<div class="modal-footer">
			  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
			</div>
		  </div>
		</div>
	</div>
</div>
</body>