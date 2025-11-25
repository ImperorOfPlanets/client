//Показывать или скрывать sidebar после загрузки
function showOrHideSideBar(){
	var ss = Cookies.get('ss');
	if(ss !== undefined)
	{
		if(ss != 1)
		{
			$('#sidebar').addClass('active');
		}
	}
}

//Магазин - дерево категорий
function getTree()
{
	$.ajax({
		url:"/shop/tree",
		dataType:'json',
		success:function(data)
		{
			$('#tree').bstreeview({data:data});
		}
	});
}

$(document).ready(function(){
	showOrHideSideBar();
	getTree();
	$('#sidebarCollapse').on('click', function () {
		$('#sidebar').toggleClass('active');
		if($('#sidebar').hasClass('active'))
		{Cookies.set('ss',0);}else{Cookies.set('ss',1);}
	});
});