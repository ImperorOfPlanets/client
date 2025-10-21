$(window).on('load',function(){
	console.log('>>>>>>>>>>>>>>>>>> $(window).on(load) - posts.js <<<<<<<<<<<<<<<<<<<<<');

	$('body').on('click','[data-action]',function(){
		var action = $(this).attr('data-action');
		if(action=='post-send')
		{
			var fd = new FormData();
			fd.append('command',action);
			$.ajax({
				url:"/wall",
				type: 'post',
				data: fd,
				dataType:'json',
			});
		}else if(action=='like'){
			alert('like');
		}else if(action=='share'){
			alert('share');
		}else if(action=='comment'){
			alert('comment');
		}
	});

	/*------------------------------- Создание -------------------------------*/

	//Изменение текста
	$('body').on('focusout','.post-create-textarea',function(){
		if($('.post-create-textarea').val()!=='' && $('.post-create-textarea').val()!==undefined)
		{
			var fd = new FormData();
			fd.append('command','saveText');
			fd.append('text',$('.post-create-textarea').val());
			$.ajax({
				url:"/wall",
				type: 'post',
				data: fd,
				dataType:'json',
			});
		}
	});

	//Добавление изображений
	$('body').on('change','input.post-create-add-image',function()
	{
		if($('input.post-create-add-image').prop("files"))
		{
			var filesAmount = $('input.post-create-add-image').prop("files").length;
			
			for(var iFile=0;iFile<filesAmount;iFile++)
			{
				console.log('create reader');
				var fd = new FormData();
				fd.append('file', $('input.post-create-add-image').prop("files")[iFile]);

				var reader = new FileReader();
				reader.onload = function()
				{
					//console.log('onload ready');
					//return function(e)
					//{
						console.log('onload return');
						//Генерируем ID
						var result           = '';
						var characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
						var charactersLength = characters.length;
						for (var i=0;i<6;i++){result+=characters.charAt(Math.floor(Math.random()*charactersLength));}

						//Добавляем превью
						$('.preview-list')
							.append(
								$('<div>',{
									class: 'preview-block w-6',
									'data-fid':result
								})
								.append(
									$('<img>',{
										src:this.result,
										class:'preview w-6'
									})
								)
								.append(
									$('<div>',{
										class:'bar w-6 h-2 bg-sky-200'
									})
									.append(
										$('<div>',{
											class:'progress h-2 shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-sky-500',
											style:'width:0%'
										})
									)
								)
							);

						//Отправляем файл
						$.ajax({
							xhr: function()
							{
								var xhr = new window.XMLHttpRequest();
								xhr.upload.addEventListener("progress",function(evt)
								{
									if(evt.lengthComputable)
									{
										var percentComplete = ((evt.loaded / evt.total) * 100);
										$(".preview-with-bar[data-fid="+result+"] div .progress").width(percentComplete + '%');
									}
								},false);
								return xhr;
							},
							fid:result,
							type: 'POST',
							url: '/files',
							data: fd,
							headers:
							{
								"x-upload":"postCreate",
								"x-fid":result
							},
							beforeSend:function()
							{
								$(".preview-with-bar[data-fid="+this.result+"] div .progress").width('0%');
							},
							error:function()
							{
								$(".preview-with-bar[data-fid="+this.result+"] div .progress").text('error')
							},
							success: function(resp)
							{
								if(resp == 'ok')
								{
									//$('#uploadForm')[0].reset();
									//$('#uploadStatus').html('<p style="color:#28A74B;">File has uploaded successfully!</p>');
								}
								else if(resp == 'err')
								{
									//$('#uploadStatus').html('<p style="color:#EA4335;">Please select a valid file to upload.</p>');
								}
							}
						});
					//}
				};
				reader.readAsDataURL($('input.post-create-add-image').prop("files")[iFile]);
			}
		}
	});
});