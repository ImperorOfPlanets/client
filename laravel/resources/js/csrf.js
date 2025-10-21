//Отправляем запрос на получение нового токена
function getNewCSRF()
{
	console.log('>>>>>>>>>>>>>>>>>> getNewCSRF - unit.csrf.js <<<<<<<<<<<<<<<<<<<<<');
	$.ajax({
		url:"/get-csrf-token",
		success:function(data)
		{
			console.log('>>>>>>>>>>>>>>>>>> getNewCSRF - ajax success - unit.csrf.js <<<<<<<<<<<<<<<<<<<<<');
			$("meta[name=csrf-token]").attr("content",data);
			$.ajaxSetup({beforeSend:function(xhr){xhr.setRequestHeader('X-CSRF-TOKEN',$('meta[name="csrf-token"]').attr('content'));}});
		}
	});
}
var updateCSRF = 
{
	//Название интервала
	name:"updateCSRF",
	//Запускаемая функция
	cycFunction:"getNewCSRF",
	//Период цикличности
	delay:60000,
};
window.addEventListener('DOMContentLoaded',(event)=>{
	console.log('>>>>>>>>>>>>>>>>>> WINDOW DOMContentLoaded - /js/unit/unit.csrf.js <<<<<<<<<<<<<<<<<<<<<');
	window.app.intervals.add(updateCSRF);
});