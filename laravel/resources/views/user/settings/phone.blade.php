<h4 class="alert-heading mb-0 inline">@if($user->param('phone_check')) <strong class='text-successs'>☑</strong> @else <strong class='text-danger'>☒</strong>@endif Tелефон</h4>
<hr classs='pt-0' />
  <div class="input-group mb-2">
    <div class="input-group-prepend">
      <span class="input-group-text" id="basic-addon1">+7</span>
    </div>
    @if($user->param('phone'))
  	  <input type='text' class='form-control' value='{{$user->getParam('phone')}}' name='phone'  data-action='phone-bind' disabled='disabled' />
    @else
      <input type='number' class='form-control' value='{{$user->getParam('phone')}}' data-action='phone-bind' name='phone' />
    @endif
  </div>
  <div class='phone-error text-right text-danger'></div>
  <div class='phone-buttons row row-cols-2 text-center'>
    <div class='phone-checked col d-grid'><button class='btn btn-success' data-action='phone-change'>Изменить</button></div>
    <div class='phone-unchecked col d-grid'><button class='btn btn-success ' data-action='phone-bind'>Привязать</button></div>
  </div>
  <div class='phone-verify container p-1 d-none'>
    Введите код проверки из смс<br />
    <input type='number' class='form-control mb-1' data-action='phone-verify-code' name='phone-verify-code' />
    <div class='phone-code-error text-right text-danger'></div>
    <div class='row row-cols-2 text-center'>
      <div class='col d-grid'><button class='btn btn-success ' data-action='phone-send-verify-code'>Отправить код</button></div>
      <div class='col'>
        <div class="input-group">
          <span id='phone-timer' class="input-group-text"></span>
          <button class='btn btn-success form-control' data-action='phone-resend-code' disabled='true'>Отправить заново смс</button>
        </div>
      </div>
    </div>
  </div>
<script>
window.timerSendPhoneCode = null;
window.timerMinutes = 1;
function finishTimer(){
  $('#phone-timer').text('').addClass('d-none');
  $('button[data-action=phone-resend-code]').attr('disabled',false).addClass('rounded');
}
function startTimer(duration, display)
{
    var timer = duration, minutes, seconds;
    window.timerSendPhoneCode = setInterval(function()
    {
        console.log('minutes'+minutes);
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;
        display.textContent = minutes + ":" + seconds;
        if (--timer < 0)
        {
          //ostanavlivaem timer
          clearInterval(window.timerSendPhoneCode);
          finishTimer();
        }
    }, 1000);
}
var phone_in_db={!! $user->param('phone') ?? 'null' !!};
var phone_check={!! $user->param('phone_check') ?? 'null' !!};
function checkPhone(phone)
{
  //Проверяем номер телефона
  console.log('Проверка телефона на соответствие: '+phone)
  console.log('Длина: '+String(phone).length)
  if(phone.length==10){
    $('.phone-error').text('');
		return true;
  }else{
    $('.phone-error').text('Телефон должен состоять из 10 цифр');
    return false;
  }
}
function checkPhoneCode(code)
{
  //Проверяем номер телефона
  console.log('Проверка на соответствие: '+ code)
  console.log('Длина: '+String(code).length)
  if(code.length==6){
    $('.phone-code-error').text('');
		return true;
  }else{
    $('.phone-code-error').text('Код должен состоять из 6 цифр');
    return false;
  }
}
function prepareDesign()
{
  if(window.phone_in_db===null)
  {
    $('.phone-checked').addClass('d-none');
  }
  else
  {
    
  }
}
$(document).ready(function()
{
  //proverka buttons
  $('body').on('click','button[data-action]',function()
  {
    //Отправляем телефон
    if($(this).attr('data-action')=='phone-bind')
    {
        if(checkPhone($('input[name=phone]').val())){
          var fd = new FormData();
					fd.append('command','sendPhone');
					fd.append('_method','PUT');
					fd.append('phone',$('input[name=phone]').val());
					$.ajax({
						url:"/settings/{{$user->id}}",
						type: 'post',
						data: fd,
						dataType:'json',
						contentType: false,
						processData: false,
						success:function(data){
						  if(data.hasOwnProperty('phoneBind'))
						  {
						    if(data.hasOwnProperty('phoneBind')==true)
						    {
						      $('.phone-verify').removeClass('d-none');
						      $('button[data-action=phone-bind]').addClass('d-none');
						      $('button[data-action=phone-change]').removeClass('d-none');
						      $('.phone-buttons').removeClass('row').removeClass('row-cols-2');
                  var fiveMinutes = 60 * window.timerMinutes,
                  display = document.querySelector('#phone-timer');
                  startTimer(fiveMinutes, display);
						    }else{
						      $('.phone-error').text(data.phoneBind);
						    }
						  }
						  else
						  {
						    
						  }
						}
					});
        }
      }
    //send verify code
    if($(this).attr('data-action')=='phone-send-verify-code')
    {
        console.log('Polushili code: '+$('input[name=phone-verify-code]').val());
        if(checkPhoneCode($('input[name=phone-verify-code]').val()))
        {
          var fd = new FormData();
					fd.append('command','sendPhoneCode');
					fd.append('_method','PUT');
					fd.append('code',$('input[name=phone-verify-code]').val());
					$.ajax(
					  {
						url:"/settings/{{$user->id}}",
						type: 'post',
						data: fd,
						dataType:'json',
						contentType: false,
						processData: false,
						success:function(data)
						{
						  if(data.hasOwnProperty('checkPhoneCode'))
						  {
						    if(data.checkPhoneCode===true)
						    {
						    }
						    else
						    {
						    }
						  }
						}
					});
        }
      }
    //izmenit nomer
    if($(this).attr('data-action')=='phone-change')
    {
      $('input[name=phone]').attr('disabled',false);
      $('input[name=phone]').attr('value','');
      $('input[name=phone]').attr('type','number');
      $(this).addClass('d-none');
      $('.phone-verify').addClass('d-none');
      $('button[data-action=phone-bind]').removeClass('d-none');
      $('.phone-buttons').addClass('row').addClass('row-cols-2');
    }
  });

  //proverka input
  $('body').on('focusout','input[data-action]',function(){
    //console.log(this)
    switch ($(this).attr('data-action')){
      case "phone-bind":
        checkPhone($(this).val());
      break;
      case "phone-verify-code":
        checkPhoneCode($(this).val());
      break;
    }
  });
});
</script>