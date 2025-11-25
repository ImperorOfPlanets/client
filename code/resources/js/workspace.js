import * as THREE from 'three';
import { PointerLockControls } from 'three/addons/controls/PointerLockControls.js';
//import { OBJExporter } from 'three/addons/exporters/OBJExporter.js';

let camera, scene, renderer, controls;

//Луч
let geometryRay,directionRay;

//Taймер
var timerUpdate;

//Объекты
var objects = {};

//Текстуры
var textures = {};

//Пол
var floor;

let raycaster;

let moveForward = false;
let moveBackward = false;
let moveLeft = false;
let moveRight = false;
let canJump = false;

let prevTime = performance.now();
const velocity = new THREE.Vector3();
const direction = new THREE.Vector3();
const vertex = new THREE.Vector3();
const color = new THREE.Color();

init();
animate();

function init() {

    //Камера
    camera = new THREE.PerspectiveCamera( 75, window.innerWidth / window.innerHeight, 1, 1000 );
    camera.name = 'Камера';
    camera.position.y = 10;

    //Сцена
    scene = new THREE.Scene();
    scene.name = 'Сцена';
    scene.background = new THREE.Color( 0xffffff );
    scene.fog = new THREE.Fog( 0xffffff, 0, 750 );

    //Свет
    const light = new THREE.HemisphereLight( 0xeeeeff, 0x777788, 2.5 );
    light.name = 'Освещение';
    light.position.set( 0.5, 1, 0.75 )
    scene.add( light );

    //Управление
    controls = new PointerLockControls( camera, document.body );

    //Элементы
    const blocker = document.getElementById( 'blocker' );
    const instructions = document.getElementById( 'instructions' );

    //Направляющие
    // X - Красная
    // Y - Зеленая
    // Z - Синяя
    const axesHelper = new THREE.AxesHelper(100);
    //scene.add( axesHelper );

    //Лазерная подсветка
    const laser = new THREE.Mesh(
        new THREE.CylinderGeometry( 0.1, 0.1, 100, 32 ),
        new THREE.MeshBasicMaterial( { color: 0x0000ff })
    );
    scene.add( laser );

    instructions.addEventListener( 'click', function () {
        controls.lock();
    });

    controls.addEventListener( 'lock', function () {
        instructions.style.display = 'none';
        blocker.style.display = 'none';
    });

    controls.addEventListener( 'unlock', function () {
        blocker.style.display = 'block';
        instructions.style.display = '';
    });

    scene.add( controls.getObject() );

    const onKeyDown = function ( event ) {
        switch ( event.code ) {
            case 'ArrowUp':
            case 'KeyW':
                moveForward = true;
                break;
            case 'ArrowLeft':
            case 'KeyA':
                moveLeft = true;
                break;
            case 'ArrowDown':
            case 'KeyS':
                moveBackward = true;
                break;
            case 'ArrowRight':
            case 'KeyD':
                moveRight = true;
                break;
            case 'Space':
                if ( canJump === true ) velocity.y += 350;
                canJump = false;
                break;
        }

    };

    const onKeyUp = function ( event ) {
        switch ( event.code ) {
            case 'ArrowUp':
            case 'KeyW':
                moveForward = false;
                break;
            case 'ArrowLeft':
            case 'KeyA':
                moveLeft = false;
                break;

            case 'ArrowDown':
            case 'KeyS':
                moveBackward = false;
                break;

            case 'ArrowRight':
            case 'KeyD':
                moveRight = false;
                break;
        }

    };

    function onKeyPress( event ) {
        //console.log('onKeyPRESS '+event.code);
        if(event.code == 'KeyH')
        {
            //console.log('Export START');
            const json = scene.toJSON();
            //console.log(JSON.stringify(json));
            downloadFile('scene.json',JSON.stringify(json));
            //const exporter = new OBJExporter();

            // Parse the input and generate the OBJ output
            //const data = exporter.parse( scene );
            //console.log(data);
            //downloadFile( data );
        }
    }

    document.addEventListener( 'keydown', onKeyDown );
    document.addEventListener( 'keyup', onKeyUp );
    document.addEventListener( 'keypress', onKeyPress );

    raycaster = new THREE.Raycaster( new THREE.Vector3(), new THREE.Vector3( 0, - 1, 0 ), 0, 10 );

    // floor

    let floorGeometry = new THREE.PlaneGeometry( 2000, 2000, 100, 100 );
    floorGeometry.rotateX( - Math.PI / 2 );

    // vertex displacement

    let position = floorGeometry.attributes.position;

    for ( let i = 0, l = position.count; i < l; i ++ ) {

        vertex.fromBufferAttribute( position, i );

        vertex.x += Math.random() * 20 - 10;
        vertex.y += Math.random() * 2;
        vertex.z += Math.random() * 20 - 10;

        position.setXYZ( i, vertex.x, vertex.y, vertex.z );

    }

    floorGeometry = floorGeometry.toNonIndexed(); // ensure each face has unique vertices

    position = floorGeometry.attributes.position;
    const colorsFloor = [];

    for ( let i = 0, l = position.count; i < l; i ++ ) {

        color.setHSL( Math.random() * 0.3 + 0.5, 0.75, Math.random() * 0.25 + 0.75, THREE.SRGBColorSpace );
        colorsFloor.push( color.r, color.g, color.b );
    }

    floorGeometry.setAttribute( 'color', new THREE.Float32BufferAttribute( colorsFloor, 3 ) );

    const floorMaterial = new THREE.MeshBasicMaterial( { vertexColors: true } );

    floor = new THREE.Mesh( floorGeometry, floorMaterial );
    scene.add( floor );

    //Кубики

    /*const boxGeometry = new THREE.BoxGeometry( 20, 20, 20 ).toNonIndexed();

    position = boxGeometry.attributes.position;
    const colorsBox = [];

    for ( let i = 0, l = position.count; i < l; i ++ ) {

        color.setHSL( Math.random() * 0.3 + 0.5, 0.75, Math.random() * 0.25 + 0.75, THREE.SRGBColorSpace );
        colorsBox.push( color.r, color.g, color.b );

    }

    boxGeometry.setAttribute( 'color', new THREE.Float32BufferAttribute( colorsBox, 3 ) );

    for ( let i = 0; i < 500; i ++ ) {

        const boxMaterial = new THREE.MeshPhongMaterial( { specular: 0xffffff, flatShading: true, vertexColors: true } );
        boxMaterial.color.setHSL( Math.random() * 0.2 + 0.5, 0.75, Math.random() * 0.25 + 0.75, THREE.SRGBColorSpace );

        const box = new THREE.Mesh( boxGeometry, boxMaterial );
        box.position.x = Math.floor( Math.random() * 20 - 10 ) * 20;
        box.position.y = Math.floor( Math.random() * 20 ) * 20 + 10;
        box.position.z = Math.floor( Math.random() * 20 - 10 ) * 20;

        scene.add( box );
        objects.push( box );

    }*/

    //

    renderer = new THREE.WebGLRenderer( { antialias: true } );
    renderer.setPixelRatio( window.devicePixelRatio );
    renderer.setSize( window.innerWidth, window.innerHeight );
    document.body.appendChild( renderer.domElement );

    //

    window.addEventListener( 'resize', onWindowResize );

}

function onWindowResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize( window.innerWidth, window.innerHeight );
}

function animate() {
    requestAnimationFrame( animate );
    const time = performance.now();
    //Управление
    if ( controls.isLocked === true ) {
        raycaster.ray.origin.copy( controls.getObject().position );
        raycaster.ray.origin.y -= 10;
        const intersections = raycaster.intersectObjects( objects, false );
        const onObject = intersections.length > 0;
        const delta = ( time - prevTime ) / 1000;
        velocity.x -= velocity.x * 10.0 * delta;
        velocity.z -= velocity.z * 10.0 * delta;
        velocity.y -= 9.8 * 100.0 * delta; // 100.0 = mass
        direction.z = Number( moveForward ) - Number( moveBackward );
        direction.x = Number( moveRight ) - Number( moveLeft );
        direction.normalize(); // this ensures consistent movements in all directions
        if ( moveForward || moveBackward ) velocity.z -= direction.z * 400.0 * delta;
        if ( moveLeft || moveRight ) velocity.x -= direction.x * 400.0 * delta;
        if ( onObject === true ) {
            velocity.y = Math.max( 0, velocity.y );
            canJump = true;
        }
        controls.moveRight( - velocity.x * delta );
        controls.moveForward( - velocity.z * delta );
        controls.getObject().position.y += ( velocity.y * delta ); // new behavior
        if ( controls.getObject().position.y < 10 ) {
            velocity.y = 0;
            controls.getObject().position.y = 10;
            canJump = true;
        }
    }
    prevTime = time;

    //Луч
    // Создаем новый Raycaster с этим направлением.
    const raycasterCursor = new THREE.Raycaster(camera.position, direction);
    const intersects = raycasterCursor.intersectObjects(scene.children, true);
    // Рисуем луч от камеры до ближайшего пересечения.
    if (intersects.length > 0) {
        const geometry = new THREE.BufferGeometry();
        geometry.vertices.push(camera.position);
        geometry.vertices.push(intersects[0].point);
        const material = new THREE.LineBasicMaterial({color: 0xff0000});
        const line = new THREE.Line(geometry, material);
        scene.add(line);
    } else {
        // Если пересечений нет, удаляем луч.
        //scene.remove(line);
    }

    //РЕНДЕР
    renderer.render( scene, camera );

    //Устанавливаем таймер если его нет для обработки объектов
    if(timerUpdate === undefined)
    {
        timerUpdate = setInterval(getProjects,5000);
    }
}

