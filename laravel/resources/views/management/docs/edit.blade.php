@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')

@push('sidebar') @include('management.sidebar') @endpush
	<div class="row m-0">
		<div class="col-8">
			<canvas id="myCanvas" />
		</div>
		<div class="col-4">
			<button data-action="export" class='form-control'>Экспортировать</button>
			<ul class="nav nav-tabs" id="myTab" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link active" id="objects-tab" data-bs-toggle="tab" data-bs-target="#objects-tab-pane" type="button" role="tab" aria-controls="objects-tab-pane" aria-selected="true">Объекты</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="propertys-tab" data-bs-toggle="tab" data-bs-target="#propertys-tab-pane" type="button" role="tab" aria-controls="propertys-tab-pane" aria-selected="true">Свойства</button>
				</li>
			</ul>
			<div class="tab-content" id="myTabContent">
				<div class="tab-pane fade show active" id="objects-tab-pane" role="tabpanel" aria-labelledby="objects-tab" tabindex="0">
					@include('management.docs.objects')
				</div>
				<div class="tab-pane fade" id="propertys-tab-pane" role="tabpanel" aria-labelledby="propertys-tab" tabindex="0">
					@include('management.docs.propertys')
				</div>
			</div>
		</div>
	</div>
<script>

$(document).ready(function(){
	$('body').on('click','button[data-action]',function(){
		var action = $(this).attr('data-action');
	});

	//Получаем свойство с объектами
	if(window.doc==undefined)
	{
		window.doc={};
		var objectsInput = $('.card[data-property="102"]').find('input');
		console.log(objectsInput[0].value);
		window.doc.objects = JSON.parse(objectsInput[0].value);
		console.log(window.doc.objects);



		var background = $('.card[data-property="103"]').find('input');
		console.log(background[0].value);
		window.doc.background = background[0].value;
	}

	refreshView();

});

//Очистка
function clearCanvas()
{
	var canvas = document.getElementById('myCanvas');
	var ctx = canvas.getContext('2d');
	// Очищаем все пространство холста
	ctx.clearRect(0, 0, canvas.width, canvas.height);
}
function refreshView()
{
	let theCanvas = document.getElementById("myCanvas");
	loadImageOnCanvasAndResizeCanvasToFitImage(theCanvas,window.doc.background);
}
let loadImageOnCanvasAndResizeCanvasToFitImage = (canvas, imageUrl) => {

	// Get the 2D Context from the canvas
	let ctx = canvas.getContext("2d");

	// Create a new Image
	let img = new Image();

	// Setting up a function with the code to run after the image is loaded
	img.onload = () => {

		//Вычисляем ширину рабочей области!
		var workWidth = $('#content .col-8').outerWidth();
		console.log(workWidth);

		//Считываем Размеры изображения
		let loadedImageWidth = img.width;
		let loadedImageHeight = img.height;

		//Вычисляем коффийциент маштабирования
		let scale_factor = workWidth/img.width;
		console.log(scale_factor);

		//Вычисляем высоту
		var workHeight = img.height*scale_factor;
		console.log(workHeight);

		// Set the canvas to the same size as the image.
		canvas.width = workWidth;
		canvas.height = workHeight;

		// Draw the image on to the canvas.	
		ctx.drawImage(img, 0, 0,workWidth,workHeight);

		var jsonObjects = JSON.stringify(window.doc.objects);

		console.log('DRAW OBJECTS');
		$('.objects-list').empty();
		for(key in window.doc.objects)
		{
			// Set all the properties of the text based on the input params
			ctx.font = `${window.doc.objects[key].fontSize*scale_factor}px ${window.doc.objects[key].fontFamily}`;
			ctx.fillStyle = window.doc.objects[key].textColor;
			ctx.textAlign = window.doc.objects[key].textAlign;
			console.log(ctx.font);
			// Setting this so that the postion of the text can be set
			// based on the x and y cord from the top right corner
			ctx.textBaseline = "top";

			// Finanlly addeing the text to the image
			ctx.fillText(window.doc.objects[key].text,window.doc.objects[key].xCordOfText*scale_factor,window.doc.objects[key].yCordOfText*scale_factor);
			console.log(JSON.stringify(window.doc.objects[key]));
			$('.objects-list').append("<li data-position='"+key+'\' data-json=\''+JSON.stringify(window.doc.objects[key])+'\'>'+window.doc.objects[key].name+'</li>');
		}
	};

	// Now that we have set up the image "onload" handeler, we can assign
	// an image URL to the image.
	img.src = imageUrl;
};
</script>
@endsection