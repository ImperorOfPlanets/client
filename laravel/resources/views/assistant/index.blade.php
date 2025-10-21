@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
    @push('scripts')
		@vite(['resources/js/assistant.js'])
		<script src="/js/hammer.min.js"></script>
        
	@endpush
	<input type="hidden" id="channel_id" name="channel_id" value="{{$channel_id}}">
	@include('assistant.messages')
	@include('assistant.bottom')
	@include('assistant.modal')
<script>
    //Генерация ID
    function generate_temp_id() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < 12; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    //Обновляет карточку сообщения
    function updateMessageWithIds(temp_id, updateId, messageId) {
        console.log('Обновляем карту');
        // Ищем карточку сообщения по temp_id
        const messageElement = document.querySelector(`[data-temp-id="${temp_id}"]`);
        console.log(messageElement);
        if (messageElement) {
            // Обновляем data-атрибуты
            messageElement.setAttribute('data-update-id', updateId);
            messageElement.setAttribute('data-message-id', messageId);

            // Обновляем статус на "доставлено" (2 зеленые галочки)
            const statusContainer = messageElement.querySelector('.status');
            if (statusContainer) {
                statusContainer.innerHTML = '<i class="bi bi-check-all text-success"></i>'; // Две зеленые галочки
            }
        } else {
            console.warn(`Сообщение с temp_id ${temp_id} не найдено`);
        }
    }

    function createMessageCard(message) {
        // Создаем родительский блок для сообщения (ширина 100%)
        const messageContainer = document.createElement('div');
        messageContainer.className = 'message-container d-flex justify-content-start w-100';  // Используем flex и ширину 100%\

        // Проверяем, кто отправил сообщение
        if (message.isMyMessage) {
            messageContainer.classList.add('justify-content-end'); // Выравниваем вправо для отправителя
        } else {
            messageContainer.classList.add('justify-content-start'); // Выравниваем влево для получателя
        }

        // Создаем карточку сообщения (ширина 50%)
        const card = document.createElement('div');
        card.className = 'card mb-3 w-50';  // Карточка занимает 50% ширины
        card.setAttribute('data-temp-id', message.temp_id);

        // Создаем card-header для времени и кнопки
        const cardHeader = document.createElement('div');
        cardHeader.className = 'card-header d-flex justify-content-between align-items-center p-2';

        // Время сообщения
        const timeContainer = document.createElement('span');
        timeContainer.className = 'text-muted small';
        timeContainer.textContent = message.time || '00:00';  // Формат времени можно настроить
        cardHeader.appendChild(timeContainer);

        // Кнопка с тремя точками
        const optionsButton = document.createElement('button');
        optionsButton.className = 'btn btn-link text-muted p-0';
        optionsButton.innerHTML = '<i class="bi bi-three-dots"></i>';  // Иконка с тремя точками
        optionsButton.addEventListener('click', function() {
            showActionsModal(message);  // Обработчик клика на кнопку
        });
        cardHeader.appendChild(optionsButton);

        // Создаем контейнер для содержимого карточки
        const cardBody = document.createElement('div');
        cardBody.className = 'card-body p-3';

        // Создаем контейнер для текста сообщения
        const textContainer = document.createElement('p');
        textContainer.textContent = message.text || 'Текст отсутствует';

        // Устанавливаем класс для выравнивания текста (в зависимости от пользователя)
        textContainer.className = message.isMyMessage ? 'text-end' : 'text-start';

        // Добавляем текст в body
        cardBody.appendChild(textContainer);

        // Создаем card-footer для статуса
        const cardFooter = document.createElement('div');
        cardFooter.className = 'card-footer text-end small';

        // Добавляем статус (галочки или индикатор)
        const statusContainer = document.createElement('div');
        statusContainer.className = 'status';

        cardFooter.appendChild(statusContainer);

        // Добавляем header, body и footer в карточку
        card.appendChild(cardHeader);
        card.appendChild(cardBody);
        card.appendChild(cardFooter);

        // Добавляем карточку в контейнер
        messageContainer.appendChild(card);

        // Добавляем сообщение в блок сообщений
        const messagesDiv = document.getElementById('messages');
        messagesDiv.prepend(messageContainer);

        // Прокручиваем вниз
        scrollMessagesToBottom()
    }

                //Голосовые
    let mediaRecorder;
    let voice;
    let isRecording = false;

    async function initAudio() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            
            // Обработчик клика по кнопке микрофона
            $('.mic').on('click', async function(e) {
                e.preventDefault();
                if (!isRecording) {
                    await startRecording();
                } else {
                    await stopRecording();
                }
            });

            mediaRecorder.addEventListener("dataavailable", function(event) {
                if (event.data && typeof event.data.arrayBuffer !== 'undefined') {
                    const arrayBuffer = event.data.arrayBuffer;
                    sendAudio(arrayBuffer);
                }
            });
        } catch (err) {
            console.error('Ошибка доступа к микрофону:', err);
        }
    }

    async function startRecording() {
        console.log('старт');
        if (mediaRecorder && mediaRecorder.state === 'inactive') {
            mediaRecorder.start();
            $('#message').attr('placeholder',"Отмена");
            $('.sticky-bottom').css('background', 'red');
            isRecording = true;

            // Return a promise that resolves when recording starts
            return new Promise((resolve) => {
                mediaRecorder.onstart = () => resolve();
            });
        }
    }

    async function stopRecording() {
        /* console.log('Запись остановлена');
        
        await new Promise(resolve => mediaRecorder.addEventListener('stop', resolve));

        let audioChunks = [];
        mediaRecorder.ondataavailable = (event) => {
            audioChunks.push(event.data);
            console.log('Добавлен новый chunk:', event.data);
        };

        await new Promise(resolve => mediaRecorder.onstop = resolve);

        console.log('Количество chunk:', audioChunks.length);

        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
        console.log('Создан Blob:', audioBlob);

        try {
            const arrayBuffer = await sendAudio(audioBlob);
            console.log('Обработан ArrayBuffer:', arrayBuffer);
            
            // Остальной код отправки...
        } catch (error) {
            console.error('Ошибка при отправке аудио:', error);
        }*/


        // Проверяем, что MediaRecorder активен перед остановкой
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        } else {
            console.warn('MediaRecorder не активен или уже остановлен');
            return;
        }
        let audioChunks = [];
        mediaRecorder.ondataavailable = (event) => {
            audioChunks.push(event.data);
            console.log('Добавлен новый chunk:', event.data);
        };
        await new Promise(resolve => mediaRecorder.onstop = resolve);
        console.log('Количество chunk:', audioChunks.length);

        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
        console.log('Создан Blob:', audioBlob);

        try {
            const arrayBuffer = await sendAudio(audioBlob);
            console.log('Обработан ArrayBuffer:', arrayBuffer);
            
            // Отправка аудио на сервер
            sendAudioToServer(arrayBuffer);
        } catch (error) {
            console.error('Ошибка при отправке аудио:', error);
        }

        // Завершение записи
        mediaRecorder.stop();
        $('.sticky-bottom').css('background', '#7432f9');
        $('#message').attr('placeholder', "Текст");
        isRecording = false;

        // Очистка потока микрофона
        stream.getTracks().forEach(track => track.stop());
    }

    initAudio();

    async function sendAudio(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => {
                const arrayBuffer = reader.result;
                resolve(arrayBuffer);
            };
            reader.onerror = reject;
            
            // Check if it's an audio file
            if (!file || typeof file !== 'object' || !file.type || !file.type.startsWith('audio/')) {
                reject(new Error('Not an audio file'));
                return;
            }
            
            reader.readAsArrayBuffer(file);
        });
    }
