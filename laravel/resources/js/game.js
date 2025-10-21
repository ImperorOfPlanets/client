// resources/js/game.js
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';

window.runGame2 = function() {
    console.log('runGame2 called');
    // Создаем сцену, камеру и рендерер
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer();

    // Устанавливаем размер рендерера
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.body.appendChild(renderer.domElement); 

    const light = new THREE.AmbientLight(0x404040);  // Добавьте освещение
    scene.add(light);

    const loader = new GLTFLoader();
    let mixer;
    let model;

    loader.load('/animations/scene.gltf', (gltf) => {
        console.log('GLTF loaded successfully');
        
        // Check if gltf.scene is valid
        if (gltf.scene && typeof gltf.scene === 'object') {
            model = gltf.scene;
            scene.add(model);
            mixer = new THREE.AnimationMixer(model);

            gltf.animations.forEach((clip) => {
                mixer.clipAction(clip).play();
            });

            model.scale.set(0.5, 0.5, 0.5);
            model.position.set(0, 0, 0);
            //update();
            console.log('Model added to scene:', !!model);
        } else {
            console.error('Invalid GLTF scene');
        }
    }, undefined, (error) => {
        console.error("Model loading error:", error);
    });

    let isRunning = false;
    let walkAction, runAction;

    const keys = {
        'ArrowUp': false,
        'ArrowDown': false,
        'Shift': false,
    };

    document.addEventListener('keydown', (event) => {
        if (event.key in keys) {
            keys[event.key] = true;
        }
    });

    document.addEventListener('keyup', (event) => {
        if (event.key in keys) {
            keys[event.key] = false;
        }
    });

    function update() {
        if (mixer && model) {
            mixer.update(0.01);

                if (keys['ArrowUp']) {
                    if (keys['Shift']) {
                        if (!isRunning) {
                            isRunning = true;
                            runAction = mixer.clipAction('Run');
                            runAction.play();
                            walkAction.stop();
                        }
                    } else {
                        if (isRunning) {
                            isRunning = false;
                            walkAction = mixer.clipAction('Walk');
                            walkAction.play();
                            runAction.stop();
                        }
                    }
                }

        if (model) {
            const speed = isRunning ? 0.1 : 0.05;
            //model.position.z -= speed;
        }

            // Add these lines for debugging
        console.log('Scene children:', scene.children.length);
        console.log('Model exists:', !!model);
        console.log('Model position:', model.position);

        renderer.render(scene, camera);
        requestAnimationFrame(update);
    }

    camera.position.z = 5;
    //update();
}


window.runGame = function() {
    console.log('runGame')
    // Проверяем поддержку WebGL

    const width = window.innerWidth;
    const height = window.innerHeight;

    // Создаем сцену, камеру и рендерер
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer();

    renderer.setSize(window.innerWidth, window.innerHeight);
    document.body.appendChild(renderer.domElement);

    // Освещение
    const light = new THREE.DirectionalLight(0xffffff, 1);
    light.position.set(10, 10, 10);
    scene.add(light);

    const ambientLight = new THREE.AmbientLight(0x404040); // Мягкий свет
    scene.add(ambientLight);

    // Главный герой (временная модель - куб)
    const heroGeometry = new THREE.BoxGeometry(1, 1, 1);
    const heroMaterial = new THREE.MeshStandardMaterial({ color: 0x00ff00 });
    const hero = new THREE.Mesh(heroGeometry, heroMaterial);
    hero.position.set(0, 0, 0);
    scene.add(hero);

    // Платформы
    const platformGeometry = new THREE.BoxGeometry(3, 0.5, 1);
    const platformMaterial = new THREE.MeshStandardMaterial({ color: 0x808080 });
    const platforms = [];

    function generatePlatforms() {
        for (let i = 0; i < 10; i++) {
            const platform = new THREE.Mesh(platformGeometry, platformMaterial);
            platform.position.set(
                (Math.random() - 0.5) * 10, // случайная позиция по X
                i * 3,                     // нарастающая позиция по Y
                0                          // фиксированная позиция по Z
            );
            platforms.push(platform);
            scene.add(platform);
        }
    }
    generatePlatforms();

    // Кристаллы (временные модели - сферы)
    const crystalGeometry = new THREE.SphereGeometry(0.5, 16, 16);
    const crystalMaterial = new THREE.MeshStandardMaterial({ color: 0xffff00 });
    const crystals = [];

    function generateCrystals() {
        for (let i = 0; i < 5; i++) {
            const crystal = new THREE.Mesh(crystalGeometry, crystalMaterial);
            const platform = platforms[Math.floor(Math.random() * platforms.length)];
            crystal.position.set(platform.position.x, platform.position.y + 1, 0);
            crystals.push(crystal);
            scene.add(crystal);
        }
    }
    generateCrystals();

    // Управление камерой
    camera.position.z = 10;
    camera.position.y = 5;
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;

    // Логика управления героем
    const keys = {};
    window.addEventListener('keydown', (e) => keys[e.key] = true);
    window.addEventListener('keyup', (e) => keys[e.key] = false);

    function handleHeroMovement() {
        if (keys['ArrowLeft']) hero.position.x -= 0.1;
        if (keys['ArrowRight']) hero.position.x += 0.1;
        if (keys[' ']) hero.position.y += 0.2; // Прыжок (упрощенный)

        // Гравитация
        hero.position.y -= 0.1;

        // Проверка падения ниже уровня платформ
        platforms.forEach(platform => {
            if (
                hero.position.x > platform.position.x - 1.5 &&
                hero.position.x < platform.position.x + 1.5 &&
                hero.position.y < platform.position.y + 0.5 &&
                hero.position.y > platform.position.y
            ) {
                hero.position.y = platform.position.y + 1; // Герой стоит на платформе
            }
        });
    }

    // Основной цикл
    function animate() {
        requestAnimationFrame(animate);
        handleHeroMovement();
        controls.update();
        renderer.render(scene, camera);
    }
    animate();

    /*const loader = new GLTFLoader();
    let mixer;
    let model;

    loader.load('/animations/scene.gltf', (gltf) => {
        model = gltf.scene;
        scene.add(model);
        mixer = new THREE.AnimationMixer(model);

        gltf.animations.forEach((clip) => {
            mixer.clipAction(clip).play();
        });

        model.scale.set(0.5, 0.5, 0.5);
        model.position.set(0, 0, 0);
    }, undefined, (error) => {
        console.error("Model loading error:", error);  // Поймать ошибки
    });

    let isRunning = false;
    let walkAction, runAction;

    const keys = {
        'ArrowUp': false,
        'ArrowDown': false,
        'Shift': false,
    };

    document.addEventListener('keydown', (event) => {
        if (event.key in keys) {
            keys[event.key] = true;
        }
    });

    document.addEventListener('keyup', (event) => {
        if (event.key in keys) {
            keys[event.key] = false;
        }
    });

    function update() {
        if (mixer) {
            mixer.update(0.01);
        }

        if (keys['ArrowUp']) {
            if (keys['Shift']) {
                if (!isRunning) {
                    isRunning = true;
                    runAction = mixer.clipAction('Run');
                    runAction.play();
                    walkAction.stop();
                }
            } else {
                if (isRunning) {
                    isRunning = false;
                    walkAction = mixer.clipAction('Walk');
                    walkAction.play();
                    runAction.stop();
                }
            }
        }

        if (model) {
            const speed = isRunning ? 0.1 : 0.05;
            //model.position.z -= speed;
        }

        renderer.render(scene, camera);
        requestAnimationFrame(update);
    }

    camera.position.z = 5;
    update();*/

    window.addEventListener('resize', () => {
        const width = window.innerWidth;
        const height = window.innerHeight;
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height);
    });
}
