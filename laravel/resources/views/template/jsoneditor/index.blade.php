@push('scripts')
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jsoneditor/9.9.2/jsoneditor.min.js" integrity="sha512-MP2pEPP3BGw032ovuAsX6yTu7O4J6L3YTXuyq3IpR+LuwRun9BBjOeeIKgO3bRiNlI88x3oCVb9I1/1+xmvFIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@endpush
@push('styles')
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jsoneditor/9.9.2/jsoneditor.min.css" integrity="sha512-brXxIa/fpMfluh8HUWyrNssKy/H0oRzA0HhmipgfVwbXPamMUTZn1jEsvoGGwJYCRpSx4idujdul4vFwWgPROA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
@endpush

<div class="modal" id="jsoneditor">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-body" id='jsoneditorbody'>
			</div>
			<div class="modal-footer">
				<div class='row w-100'>
					<div class='col d-grid'>
						<button class='btn btn-success text-white' data-action='saveResult' class='text-white'>Сохранить</button>
					</div>
					<div class='col d-grid'>
						<button class='btn btn-success text-white' type="button" data-bs-dismiss="modal" aria-label="Close" data-action='closeJsonEditor'>Закрыть</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	// create the editor
	const container = document.getElementById("jsoneditorbody")
	const options = {
		'language': 'ru'
	}
	const editor = new JSONEditor(container, options)

	// set json
	var initialJson = null;
	editor.set(initialJson)

	// get json
	const updatedJson = editor.get()
	
$(document).ready(function(){
	$('body').on('click','[data-action]',function(){
		var action = $(this).attr('data-action');
		if(action=='closeJsonEditor')
		{
			$('#jsoneditor').hide();
		}
	})
});
</script>