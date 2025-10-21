<h4 class="alert-heading mb-0 inline"> Уведомления</h4>
<hr classs='pt-0' />
<button class='btn btn-success'>
	Разрешить получать уведомления
</button>
<div class='errors d-none'>
</div>
<script>
//Запрос на получение уведомлений
function requestPermission() {
  return new Promise(function(resolve, reject) {
    const permissionResult = Notification.requestPermission(function(result) {
      // Поддержка устаревшей версии с функцией обратного вызова.
      resolve(result);
    });

    if (permissionResult) {
      permissionResult.then(resolve, reject);
    }
  })
  .then(function(permissionResult) {
    if (permissionResult !== 'granted') {
      throw new Error('Permission not granted.');
    }
  });
}
</script>