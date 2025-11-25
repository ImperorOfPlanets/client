<div class='post-body'>
	<div class='post-text bg-white p-3'>{{$post->propertyById(10)->pivot->value ?? 'Тут будет текст'}}</div>
</div>