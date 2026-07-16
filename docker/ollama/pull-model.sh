#!/bin/sh
# Waits for Ollama, then pulls the configured model (compose profile `llm`).
set -eu

export OLLAMA_HOST="${OLLAMA_HOST:-http://ollama:11434}"
MODEL="${OLLAMA_MODEL:-llama3.1:8b}"

echo "Waiting for Ollama at ${OLLAMA_HOST}..."
i=0
while [ "$i" -lt 60 ]; do
  if ollama list >/dev/null 2>&1; then
    break
  fi
  i=$((i + 1))
  sleep 2
done

if ! ollama list >/dev/null 2>&1; then
  echo "Ollama did not become ready in time." >&2
  exit 1
fi

echo "Pulling model ${MODEL} (needs disk + preferably a strong GPU)..."
ollama pull "${MODEL}"
echo "Model ${MODEL} ready."
