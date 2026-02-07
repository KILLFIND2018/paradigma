import os
import json
import torch
import uuid
import logging
from flask import Flask, request, jsonify
from flask_cors import CORS
from transformers import AutoModelForCausalLM, AutoTokenizer, SpeechT5Processor, SpeechT5ForTextToSpeech, SpeechT5HifiGan
from dotenv import load_dotenv
import scipy.io.wavfile

# Загрузка переменных окружения
load_dotenv()

# Настройка Flask
app = Flask(__name__)
CORS(app)

# Настройка логирования
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Глобальные переменные для моделей
models = {
    "gpt2": None,
    "speech": None
}

def load_models():
    """Загрузка AI моделей при старте сервиса"""
    try:
        token = os.getenv("HUGGINGFACE_HUB_TOKEN", "")

        logger.info("🔄 Loading GPT-2 model...")
        # GPT-2 для текста
        models["gpt2"] = {
            "tokenizer": AutoTokenizer.from_pretrained("gpt2"),
            "model": AutoModelForCausalLM.from_pretrained("gpt2")
        }

        logger.info("✅ GPT-2 loaded")

        logger.info("🔄 Loading SpeechT5 model...")
        # SpeechT5 для генерации речи
        models["speech"] = {
            "processor": SpeechT5Processor.from_pretrained("microsoft/speecht5_tts"),
            "model": SpeechT5ForTextToSpeech.from_pretrained("microsoft/speecht5_tts"),
            "vocoder": SpeechT5HifiGan.from_pretrained("microsoft/speecht5_hifigan")
        }

        logger.info("✅ All models loaded successfully")
        return True

    except Exception as e:
        logger.error(f"❌ Error loading models: {str(e)}")
        return False

@app.route('/health', methods=['GET'])
def health():
    """Проверка работоспособности сервиса"""
    return jsonify({
        "status": "ok",
        "service": "llm-api",
        "models_loaded": models["gpt2"] is not None
    })

@app.route('/generate', methods=['POST'])
def generate():
    """Основной endpoint для генерации текста и речи"""
    try:
        data = request.json

        if not data or 'message' not in data:
            return jsonify({
                "success": False,
                "error": "No message provided"
            }), 400

        message = data.get('message', '')
        user_id = data.get('user_id', 'anonymous')

        logger.info(f"📨 Received message from {user_id}: {message[:50]}...")

        # 1. Генерация текста с помощью GPT-2
        if not models["gpt2"]:
            return jsonify({
                "success": False,
                "error": "Text model not loaded"
            }), 503

        # Токенизация и генерация
        inputs = models["gpt2"]["tokenizer"].encode(message, return_tensors='pt')
        outputs = models["gpt2"]["model"].generate(
            inputs,
            max_length=100,
            num_return_sequences=1,
            temperature=0.7,
            do_sample=True
        )

        generated_text = models["gpt2"]["tokenizer"].decode(outputs[0], skip_special_tokens=True)

        # Очистка текста (удаляем повторяющиеся фразы)
        generated_text = generated_text.replace(message, "").strip()
        if not generated_text:
            generated_text = "Я получил ваше сообщение. Чем еще могу помочь?"

        logger.info(f"📝 Generated text: {generated_text[:100]}...")

        # 2. Генерация речи (опционально)
        audio_filename = None

        if models["speech"] and data.get('generate_audio', False):
            try:
                audio_filename = f"{uuid.uuid4().hex}.wav"
                audio_path = os.path.join('audio', audio_filename)

                # Создаем директорию если нет
                os.makedirs('audio', exist_ok=True)

                # Генерация речи
                speech_inputs = models["speech"]["processor"](
                    generated_text,
                    return_tensors="pt"
                )
                speaker_embeddings = torch.zeros(1, 512)  # Простые эмбеддинги

                with torch.no_grad():
                    speech = models["speech"]["model"].generate_speech(
                        speech_inputs["input_ids"],
                        speaker_embeddings,
                        vocoder=models["speech"]["vocoder"]
                    )

                # Сохраняем аудиофайл
                scipy.io.wavfile.write(audio_path, 16000, speech.numpy())
                logger.info(f"🔊 Audio saved: {audio_filename}")

            except Exception as e:
                logger.error(f"Audio generation error: {str(e)}")
                audio_filename = None

        # Подготовка ответа
        response = {
            "success": True,
            "text": generated_text,
            "audio_filename": audio_filename,
            "user_id": user_id,
            "processing_time": 0.5  # Можно вычислять реальное время
        }

        logger.info(f"✅ Response generated for {user_id}")
        return jsonify(response)

    except Exception as e:
        logger.error(f"❌ Error in /generate: {str(e)}")
        return jsonify({
            "success": False,
            "error": str(e),
            "fallback_text": f"Я получил ваше сообщение: '{message if 'message' in locals() else ''}'. Пожалуйста, попробуйте еще раз."
        }), 500

@app.route('/audio/<filename>', methods=['GET'])
def get_audio(filename):
    """Получение аудиофайла"""
    try:
        audio_path = os.path.join('audio', filename)

        if not os.path.exists(audio_path):
            return jsonify({"error": "Audio file not found"}), 404

        from flask import send_file
        return send_file(audio_path, mimetype='audio/wav')

    except Exception as e:
        return jsonify({"error": str(e)}), 500

# Загружаем модели при импорте
load_models()

if __name__ == '__main__':
    logger.info("🚀 Starting LLM Service on port 5000...")
    app.run(host='0.0.0.0', port=5000, debug=False)