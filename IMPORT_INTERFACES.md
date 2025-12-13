# Import Sync Interfaces

## Overview
Система імпорту даних має три інтерфейси для запуску синхронізації:
1. **Artisan Command** - `php artisan import:sync`
2. **Filament Action** - UI сторінка в адмін-панелі
3. **API Endpoint** - REST API для програмного доступу

## 1. Artisan Command

### Використання
```bash
# Останні 7 днів (за замовчуванням)
php artisan import:sync

# Одна дата
php artisan import:sync --date=2022-07-02

# Діапазон дат
php artisan import:sync --from=2022-07-01 --to=2022-07-31

# Останні 30 днів
php artisan import:sync --last-days=30

# Тільки витрати
php artisan import:sync --only=expenses

# Тільки замовлення
php artisan import:sync --only=orders

# Кастомний розмір чанку
php artisan import:sync --chunk=500
```

### Особливості
- ✅ Автоматично перевіряє підключення до зовнішньої БД перед імпортом
- ✅ Показує прогрес-бар для великих обсягів даних
- ✅ Виводить детальну статистику після завершення
- ✅ Логує всі операції в `storage/logs/laravel.log`

## 2. Filament Action

### Доступ
Filament → **Import Sync** (в навігації)

### Функціонал
1. **Test Connection** - перевірка підключення до зовнішньої БД
2. **Start Import** - запуск імпорту з формою параметрів:
   - Single Date / Date Range / Last N Days
   - Import Only (Expenses/Orders/All)
   - Chunk Size
   - Test Connection First (toggle)

### Особливості
- ✅ Візуальний інтерфейс з формами
- ✅ Нотифікації про успіх/помилки
- ✅ Автоматична перевірка підключення (опціонально)
- ✅ Логування всіх операцій

## 3. API Endpoint

### Endpoints

#### Test Connection
```http
GET /api/import/test-connection
Authorization: Bearer {token}
```

**Response:**
```json
{
  "connected": true,
  "message": "Successfully connected to external database"
}
```

#### Sync Data
```http
POST /api/import/sync
Authorization: Bearer {token}
Content-Type: application/json

{
  "date": "2022-07-02",           // Optional: single date
  "from": "2022-07-01",           // Optional: start date (requires 'to')
  "to": "2022-07-31",             // Optional: end date (requires 'from')
  "last_days": 30,                 // Optional: last N days
  "only": "expenses",              // Optional: "expenses" | "orders"
  "chunk": 100                     // Optional: chunk size (default: 100)
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Import completed successfully",
  "date_range": {
    "from": "2022-07-01",
    "to": "2022-07-31"
  },
  "stats": {
    "expenses": {
      "created": 150,
      "updated": 25,
      "skipped": 5,
      "errors": 0
    },
    "orders": {
      "created": 200,
      "updated": 50,
      "skipped": 10,
      "errors": 0
    }
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Import failed: {error message}"
}
```

### Особливості
- ✅ Автоматична перевірка підключення перед імпортом
- ✅ Валідація параметрів
- ✅ Детальна статистика в відповіді
- ✅ Логування всіх операцій

## Логування

### Розташування
Всі операції імпорту логуються в: **`storage/logs/laravel.log`**

### Типи логів

#### Успішні операції
```
[2025-12-13 10:00:00] local.INFO: Created new customer {"customer_id":123,"email":"test@example.com"}
[2025-12-13 10:00:01] local.INFO: Created new order {"order_id":456,"OrderID":789}
[2025-12-13 10:00:02] local.INFO: Import sync completed via API {"date_range":"2022-07-01 to 2022-07-31","stats":{...}}
```

#### Попередження (пропущені записи)
```
[2025-12-13 10:00:00] local.WARNING: Order references non-existent Product {"OrderID":123,"BrandID":999}
[2025-12-13 10:00:01] local.WARNING: OrderItem references non-existent ProductItem {"idOrderItem":456,"ItemID":999}
```

#### Помилки
```
[2025-12-13 10:00:00] local.ERROR: Failed to import order {"OrderID":123,"error":"...","trace":"..."}
[2025-12-13 10:00:01] local.ERROR: Import sync failed via API {"error":"...","trace":"..."}
```

### Перегляд логів

#### Через Artisan
```bash
php artisan pail
```

#### Через файл
```bash
tail -f storage/logs/laravel.log
```

#### Через Filament (якщо є Log Viewer)
Filament → Logs

## Перевірка підключення

Всі три інтерфейси автоматично перевіряють підключення до зовнішньої БД перед імпортом:

1. **Artisan Command** - перевіряє при старті команди
2. **Filament Action** - має окрему кнопку "Test Connection" + опціональна перевірка перед імпортом
3. **API Endpoint** - автоматично перевіряє перед імпортом + окремий endpoint `/api/import/test-connection`

### Конфігурація підключення
Параметри зовнішньої БД налаштовуються в `.env`:
```env
SS_HOST=127.0.0.1
SS_PORT=3306
SS_DATABASE=forge
SS_USERNAME=forge
SS_PASSWORD=your_password
```

## Безпека

- ✅ API endpoints вимагають аутентифікації (`auth:sanctum`)
- ✅ Filament actions доступні тільки авторизованим користувачам
- ✅ Artisan команда може бути запущена тільки з консолі
- ✅ Зовнішня БД доступна тільки для читання (read-only)
