<li class='text-center'>
	{{__('Management')}}
</li>
<li>
	<a href="/management/docs">
		{{__('management.docs')}}
	</a>
</li>
<li>
	<a href="#collapseWall" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseWall">{{__('management.wall')}} ▼</a>
	<div class="collapse" id="collapseWall">
		<ul>
			<li><a class="dropdown-item" href="/management/wall/posts">{{__('management.posts')}}</a></li>
			<li><a class="dropdown-item" href="/management/wall/categories">{{__('management.categories')}}</a></li>
		</ul>
	</div>
</li>
<li>
	<a href="#collapseShop" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseUsers">{{__('Shop')}} ▼</a>
	<div class="collapse" id="collapseShop">
		<ul>
			<li><a class="dropdown-item" href="/management/shop/products">{{__('management.products')}}</a></li>
			<li><a class="dropdown-item" href="/management/shop/orders">{{__('management.orders')}}</a></li>
			<li><a class="dropdown-item" href="/management/shop/categories">{{__('management.categories')}}</a></li>
			<li><a class="dropdown-item" href="/management/shop/basket">{{__('management.basket')}}</a></li>
		</ul>
	</div>
</li>
<li>
  <a href="#collapseAssistants" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseAssistants">{{__('management.assistants')}} ▼</a>
  <div class="collapse" id="collapseAssistants">
    <ul>
      <li><a class="dropdown-item" href="/management/assistant/commands">{{__('management.commands')}}</a></li>
      <li><a class="dropdown-item" href="/management/assistant/messages">{{__('management.messages')}}</a></li>
      <li><a class="dropdown-item" href="/management/assistant/settings">{{__('management.settings')}}</a></li>
      <li><a class="dropdown-item" href="/management/assistant/filters">{{__('management.filters')}}</a></li>
	  <li><a class="dropdown-item" href="/management/assistant/browser">{{__('management.browser')}}</a></li>
      <li>
        <a href="#collapseLearning" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseLearning">
          {{__('management.learning')}} ▼
        </a>
        <div class="collapse" id="collapseLearning">
          <ul>
            <!-- Подраздел AI -->
            <li>
              <a href="#collapseLearningAI" class="dropdown-item dropdown-toggle" 
                data-bs-toggle="collapse" 
                aria-expanded="false">
                {{__('management.ai')}} ▶
              </a>
              <div class="collapse" id="collapseLearningAI">
                <ul>
                  <li><a class="dropdown-item" href="/management/ai/services">{{__('management.services')}}</a></li>
                  <li><a class="dropdown-item" href="/management/ai/prompts">{{__('management.prompts')}}</a></li>
                  <li><a class="dropdown-item" href="/management/ai/requests">{{__('management.requests')}}</a></li>
                </ul>
              </div>
            </li>
            
            <!-- Подраздел Embeddings -->
            <li><a class="dropdown-item" href="/management/assistant/learning">{{__('management.learning-data')}}</a></li>
          </ul>
        </div>
      </li>
    </ul>
  </div>
</li>
<li>
	<a href="#collapseSettings" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseSettings">{{__('management.settings')}} ▼</a>
	<div class="collapse" id="collapseSettings">
		<ul>
			<li><a class="dropdown-item" href="/management/settings/basic">{{__('management.basic')}}</a></li>
			<li><a class="dropdown-item" href="/management/settings/files">{{__('management.files')}}</a></li>
			<li><a class="dropdown-item" href="/management/settings/keywords">{{__('management.keywords')}}</a></li>
			<li><a class="dropdown-item" href="/management/settings/processes">{{__('management.processes')}}</a></li>
			<li><a class="dropdown-item" href="/management/settings/queues">{{__('management.queues')}}</a></li>
			<li><a class="dropdown-item" href="/management/settings/ips">{{__('management.ips')}}</a></li>
			<li><a class="dropdown-item" href="/management/settings/pwa">{{__('management.pwa')}}</a></li>
			<li><a class="dropdown-item" href="/management/settings/parsers">{{__('management.parsers')}}</a></li>
			<li><a class="dropdown-item" href="/management/settings/sockets">{{__('management.sockets')}}</a></li>
			<li><a class="dropdown-item" href="/management/settings/logs">{{__('management.logs')}}</a></li>
		</ul>
	</div>
</li>
<li>
	<a href="#collapseUsers" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseUsers">{{__('management.users')}} ▼</a>
	<div class="collapse" id="collapseUsers">
		<ul>
			<li><a class="dropdown-item" href="/management/users/users">{{__('management.users')}}</a></li>
			<li><a class="dropdown-item" href="/management/users/roles">{{__('management.roles')}}</a></li>
		</ul>
	</div>
</li>
<li>
	<a href="#collapsePayments" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapsePayments">{{__('management.payments')}} ▼</a>
	<div class="collapse" id="collapsePayments">
		<ul>
			<li><a class="dropdown-item" href="/management/payments/payments">{{__('management.payments')}}</a></li>
			<li><a class="dropdown-item" href="/management/payments/provaiders">{{__('management.provaiders')}}</a></li>
			<li><a class="dropdown-item" href="/management/payments/currencys">{{__('management.currencys')}}</a></li>
			<li><a class="dropdown-item" href="/management/payments/statuses">{{__('management.paymentsstatuses')}}</a></li>
		</ul>
	</div>
</li>