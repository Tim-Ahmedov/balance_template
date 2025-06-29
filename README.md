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

## Сценарии разблокировки средств (unlock)

В системе блокировки средств реализованы два сценария разблокировки:

1. **Списание заблокированной суммы** (`confirm: true`):
   - Заблокированная сумма списывается окончательно, баланс пользователя не меняется (средства уже были вычтены при lock), статус блокировки меняется на `charged`.

2. **Возврат заблокированной суммы** (`confirm: false`):
   - Заблокированная сумма возвращается пользователю на баланс, статус блокировки меняется на `unlocked`.

### Примеры сообщений

**Сценарий 1: Списание заблокированной суммы**
```json
{
  "operation": "unlock",
  "user_id": 123,
  "amount": 30.0,
  "operation_id": "unique-op-id-5",
  "lock_id": "lock-abc-1",
  "confirm": true
}
```
- Баланс не меняется.
- В `locked_funds` статус: `charged`.

**Сценарий 2: Возврат заблокированной суммы**
```json
{
  "operation": "unlock",
  "user_id": 123,
  "amount": 30.0,
  "operation_id": "unique-op-id-6",
  "lock_id": "lock-abc-1",
  "confirm": false
}
```
- Баланс увеличивается на 30.0.
- В `locked_funds` статус: `unlocked`.

> Оба сценария требуют, чтобы операция `lock` была проведена ранее (lock_id должен существовать и быть в статусе `locked`).
> 
> - Операция `unlock` с `confirm: true` используется, когда внешняя система подтвердила списание (например, успешная оплата).
> - Операция `unlock` с `confirm: false` используется, когда операция отменена (например, отказ в оплате).

## Техническая реализация операций блокировки и разблокировки

### Операция блокировки (lock)
1. Начинается транзакция БД.
2. Пользователь выбирается с блокировкой строки (`forUpdate`).
3. Проверяется достаточность баланса.
4. Баланс пользователя уменьшается на сумму блокировки.
5. В таблицу `locked_funds` добавляется запись со статусом `locked`.
6. В таблицу `transaction` добавляется запись о блокировке.
7. Транзакция коммитится.
8. В очередь отправляется событие `funds_locked`.
9. Все шаги логируются через Yii2-логгер.

**Псевдокод:**
```
BEGIN;
SELECT ... FOR UPDATE;
IF balance < amount: ROLLBACK;
UPDATE user SET balance = balance - amount;
INSERT INTO locked_funds (..., status='locked');
INSERT INTO transaction (..., type='lock');
COMMIT;
SEND_EVENT('funds_locked');
```

### Операция разблокировки (unlock)
1. Начинается транзакция БД.
2. Ищется запись в `locked_funds` по lock_id, user_id и статусу `locked`.
3. Если `confirm=true`:
    - Статус блокировки меняется на `charged` (средства окончательно списаны).
    - Баланс не меняется.
4. Если `confirm=false`:
    - Пользователь выбирается с блокировкой строки (`forUpdate`).
    - Баланс увеличивается на сумму блокировки.
    - Статус блокировки меняется на `unlocked` (средства возвращены).
5. В таблицу `transaction` добавляется запись о разблокировке.
6. Транзакция коммитится.
7. В очередь отправляется событие `funds_unlocked` (со статусом `charged` или `unlocked`).
8. Все шаги логируются через Yii2-логгер.

**Псевдокод:**
```
BEGIN;
SELECT * FROM locked_funds WHERE id=lock_id AND user_id=? AND status='locked';
IF confirm=true:
    UPDATE locked_funds SET status='charged';
ELSE:
    SELECT user FOR UPDATE;
    UPDATE user SET balance = balance + amount;
    UPDATE locked_funds SET status='unlocked';
INSERT INTO transaction (..., type='unlock');
COMMIT;
SEND_EVENT('funds_unlocked');
```

> Все действия атомарны, используются транзакции и row-level locking для предотвращения гонок и двойных списаний.
> Все изменения логируются (info/error) и сопровождаются событием в очередь.

## Установка и запуск

1. Убедитесь, что установлены Docker и Docker Compose.
2. Клонируйте репозиторий:
   ```sh
   git clone <repo-url>
   cd <project-folder>
   ```
3. Запустите проект:
   ```sh
   docker-compose up --build
   ```
   - При первом запуске автоматически будут установлены все зависимости composer и применены все миграции к базе данных.
   - Приложение будет доступно на http://localhost:8080 (или другой порт, если изменён в docker-compose.yml).

**Всё! Не требуется вручную запускать composer install или миграции — всё происходит автоматически при старте контейнера.**

- Для тестов: `docker-compose exec app vendor/bin/codecept run`
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