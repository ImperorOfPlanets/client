<!DOCTYPE html>
<html>
<head>
    <title>{{$title ?? ''}}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <META HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE">
    @vite([	
        'resources/sass/app.scss',
    ])
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
              <div class="text-center fs-4">
                <img src="/img/down.png">
                <br />
                Технические работы 
              </div>     
            </div>
        </div>
    </div>
</body>
</html>