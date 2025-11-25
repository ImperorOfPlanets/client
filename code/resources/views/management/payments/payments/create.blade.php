@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<div class="card m-1 w-100">
	<form action="/management/payments/payments" method='post' id='createpayment'>
		@csrf
		<h5 class="card-header">Платежная система</h5>
		<div class="card-body"> 
			<select type="text" name="provaider" class='form-control' >
			</select>
		</div>
		<h5 class="card-header">Сумма</h5>
		<div class="card-body"> 
			<input type="text" name="summ" class='form-control' />
		</div>
		<h5 class="card-header">Валюта</h5>
		<div class="card-body"> 
			<select type="text" name="currency" class='form-control' >
				<option value="0" disabled='disabled' selected='selected'>Выберите валюту</option>
			</select>
		</div>
		<div class="card-footer text-body-secondary d-grid">
			<button>Создать</button>
		</div>
	</form>
</div>
<script>

	//Первое получение
	var getted = false;

	//Провайдеры
	var provaiders = null;

	//Обновить список валют
	function updateCurrencys(provaider)
	{
		$('[name=currency]').empty();
		$('[name=currency]').append("<option value='0' disabled='disabled' selected='selected'>Выберите валюту</option>");
		var finded = false;
		provaiders.forEach(element => {
			if(element.id==provaider)
			{
				element.currencys.forEach(currency => {
					console.log(currency);
					$('[name=currency]').append("<option value='"+currency.inId+"'>"+currency.name+"</option>");
				});
			}
		});
	}

	//Отрисовка списка платежных систем
	function setProvaiders()
	{
		console.log(provaiders);
		$('[name=provaider]').append("<option value='0' disabled='disabled' selected='selected'>Выберите платежную систему</option>");
		provaiders.forEach(element => {
			$('[name=provaider]').append("<option value='"+element.id+"'>"+element.name+"</option>");
		});
	}

	$(document).ready(function(){
		console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - management.payments.create <<<<<<<<<<<<<<<<<<<<<');

		//Получение платежных систем
		if(getted == false)
		{
			var fd = new FormData();
			fd.append('command','getCurrencys');
			fd.append('_method','put');
			$.ajax({
				url:"/payments/provaiders",
				data: fd,
				dataType:'json',
				success:function(data)
				{
					provaiders = data;
					setProvaiders();
				}
			});
		}

		//Выбор провайдера
		$('[name=provaider]').on('change',function(e){			
			updateCurrencys($('[name=provaider]').val());
		});

		//Валдция формы
		$('#createpayment').on('submit',function(event){

			//Проверяем провайдера
			console.log($('[name=provaider]').val());
			if($('[name=provaider]').val() == 0 || $('[name=provaider]').val()==null)
			{
				alert('Выберите платежную систему');
				event.preventDefault(); // Предотвращаем отправку формы
				return false;
			}

			//Проверяем сумму
			if($('[name=summ]').val() =='')
			{
				alert('Введите сумму.');
				event.preventDefault(); // Предотвращаем отправку формы
				return false;
			}

			//Проверяем валюту
			if($('[name=currency]').val() == 0 || $('[name=currency]').val()==null)
			{
				alert('Выберите валюту.');
				event.preventDefault(); // Предотвращаем отправку формы
				return false;
			}
		});
	});
</script>
@endsection