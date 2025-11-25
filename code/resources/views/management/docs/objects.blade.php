<div id='myModal' class="modal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<label>Введите название</label>
				<input type="text" name="name" class='form-control' value="">
				<label>Введите текст</label>
				<input type="text" name="text" class='form-control' value="">
				<label>Размер текста</label>
				<input type="text" name="fontSize" class='form-control' value="">
				<label>Шрифт текста</label>
				<input type="text" name="fontFamily" class='form-control' value="">
				<label>X</label>
				<input type="text" name="xCordOfText" class='form-control' value="">
				<label>Y</label>
				<input type="text" name="yCordOfText" class='form-control' value="">
			</div>
			<div class="modal-footer">
				<button type="button" data-action="deleteObject" class="btn btn-secondary" data-bs-dismiss="modal">Удалить</button>
				<button type="button" data-action="saveObject" class="btn btn-primary">Сохранить</button>
			</div>
		</div>
	</div>
</div>
<button class="form-control" data-action="addObject">Добавить</button>
<ul class='objects-list'>
</ul>
<script>
var defObject = {
	name: 'Название',
	text: 'Текст',
	fontSize: 200,
	fontFamily: "Arial",
	textColor: "rgba(0,0,0,0)",
	textAlign: "left",
	xCordOfText:10,
	yCordOfText:10
};
function saveObjects()
{
	var lis = $('ul.objects-list').find('li');
	window.doc.objects = [];
	$.each(lis,function(index){
		window.doc.objects.push(JSON.parse($(this).attr('data-json')));
	});
	var fd = new FormData();
	fd.append('command','saveProperty');
	fd.append('property_id',102);
	fd.append('value',JSON.stringify(window.doc.objects));
	fd.append('_method','put');
	$.ajax({
		url:"{{route('docs.update',$doc->id)}}",
		type: 'post',
		data: fd,
		dataType:'json'
	});
	refreshView();
}
$(document).ready(function(){
	$('body').on('click','[data-action=addObject]',function(){
		console.log(window.doc.objects.length);
		if(window.doc.objects.length==undefined)
		{
			window.doc.objects = [];
		}
		window.doc.objects.push(defObject);
		console.log(window.doc.objects);
		var jsonObjects = JSON.stringify(window.doc.objects);
		console.log(jsonObjects);
		var fd = new FormData();
		fd.append('command','saveProperty');
		fd.append('property_id',102);
		fd.append('value',jsonObjects);
		fd.append('_method','put');
		$.ajax({
			url:"{{route('docs.update',$doc->id)}}",
			type: 'post',
			data: fd,
			dataType:'json'
		});
		refreshView();
	});
	$('#myModal').on('click','[data-action]',function(){
		var action = $(this).attr('data-action');
		var position = $(this).parents('#myModal').attr('data-position');
		if(action=='deleteObject')
		{
			console.log($('ul.objects-list').find('li[data-position='+position+']').remove());
		}
		else if(action=='saveObject')
		{
			var inputsModal = $('#myModal').find('input');
			console.log(inputsModal);
			var newObj = {};
			$.each(inputsModal,function(index){
				console.log($(inputsModal[index]).attr('name'));
				attrName = $(inputsModal[index]).attr('name');
				newObj[attrName] = $(inputsModal[index]).val();
			});
			console.log(newObj);
			var liFinded = $('ul.objects-list').find('li[data-position='+position+']');
			liFinded.attr('data-json',JSON.stringify(newObj));
			$('#myModal').modal('hide');
		}
		saveObjects();
	});
	$('.objects-list').on('click','li',function(){
		$('#myModal').modal('show');
		$('#myModal').attr('data-position',$(this).attr('data-position'));
		var dataJSON = JSON.parse($(this).attr('data-json'));
		for(key in dataJSON)
		{
			console.log($('#myModal').find('input[name="'+key+'"]'));
			$('#myModal').find('input[name="'+key+'"]').val(dataJSON[key]);
		}
	});
});
</script>