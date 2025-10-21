@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<div class='input-group'>
    <input type='text' id='author' class="form-control">
    <span class="input-group-text" id="add">Добавить</span>
</div>
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="myModalLabel">Замеченные авторы</h4>
      </div>
      <div class="modal-body">
        <ul class="list-group findedAuthors">
            @foreach($findedAuthors as $author)
                <li class="list-group-item">{{$author}}</li>
            @endforeach
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#myModal">Показать список найденных авторов</button>
<table class='table table-bordered'>
    @foreach($authors as $author)
        <tr>
            <td>{{$author}}</td>
            <td width="20"><button class='btn btn-danger delete'>X</button></td>
        </tr>
    @endforeach
</table>
<script>
    function addAuthor(author)
    {
        var tr = '<tr><td>'+author+'</td><td width="20"><button class="btn btn-danger delete">X</button></td></tr>';
        $('table').append(tr);
        updateAuthors();
    }
    function updateAuthors()
    {
        var strings = $('table tr');
        var authors = [];
        $.each(strings,function(indexInArray,valueOfElement){
            var td = $(valueOfElement).find('td');
            console.log(td);
            authors.push($(td[0]).text());
        });
        var fd = new FormData;
        fd.append('authors',JSON.stringify(authors));
        $.ajax({
            url:"/management/settings/logs",
            type: 'post',
            data: fd,
            dataType:'json'
        });
    }
	$(document).ready(function(){
		console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - management/settings/logs/settings <<<<<<<<<<<<<<<<<<<<<');

		$("#add").on("click", function(){
			var author = $('#author').val();
			addAuthor(author);
			updateAuthors();
		});

		$('table').on('click','.delete',function(){
			console.log('click')
			$(this).closest('tr').remove();
			updateAuthors();
		});

		$('.findedAuthors').on('click','li',function(){
            console.log('click')
			var author = $(this).text();
			addAuthor(author);
			updateAuthors();
		});
	});
</script>
@endsection