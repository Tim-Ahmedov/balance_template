# User Balance Microservice

## Назначение
Микросервис реализует хранение и управление балансом пользователей. Все операции (списание, зачисление, перевод, блокировка, разблокировка) выполняются только по сообщениям из очереди RabbitMQ. После каждой операции генерируется событие в отдельную очередь.

## Архитектура
- **Ядро:** Yii2 Basic
- **Очередь:** RabbitMQ (через enqueue/amqp-lib)
- **БД:** MySQL
- **Взаимодействие:** только через очередь (нет REST API)
- **Idempotency:** операции уникальны по operation_id
- **Параллелизм:** безопасная обработка в нескольких воркерах

## Форматы сообщений

### Входящие операции (в очередь `balance`)

#### Списание (debit)
```json
{
  "operation": "debit",
  "user_id": 123,
  "amount": 100.0,
  "operation_id": "unique-op-id-1"
}
```

#### Зачисление (credit)
```json
{
  "operation": "credit",
  "user_id": 123,
  "amount": 100.0,
  "operation_id": "unique-op-id-2"
}
```

#### Перевод (transfer)
```json
{
  "operation": "transfer",
  "user_id": 123,           // отправитель
  "related_user_id": 456,   // получатель
  "amount": 50.0,
  "operation_id": "unique-op-id-3"
}
```

#### Блокировка (lock)
```json
{
  "operation": "lock",
  "user_id": 123,
  "amount": 30.0,
  "operation_id": "unique-op-id-4",
  "lock_id": "lock-abc-1"
}
```

#### Разблокировка (unlock)
```json
{
  "operation": "unlock",
  "user_id": 123,
  "amount": 30.0,
  "operation_id": "unique-op-id-5",
  "lock_id": "lock-abc-1",
  "confirm": false // true = списать, false = вернуть
}
```

### Ответы (в очередь не отправляются, только для тестов/отладки)
- `{ "status": "success" }`
- `{ "status": "error", "message": "..." }`
- `{ "status": "duplicate" }`

### События (exchange `balance_events_exchange`)

#### Пример события о смене баланса
```json
{
  "event": "balance_changed",
  "user_id": 123,
  "amount": -100.0,
  "operation": "debit",
  "operation_id": "unique-op-id-1",
  "status": "confirmed",
  "timestamp": "2024-06-11T12:00:00Z"
}
```

#### Пример события о блокировке
```json
{
  "event": "funds_locked",
  "user_id": 123,
  "amount": 30.0,
  "operation": "lock",
  "operation_id": "unique-op-id-4",
  "status": "locked",
  "timestamp": "2024-06-11T12:00:00Z"
}
```

#### Пример события о разблокировке
```json
{
  "event": "funds_unlocked",
  "user_id": 123,
  "amount": 30.0,
  "operation": "unlock",
  "operation_id": "unique-op-id-5",
  "lock_id": "lock-abc-1",
  "status": "unlocked",
  "timestamp": "2024-06-11T12:00:00Z"
}
```

## Запуск и тестирование
- Все настройки очереди и БД — в `app/config/*.php` и `docker-compose.yml`
- Для запуска: `docker-compose up --build`
- Для тестов: `vendor/bin/codecept run`
- Для локального тестирования очереди — используйте команды из `app/commands/`

## Структура проекта

```
balance/
  app/                # Исходный код приложения (Yii2)
    assets/
    commands/         # Консольные команды (воркеры, тесты)
    components/       # Компоненты (AMQP)
    config/           # Конфиги приложения
    controllers/
    migrations/       # Миграции БД
    models/
    services/         # Логика операций
    tests/            # Unit/functional/acceptance тесты
    views/
    web/              # Веб-энтрипоинт (не используется для API)
    widgets/
    ...
  docker-compose.yml  # Docker-оркестрация
  Dockerfile          # Dockerfile для приложения
  nginx.conf          # Конфиг nginx
  ...
```

--- 