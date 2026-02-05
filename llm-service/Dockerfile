FROM python:3.9

ENV TZ=Europe/Moscow
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

# Системные зависимости
RUN apt-get update && apt-get install -y \
    git \
    curl \
    ffmpeg \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# pip
RUN pip install --upgrade pip

# requirements
COPY requirements.txt .

# Установка PyTorch CPU из официального индекса
RUN pip install torch==2.8.0 --index-url https://download.pytorch.org/whl/cpu

# Остальные зависимости
RUN pip install --no-cache-dir -r requirements.txt

# Код
COPY . .

# Папки
RUN mkdir -p audio logs models

# Пользователь
RUN useradd -m -u 1000 appuser && chown -R appuser:appuser /app
USER appuser

EXPOSE 5000
CMD ["python", "app.py"]
