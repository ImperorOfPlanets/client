<header class="navbar navbar-dark bg-dark flex-md-nowrap p-0 shadow">
	<button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
		<span class="navbar-toggler-icon"></span>
	</button>
	<ul class="navbar-nav px-3">
		@guest
		<li class="nav-item text-nowrap">
			<a class="nav-link" href="/login">Войти</a>
		</li>
		@endguest
		@auth
		<li class="nav-item text-nowrap">
			<a class="nav-link" href="/profile">Профиль</a>
		</li>
		@endauth
	</ul>
</header>