</script>

<script>
    // СООБЩЕНИЯ ОТОБРАЖЕНИЕ

    function updateMessages()
    {
        $.ajax({
            url: '/assistant/messages', // Укажите правильный endpoint
            type: 'GET',
            processData: false,
            contentType: false,
            dataType:'json',
            success: function(response) {
                console.log('Success:', response);

                // Очищаем существующие сообщения
                $('#messages').empty();

                // Создаем новые карточки для каждого сообщения
                if (response && Array.isArray(response.messages)) {
                    response.messages.forEach(function(message) {
                        createMessageCard(message);
                    });
                } else {
                    console.error('Invalid response format');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
            }
        });
    }



    function showActionsModal(message)
    {
        const modalContent = document.querySelector('.modal-body');
        modalContent.innerHTML = `
            <h5>Действия с сообщением</h5>
            <p>Текст сообщения: ${message.text}</p>
            <!-- Здесь можно добавить дополнительные действия -->
            <button class="btn btn-primary">Применить изменения</button>
            <button class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        `;
        new bs.Modal(document.getElementById('actionsModal')).show();
    }

    function sendMessageText() {
        console.log($('#message').val())
        if($('#message').val()=='games2')
        {
            console.log(123)
            console.log(window.runGame2);
            window.runGame2();
        }
        if($('#message').val()=='games')
        {
            console.log(123)
            window.runGame();
        }
        // Проверяем поддержку WebSocket
        if ('WebSocket' in window) {
            // Поддерживается WebSocket, используем его
            sendMessageThroughWebSocket();
        } else {
            // WebSocket не поддерживается, используем AJAX
            sendMessageAjax();
        }
        // Очищаем поле ввода после отправки сообщения
        $('#message').val('');
    }

    async function sendMessageThroughWebSocket() {
        const message = $('#message').val();
        if (message.trim() === '') {
            console.warn('Пустое сообщение');
            return;
        }

        try {
            const temp_id = generate_temp_id();
            const messageData = {
                temp_id: temp_id,
                type: 'text',
                text: message,
                isMyMessage:true
            };
            Echo.connector.pusher.send_event('SendMessage',messageData,'private-chat.' + channel_id);

            // Добавляем сообщение в интерфейс с одной серой галочкой
            createMessageCard(messageData);
        } catch (error) {
            console.error('Ошибка при отправке сообщения через WebSocket:', error);
            // В случае ошибки, попробуем отправить через AJAX
            sendMessageAjax();
        }
    }

    async function sendMessageAjax() {
        const formData = new FormData();
        formData.append('type', 'text');
        formData.append('text', $('#message').val());

        try {
            const response = await fetch('/assistant/messages', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const result = await response.json();
                console.log('Сообщение отправлено через AJAX:', result);
                updateMessages(); // Обновляем список сообщений после успешной отправки
            } else {
                throw new Error(`HTTP ошибка: ${response.status}`);
            }
        } catch (error) {
            console.error('Ошибка при отправке сообщения через AJAX:', error);
        }
    }

    //Для отправки событий от сервера к клиенту
    let privateChannel;

    // СООБЩЕНИЯ ОТПРАВКА
    const channel_id = document.getElementById('channel_id').value;

$(document).ready(function(){
    console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - assistant.js <<<<<<<<<<<<<<<<<<<<<');

    // Обработчик события прокрутки
    $('#messages').on('scroll', function() {
        console.log('Самый вверх');
        const scrollTop = $(this).scrollTop();
        const scrollHeight = $(this)[0].scrollHeight;
        const clientHeight = $(this)[0].clientHeight;

        // Если пользователь прокрутил до самого верха
        if (scrollTop === 0 && !isLoadingMessages) {
            isLoadingMessages = true;
            loadOlderMessages();  // Загружаем старые сообщения
        }
    });

    privateChannel = Echo.private('chat.' + channel_id)
        .listen('.MessageReceived', (data) => {
            console.log('.MessageReceived')
            console.log(data);

            // Находим сообщение по temp_id
            const temp_id = data.temp_id;
            const updateId = data.update_id;
            const messageId = data.message_id;
            const event = data.event;

            if(event)
            {
                console.log('Есть событие');
            }
            console.log('Принял',temp_id, updateId, messageId);

            if(temp_id) {
                updateMessageWithIds(temp_id, updateId, messageId);
            }

            //updateMessages(); // Обновляем сообщения при получении новых
        })
        .listen('.EditMessage', (data) => {
            console.log('.Messages')
            console.log(data);
            // Если есть данные сообщений, то перебираем их
            if (data && data.length > 0) {
                // Сортируем сообщения по id (по убыванию, чтобы новые добавлялись позже)
                data.sort((a, b) => a.id - b.id);

                // Теперь перебираем отсортированные сообщения
                data.forEach(message => {
                    console.log(message);
                    // Для каждого сообщения создаем карточку
                    createMessageCard(message);
                });
            }
        })
        .listen('.Messages', (data) => {
            console.log('.Messages')
            console.log(data);
            // Если есть данные сообщений, то перебираем их
            if (data && data.length > 0) {
                // Сортируем сообщения по id (по убыванию, чтобы новые добавлялись позже)
                data.sort((a, b) => a.id - b.id);

                // Теперь перебираем отсортированные сообщения
                data.forEach(message => {
                    console.log(message);
                    // Для каждого сообщения создаем карточку
                    createMessageCard(message);
                });
            }
        })
        .subscribed(() => {
            console.log('Подписка на приватный канал ассистента получена! Получаем сообщения');
            // Отправляем событие getMessages
            const messageData = {
                offset: 0
            };
            Echo.connector.pusher.send_event('getMessages', messageData, 'private-chat.' + channel_id);
        })
        .on('error', (error) => {
            console.error('Ошибка подписки:', error);
        });

    console.log('Подписка на WebSocket канал установлена');

    // Обработка отправки сообщения
    $('span').on('click', function() {
        const action = $(this).data('action');
        console.log(action);
        switch(action) {
            case 'sendMessage':
                sendMessageText();
                break;
            default:
                console.error('Неизвестное действие:', action);
        }
    });

    //Нажат ENTER
    $('#message').on('keypress',function(e){
        //Нажат ENTER
        if(e.which == 13)
        {
            sendMessageText();
        }
    });

    //Ввод если начали вводить буквы убираем микрорфон
    $('#message').on('input',function(e){
        var length = $(this).val().length;
        console.log(length);
        if(length > 0)
        {
            $('.sticky-bottom span').removeClass('d-none');
            $('.sticky-bottom svg').addClass('d-none');
        }
        else
        {
            $('.sticky-bottom span').addClass('d-none');
            $('.sticky-bottom svg').removeClass('d-none');
        }
    });

    $('#message').trigger('input');
});
</script>
@endsection