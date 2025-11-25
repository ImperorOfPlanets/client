//Функция urlBase64ToUint8Array() преобразует наш открытый ключ в соответствующий формат
function urlBase64ToUint8Array(base64String) {
	var padding = '='.repeat((4 - base64String.length % 4) % 4);
	var base64 = (base64String + padding)
		.replace(/\-/g, '+')
		.replace(/_/g, '/');

	var rawData = window.atob(base64);
	var outputArray = new Uint8Array(rawData.length);

	for (var i = 0; i < rawData.length; ++i) {
		outputArray[i] = rawData.charCodeAt(i);
	}
	return outputArray;
}

//Подписываем пользователя
function subscribeUser()
{
	navigator.serviceWorker.ready
	.then((registration) => {
		const subscribeOptions = {
			userVisibleOnly: true,
			applicationServerKey: urlBase64ToUint8Array('BFVruIyDYV8RW2cDdxp1kYVl6SHJ-Pza_qpfYaeRePIMFfs05ukLxuG4nO85BYBPR5NHbPZfFeNAYpN0bh2uNBU')
		};
		return registration.pushManager.subscribe(subscribeOptions);
	})
	.then((pushSubscription) => {
		console.log('Received PushSubscription: ', JSON.stringify(pushSubscription));
		storePushSubscription(pushSubscription);
	});
}

//Инициализация
function initPush() {
	if (!navigator.serviceWorker.ready) {
		return;
	}

	new Promise(function (resolve, reject) {
		const permissionResult = Notification.requestPermission(function (result) {
			resolve(result);
		});

		if (permissionResult) {
			permissionResult.then(resolve, reject);
		}
	})
	.then((permissionResult) => {
		if (permissionResult !== 'granted')
		{
			throw new Error('We weren\'t granted permission.');
		}
		subscribeUser();
	});
}

//Отправка ключа подписки
function storePushSubscription(pushSubscription) {
	const token = document.querySelector('meta[name=csrf-token]').getAttribute('content');
	fetch('/push',
	{
		method: 'POST',
		body: JSON.stringify(pushSubscription),
		headers: {
			'Accept': 'application/json',
			'Content-Type': 'application/json',
			'X-CSRF-Token': token
		}
	})
	.then((res) => {
		return res.json();
	})
	.then((res) => {
		console.log(res)
	})
	.catch((err) => {
		console.log(err)
	});
}

//Функция вызываемая после регистрации
function afterRegisterPush()
{
	initPush();
}

//Вывод пуш
self.addEventListener('push', function (e) {
	if (!(self.Notification && self.Notification.permission === 'granted')) {
	//notifications aren't supported or permission not granted!
		return;
	}

	if(e.data)
	{
		var msg = e.data.json();
		console.log(msg)
		e.waitUntil(self.registration.showNotification(msg.title, {
			body: msg.body,
			icon: msg.icon,
			actions: msg.actions
		}));
	}
});

//клик на кнопку
self.addEventListener('notificationclick', function(event) {
	console.log('On notification click: ', event.notification.tag);
	// Android doesn't close the notification when you click on it
	// See: http://crbug.com/463146
	event.notification.close();
	
	// This looks to see if the current is already open and
	// focuses if it is
	event.waitUntil(
		clients.matchAll({
		type: "window"
	})
	.then(function(clientList)
	{
		for (var i = 0; i < clientList.length; i++)
		{
			var client = clientList[i];
			if (client.url == '/' && 'focus' in client)
			return client.focus();
		}
		if (clients.openWindow)
		{
			return clients.openWindow('/');
		}
	})
	);
});

//Активирован
self.addEventListener('activate', event => {
	console.log('Активирован электронный сотрудник для работы с уведомлениями.');
});