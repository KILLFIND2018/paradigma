<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Paradigma Chat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Three.js и другие библиотеки -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <style>
        /* Стили из оригинального index.html */
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #2c3e50;
            color: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            overflow: hidden;
        }

        .chat-container {
            width: 100%;
            max-width: 1200px;
            height: 90vh;
            display: flex;
            gap: 20px;
            flex-direction: row;

        }

        .model-container {
            flex: 3;
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            background: #1a252f;
        }

        .chat-interface {
            flex: 2;
            display: flex;
            flex-direction: column;
            background: #34495e;
            border-radius: 10px;
            padding: 20px;
        }

        #messages {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            padding: 10px;
            background: #2c3e50;
            border-radius: 5px;
        }

        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 10px;
            max-width: 80%;
        }

        .user-message {
            background: #3498db;
            margin-left: auto;
        }

        .ai-message {
            background: #2ecc71;
            margin-right: auto;
        }

        .input-area {
            display: flex;
            gap: 10px;
        }

        #message-input {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 5px;
            background: #ecf0f1;
            color: #2c3e50;
        }

        .send-button {
            padding: 10px 20px;
            background: #1abc9c;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
        }

        #typing-indicator {
            display: none;
            padding: 10px;
            color: #95a5a6;
        }

        .typing-dots span {
            animation: blink 1.4s infinite;
            animation-delay: calc(var(--i) * 0.2s);
        }

        @keyframes blink {
            0%, 100% { opacity: 0.2; }
            50% { opacity: 1; }
        }

        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
            }

        }
    </style>
</head>
<body>
<div class="chat-container">
    <!-- Контейнер для 3D модели -->
    <div class="model-container">
        <div id="loading">Загрузка модели...</div>
    </div>

    <!-- Интерфейс чата -->
    <div class="chat-interface">
        <div id="messages">
            <!-- Сообщения будут здесь -->
        </div>

        <div id="typing-indicator" class="typing-dots">
            <span style="--i: 0">.</span>
            <span style="--i: 1">.</span>
            <span style="--i: 2">.</span>
        </div>

        <div class="input-area">
            <input type="text" id="message-input" placeholder="Введите сообщение...">
            <button class="send-button">Отправить</button>
        </div>
    </div>
</div>

<script>
    // Текущий пользователь (в реальности из сессии/куки)
    const userId = 'user_' + Math.random().toString(36).substr(2, 9);

    // Three.js инициализация
    let scene, camera, renderer, currentModel;

    function init3D() {
        scene = new THREE.Scene();
        camera = new THREE.PerspectiveCamera(75, document.querySelector('.model-container').offsetWidth / document.querySelector('.model-container').offsetHeight, 0.1, 1000);
        camera.position.set(0, 0, 5);

        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(document.querySelector('.model-container').offsetWidth, document.querySelector('.model-container').offsetHeight);
        document.querySelector('.model-container').appendChild(renderer.domElement);

        // Освещение
        const light = new THREE.DirectionalLight(0xffffff, 1);
        light.position.set(5, 5, 5);
        scene.add(light);

        const ambientLight = new THREE.AmbientLight(0x404040);
        scene.add(ambientLight);

        // Загрузка модели
        const loader = new THREE.GLTFLoader();
        loader.load(
            '/static/models/android.glb',
            function(gltf) {
                currentModel = gltf.scene;
                currentModel.scale.set(2, 2, 2);
                scene.add(currentModel);

                // Важно: привязываем анимации к модели
                if (gltf.animations && gltf.animations.length > 0) {
                    currentModel.animations = gltf.animations;
                    console.log('Загружены анимации:', gltf.animations.map(a => a.name));
                }

                document.getElementById('loading').style.display = 'none';
            },
            undefined,
            function(error) {
                console.error('Ошибка загрузки модели:', error);
                document.getElementById('loading').textContent = 'Ошибка загрузки модели';
            }
        );

        // Анимация
        function animate() {
            requestAnimationFrame(animate);

            // Обновляем микшер анимаций
            if (animationMixer) {
                const delta = clock.getDelta();
                animationMixer.update(delta);
            }

            renderer.render(scene, camera);
        }
        animate();
    }

    // Функции чата
    function addMessage(text, isUser = false) {
        const messagesDiv = document.getElementById('messages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isUser ? 'user-message' : 'ai-message'}`;
        messageDiv.textContent = text;
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;

        // Анимация "говорения" для AI сообщений
        if (!isUser) {
            startTalkingAnimation();
        }
    }

    let animationMixer = null;
    let currentAction = null;
    let clock = new THREE.Clock();

    function startTalkingAnimation() {
        if (currentModel && currentModel.animations) {
            if (!animationMixer) {
                animationMixer = new THREE.AnimationMixer(currentModel);
            }

            if (currentAction) {
                currentAction.stop();
                currentAction = null;
            }

            const speakingAnimation = currentModel.animations.find(anim => anim.name === '3Speaking');

            if (speakingAnimation) {
                console.log('Запускаем анимацию говорения');

                currentAction = animationMixer.clipAction(speakingAnimation);

                // Если анимация короткая (например, 1-2 секунды), повторяем её
                const animationDuration = speakingAnimation.duration * 1000; // в миллисекундах

                // Устанавливаем количество повторений на 3 секунды
                const repeatCount = Math.ceil(3000 / (animationDuration));
                currentAction.setLoop(THREE.LoopRepeat, repeatCount);

                currentAction.play();

                // Останавливаем через 3 секунды
                setTimeout(() => {
                    if (currentAction) {
                        currentAction.stop();
                        currentAction = null;
                        console.log('Анимация говорения остановлена');
                    }
                }, 3000);
            }
        }
    }


    async function sendMessage() {
        const input = document.getElementById('message-input');
        const message = input.value.trim();

        if (!message) return;

        // Добавляем сообщение пользователя
        addMessage(message, true);
        input.value = '';

        // Показываем индикатор набора
        document.getElementById('typing-indicator').style.display = 'block';

        try {
            // Отправляем на Laravel API
            const response = await axios.post('/api/chat/send', {
                message: message,
                user_id: userId
            });

            document.getElementById('typing-indicator').style.display = 'none';

            if (response.data.success) {
                // Добавляем ответ AI
                addMessage(response.data.data.text, false);

                // Воспроизводим аудио, если есть
                if (response.data.data.audio_url) {
                    const audio = new Audio(response.data.data.audio_url);
                    audio.play();
                }
            } else {
                addMessage('Ошибка: ' + response.data.error, false);
            }

        } catch (error) {
            document.getElementById('typing-indicator').style.display = 'none';
            addMessage('Ошибка соединения с сервером', false);
            console.error('Error:', error);
        }
    }

    // Инициализация
    window.addEventListener('DOMContentLoaded', () => {
        init3D();

        // Обработчики событий
        document.querySelector('.send-button').addEventListener('click', sendMessage);
        document.getElementById('message-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Приветственное сообщение
        setTimeout(() => {
            addMessage('Привет! Я Paradigma AI. Чем могу помочь?', false);
        }, 1000);
    });

    // Ресайз окна
    window.addEventListener('resize', () => {
        if (camera && renderer) {
            camera.aspect = document.querySelector('.model-container').offsetWidth / document.querySelector('.model-container').offsetHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(document.querySelector('.model-container').offsetWidth, document.querySelector('.model-container').offsetHeight);
        }
    });
</script>
</body>
</html>
