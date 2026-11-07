FROM python:3.11-slim
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg libsndfile1 libgomp1 && rm -rf /var/lib/apt/lists/*
WORKDIR /srv
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY requirements-documents.txt .
RUN pip install --no-cache-dir -r requirements-documents.txt
COPY app ./app
ENV SPEECH_DATA=/data HF_HOME=/models HF_HUB_DISABLE_TELEMETRY=1
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "1"]
