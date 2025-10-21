<div id="modalCreatePost" class="modal fade" tabindex="-1" aria-labelledby="modalCreatePost" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header">
				<h1 class="modal-title fs-4" id="exampleModalFullscreenLabel">Создать запись</h1>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-0">
				@auth
					<textarea rows="5" name="text" class='post-create-textarea w-100'>{{session()->get('posts.create.text')}}</textarea>
					<div class='post-create-images'>
						@if(session()->has('posts.create.images'))
							<div class="preview-list flex justify-start">
								@foreach(session()->get('posts.create.images') as $file)
										<div class="preview-block w-6">
											<img src="/files/{{$file}}?w=200" class='preview'/>
											<div class="bar w-6 w-full h-2 bg-sky-200">
												<div class="progress h-2 shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-sky-500" style="width:100%">
												</div>
											</div>
										</div>
								@endforeach
							</div>
						@endif
					</div>
					<div class='post-create-controll row'>
						<div class='col'>
							<label class="">
								<input class="post-create-add-image text-sm cursor-pointer w-36 invisible" name="file" type="file" multiple accept="image/*" />
								<img class='w-4 h-4' src="/img/posts/addimage.svg"/>
							</label>
						</div>
						<div class='col'><button class="ml-4 bg-slate-50" data-action='post-send'><img class='w-4 h-4' src="/img/posts/write.svg"/></button></div>
					</div>
				@else
					{{__('posts.auth')}}
				@endauth
			</div>
		</div>
	</div>
</div>