// Функция для создания и скачивания файла
function downloadFile(filename, content) {
    // Создаем Blob объект из текста
    const blob = new Blob([content], {type: 'text/plain'});
  
    // Создаем URL из Blob
    const url = window.URL.createObjectURL(blob);
  
    // Создаем временный элемент <a> для скачивания файла
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click(); // Имитируем клик по ссылке для скачивания
  
    // Очищаем URL после скачивания
    window.URL.revokeObjectURL(url);
}

var painted = false;
//Функция обновления проектов
function updateProjects(data){
    if(objects.projects === undefined){

        objects.projects = [];
    }
    if(painted)
    {
        return
    }
    if(objects.projects.length != Object.keys(data).length){
        //removeProjects();
        //Пересобираем кубики с проектами
        objects.projects = [];
        var i = 0;
        var j = 0;
        $.each(data,function(k,v){

            //Проверяем текстуру
            if(textures.projects===undefined)
            {
                textures.projects = [];
                textures.projects[k] = createTextureForProject(k);
            }
            else
            {
                if(textures.projects[k]!== undefined){
                    console.log('Текструра существует генерация не требуется')
                }
                else
                {
                    textures.projects[k] = createTextureForProject(k);
                }
            }

            const cubeGeometry = new THREE.BoxGeometry( 20, 20, 20 ).toNonIndexed();

            const textureLoader = new THREE.TextureLoader();
            const texture = textureLoader.load(textures.projects[k]);

            const cubeMaterial = new THREE.MeshBasicMaterial({ map: texture });

            objects.projects[k] = new THREE.Mesh(cubeGeometry, cubeMaterial);
            objects.projects[k].name = 'project'+k;
            
            // X - Красная
            // Y - Зеленая
            // Z - Синяя
            console.log('Рисую')
            objects.projects[k].position.x = (i*20)+ i*5;
            objects.projects[k].position.y = ((j*20) + j*5)+20;
            objects.projects[k].position.z = 0;
            scene.add(objects.projects[k]);
            i++;
            if(i==2)
            {
                j++;
                i=0;
            }
        });
    }
    painted = true;
    console.log(objects);
}

//Функция получения проектов
function getProjects(){
    var fd = new FormData();
    fd.append('command','getProjects');
    $.ajax({
        url:"/control/workspace/projects",
        type: 'get',
        data: fd,
        dataType:'json',
        success:function(data){
            updateProjects(data);
        }
    });
}

//Функция создания текстур
function createTextureForProject(number){
    // Получаем элемент canvas
    var canvas = document.getElementById('genTextures');
    var ctx = canvas.getContext('2d');

    // Устанавливаем размеры canvas
    canvas.width = 150;
    canvas.height = 150;

    // Рисуем фон
    ctx.fillStyle = '#f0f0f0';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.font = 'bold 60px Arial';
    ctx.fillStyle = '#333';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(number, canvas.width / 2, canvas.height / 2);
    var dataURL = canvas.toDataURL();
    return dataURL;
}

//Функция удаления проектов
function removeProjects(){
    scene.traverse( function( object ) {
        if(object.isMesh){
            console.log('Название объекта'+object.name);
            if (object.name.includes('project'))
            {
                console.log("Удаляем");
                scene.remove(object);
            }
        }
    });
}