$(window).on('load',function(){
	console.log('>>>>>>>>>>>>>>>>>> $(window).on(load) - posts.js <<<<<<<<<<<<<<<<<<<<<');

	$('body').on('click','[data-action]',function(){
		var action = $(this).attr('data-action');
		if(action=='addInCart')
		{
			var fd = new FormData();
			fd.append('command','addInCart');
			fd.append('product_id',$(this).closest('[data-product-id]').attr('data-product-id'));
			fd.append('count',$(this).closest('[data-product-id]').find('input[data-count]').val());
			//fd.append('_method','put');
			$.ajax({
				url:"shop/basket",
				type: 'post',
				data: fd,
				dataType:'json'
			});
		}
		else if(action=='addCount' || action=='reCount')
		{
			var obj = $(this).siblings('input');
			if(action=='addCount')
			{
				var newVal = parseInt(obj.val())+1;
			}
			if(action=='reCount')
			{
				var newVal = parseInt(obj.val())-1;
				if(newVal<1)
				{
					newVal=1;
				}
			}
			
			console.log(newVal);
			$(obj).val(newVal);
		}
	});
});