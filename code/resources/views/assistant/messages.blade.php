<div id="messages">
</div>
<style>
    html, body {
        height: 100%;
        margin: 0;
        overflow: hidden;
    }
    #messages {
        background-image: url("img/pwa/forAll.png");
        background-attachment: scroll;
        background-repeat: no-repeat;
        background-position: top;
        background-size: 400px;
        display: flex;
        flex-direction: column-reverse;  /* Располагаем сообщения снизу вверх */
        overflow-y: auto; /* Позволяет прокручивать контейнер сообщений */
        padding: 10px;
    }

    .message-container {
        display: flex;
        justify-content: start;  /* Сообщения идут слева */
        width: 100%;  /* Родительский блок занимает 100% ширины */
    }

    .card {
        width: 50%;  /* Карточка занимает 50% ширины контейнера */
        margin-right: 10px;  /* Отступ справа для отделения карточек */
    }

    .card-header {
        padding: 0.5rem 1rem; /* Немного сжимаем пространство */
    }

    .card-footer {
        background-color: #f8f9fa;
        padding: 0.5rem 1rem;
    }

    .text-end {
        text-align: right;
    }

    .text-start {
        text-align: left;
    }

    .status {
        display: inline-flex;
        justify-content: flex-end;
        width: 100%; /* Статус занимает всю ширину */
    }

    .bi-check-all {
        font-size: 16px;
    }

    .bi-clock {
        font-size: 16px;
    }

    .btn-link {
        padding: 0;
        border: none;
        background: none;
        font-size: 18px;
    }

    .bi-three-dots {
        font-size: 20px;
    }
</style>
<script>
function setMessagesSize() {
    var windowHeight = $(window).outerHeight();
    console.log('Window Height:', windowHeight);

    var navbar = $('#content > nav');
    var navbarHeight = navbar.outerHeight();
    console.log('Navbar Height:', navbarHeight);

    var stickyBottom = $('.sticky-bottom');
    var stickyBottomHeight = stickyBottom.outerHeight();
    console.log('Sticky Bottom Height:', stickyBottomHeight);

    // Вычисляем доступную высоту для сообщений
    var availableHeight = windowHeight - navbarHeight - stickyBottomHeight;
    console.log('Available Height:', availableHeight);

    // Устанавливаем минимальную и максимальную высоту для контейнера сообщений
    $('#messages').css({
        'min-height': availableHeight,
        'max-height': availableHeight
    });

    // Прокручиваем контейнер вниз после добавления нового сообщения
    $('#messages').scrollTop($('#messages')[0].scrollHeight);
}

function scrollMessagesToBottom() {
	console.log('Скролим вниз');
    const messagesDiv = document.getElementById('messages');
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

window.addEventListener('resize',function()
{
	setMessagesSize();
});

$(document).ready(function(){
	setMessagesSize();
});
</script>