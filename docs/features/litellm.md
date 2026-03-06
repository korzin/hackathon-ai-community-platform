# LiteLLM

Документація по локальному LiteLLM gateway у AI Community Platform.

## Призначення

`LiteLLM` є platform-owned LLM proxy для локального середовища:

- дає єдину точку доступу до моделей для агентів;
- ізолює provider credentials від агентного коду;
- уніфікує OpenAI-compatible API (`/v1/...`).

## Доступ

- URL: `http://localhost:4000`
- Admin shortcut: `Admin -> Інструменти -> LiteLLM`
- API auth: `Authorization: Bearer dev-key` (local default)
- UI login: `http://localhost:4000/ui/login` (працює тільки з підключеною DB)
- UI credentials (local): `admin` / `dev-key`

## Креденшели

### 1) Доступ до LiteLLM API

- Ключ API gateway: `dev-key`
- Джерело: `compose.yaml` -> `litellm.environment.LITELLM_MASTER_KEY`

Цей ключ використовують агенти для викликів LiteLLM у local dev.

### 2) Доступ LiteLLM до OpenRouter

- Провайдерський ключ: `OPENROUTER_API_KEY`
- Джерело: `.env.local`
- Прокидка в контейнер: `compose.yaml` -> `litellm.env_file` + `litellm.environment`

Без валідного `OPENROUTER_API_KEY` LiteLLM не зможе виконувати completion/embed виклики до OpenRouter.
Ключ читається з `.env.local` через `compose.yaml -> litellm.env_file`.

## LLM Trace Context

Щоб у LiteLLM логах було видно корисний контекст, агенти додають у LLM-запити:

- заголовки: `X-Request-Id`, `X-Service-Name`, `X-Agent-Name`, `X-Feature-Name`
- додатково `X-Trace-Id` (коли доступний trace context)
- поле `user` у payload з тегами `service`, `feature`, `request_id`
- поле `metadata` у chat completion викликах (`request_id`, `service_name`, `agent_name`, `feature_name`, `trace_id`)

Це застосовано для `hello-agent`, `knowledge-agent` і `news-maker-agent`.

### 3) LiteLLM DB (для `/ui/login`)

- `DATABASE_URL=postgresql://app:app@postgres:5432/litellm`
- Конфігуровано в `compose.yaml` + `docker/litellm/config.yaml`
- БД `litellm` створюється у fresh setup через `docker/postgres/init/02_create_litellm_db.sql`

Для вже існуючого Postgres volume треба одноразово створити БД вручну:

```bash
make litellm-db-init
```

## Моделі (поточний local preset)

Конфіг: `docker/litellm/config.yaml`

- `minimax/minimax-m2.5` -> `openrouter/minimax/minimax-m2.5`
- `gpt-4o-mini` -> alias на `openrouter/minimax/minimax-m2.5` (compat для існуючих агентів)

За замовчуванням агенти в цьому репозиторії використовують `minimax/minimax-m2.5` через LiteLLM.

## Troubleshooting

### `Authentication Error, Not connected to DB!`

Симптом: помилка на `http://localhost:4000/ui/login`.

Причина: LiteLLM не має доступу до Postgres DB `litellm` (часто на старому `postgres-data` volume).

Виправлення:

```bash
docker compose up -d postgres
make litellm-db-init
docker compose logs --tail=100 litellm
```

## Швидка перевірка

### Список моделей

```bash
docker compose exec litellm python - <<'PY'
import urllib.request
req = urllib.request.Request(
    'http://127.0.0.1:4000/v1/models',
    headers={'Authorization': 'Bearer dev-key'},
)
with urllib.request.urlopen(req, timeout=5) as r:
    print(r.status)
    print(r.read().decode('utf-8'))
PY
```

Очікування: HTTP `200`, у відповіді є `minimax/minimax-m2.5` і `gpt-4o-mini`.

### Перевірка completion

```bash
curl -sS http://localhost:4000/v1/chat/completions \
  -H 'Authorization: Bearer dev-key' \
  -H 'Content-Type: application/json' \
  -d '{
    "model": "minimax/minimax-m2.5",
    "messages": [{"role":"user","content":"ping"}]
  }'
```

## Ротація ключів (local)

1. Змінити `LITELLM_MASTER_KEY` у `compose.yaml` (або винести в env).
2. Оновити `LITELLM_API_KEY` у сервісах, що викликають LiteLLM.
3. Перезапустити:

```bash
docker compose up -d litellm core knowledge-agent knowledge-worker news-maker-agent
```
