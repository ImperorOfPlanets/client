window["initEvents"] =  function()
{
	console.log('Инициализация событий началась');
	if(window.app.events!=undefined)
	{
		console.log('События определены');
		return false;
	}
	console.log('Устанавливаем события');
	window.app.events = 
	{
		array:[],
		add:function(name,callback=null)
		{
			console.log('Добавляем событие: '+name);
			//Проверяем его наличие
			var finded = null;
			for(const indexEvent in this.array)
			{
				console.log(indexEvent);
				if(this.array[indexEvent]['name']==name)
				{
					finded=true;
					return false;
				}
			}
			if(finded==null)
			{
				this.array.push({
					name:name,
					callback:((callback==null)?name:callback)
				});
			}
		},
		fire:function(name,params=null)
		{
			for(const indexEvent in this.array)
			{
				console.log(indexEvent);
				if(this.array[indexEvent]['name']==name)
				{
					if(params==null)
					{
						console.log(this.array[indexEvent]['callback']);
						window[this.array[indexEvent]['callback']]();
					}
					else
					{
						window[this.array[indexEvent]['callback']](params);
					}
					console.log('Событие: '+name+' сработало');
					return false;
				}
			}
		}
	};
}