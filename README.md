# Office Gym Nutrition Planner

A full-stack web application that helps office employees hit their fitness goals using only the cafeteria menu. The admin uploads the weekly menu; AI (Gemini) analyzes nutrition; a PHP optimizer matches dishes to each user's calorie/macro targets.

## Stack

| Layer | Tech |
|-------|------|
| Backend | Laravel 11 (PHP 8.2+) |
| Database | MySQL 8 |
| AI | Google Gemini API |
| Jobs | Laravel Queue (database driver) |
| Frontend | Vite + React 18 + TypeScript + Tailwind CSS |
| Charts | Recharts |
| Auth | JWT (php-open-source-saver/jwt-auth) |

## Deployment (Hostinger)

Both frontend and backend can be hosted on **Hostinger basic shared hosting** (PHP + MySQL).

### Backend

1. Upload the `backend/` folder contents to Hostinger via File Manager or FTP.
2. Set the **Document Root** / webroot to `backend/public/` in your Hostinger cPanel.
3. Copy `.env.example` to `.env` and fill in your Hostinger MySQL credentials and Gemini API key.
4. Run in the terminal (SSH or Hostinger terminal):
   ```bash
   php artisan key:generate
   php artisan jwt:secret
   php artisan migrate --force
   php artisan db:seed
   ```
5. In **cPanel → Cron Jobs**, add (runs every minute to process AI jobs):
   ```
   * * * * * /usr/local/bin/php /home/YOUR_USERNAME/public_html/backend/artisan queue:work --once
   ```

### Frontend

1. Set your backend URL in `frontend/.env`:
   ```
   VITE_API_URL=https://yourdomain.hostinger.com/api
   ```
2. Build:
   ```bash
   cd frontend
   npm install
   npm run build
   ```
3. Upload contents of `frontend/dist/` to `public_html/` (or a subdomain).

---

## Local Development

### Prerequisites

- PHP 8.2+, Composer 2
- MySQL 8 (or XAMPP / Laragon)
- Node.js 18+
- PHP extensions: `ext-sodium`, `ext-pdo_mysql`, `ext-mbstring`, `ext-xml`, `ext-curl`

### Backend

```bash
cd backend
cp .env.example .env
# Edit .env — set DB credentials and GEMINI_API_KEY
composer install
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan db:seed
php artisan serve          # runs on http://localhost:8000
```

In a second terminal (process queue jobs):
```bash
php artisan queue:work --tries=3
```

### Frontend

```bash
cd frontend
cp .env.example .env       # VITE_API_URL=http://localhost:8000/api
npm install
npm run dev                # runs on http://localhost:5173
```

---

## Default Accounts

| Role     | Email                  | Password      |
|----------|------------------------|---------------|
| Admin    | admin@company.com      | Admin@123     |
| Employee | employee@company.com   | Employee@123  |

**Change these in `.env` before deploying!**

---

## Usage

### Admin Flow

1. Log in as admin → **Menus** tab
2. Click **Import Menu JSON** and paste your weekly menu:
   ```json
   {
     "2026-06-23": {
       "breakfast": ["Poha", "Milk", "Boiled Eggs"],
       "lunch":     ["Rice", "Dal Tadka", "Paneer Butter Masala", "Roti"],
       "snacks":    ["Sprouts Chaat", "Tea"],
       "dinner":    ["Chicken Curry", "Rice", "Salad"]
     }
   }
   ```
3. New dishes are automatically queued for Gemini AI nutrition analysis.
4. Monitor job status in **AI Jobs** tab — retry any failed jobs.

### Employee Flow

1. Register → complete the **Gym Goal Wizard** (4 steps)
2. View **Today's Meal Plan** on the Dashboard
3. Track weight in **Progress** → see weight trend chart
4. Update goals anytime in **Profile** (targets recalculate automatically)

---

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | /api/auth/register | — | Register |
| POST | /api/auth/login | — | Login → returns JWT + refresh token |
| POST | /api/auth/refresh | — | Refresh access token |
| POST | /api/auth/logout | JWT | Revoke tokens |
| GET | /api/users/profile | JWT | Get profile + targets |
| PUT | /api/users/profile | JWT | Update profile → recalculates BMR/TDEE/macros |
| GET | /api/menus | JWT | List menus (filter by ?date=) |
| POST | /api/menus | Admin | Create menu |
| POST | /api/menus/import | Admin | Bulk JSON import |
| GET | /api/dishes | JWT | List/search dishes |
| PUT | /api/dishes/{id} | Admin | Edit dish nutrition |
| GET | /api/recommendations/today | JWT | Today's meal plan (auto-generates) |
| POST | /api/recommendations/generate | JWT | Force regenerate |
| GET | /api/recommendations/week | JWT | Mon–Sun overview |
| POST | /api/weight | JWT | Log weight |
| GET | /api/weight/history | JWT | Weight history (last 90 entries) |
| GET | /api/ai-jobs | Admin | List AI jobs |
| POST | /api/ai-jobs/{id}/retry | Admin | Retry failed job |

---

## Running Tests

### Backend (PHPUnit)

```bash
cd backend
php vendor/bin/phpunit tests/Unit/
```

Expected: **14 tests, 83 assertions — all pass.**

Tests cover:
- BMR calculation for both genders
- TDEE for all 5 activity levels
- Macro targets for all 3 goals
- Optimizer servings bounds (0.5–3.0)
- Optimizer meal-type isolation

---

## Fitness Formulas

**BMR (Mifflin-St Jeor):**
- Male: `10×weight + 6.25×height − 5×age + 5`
- Female: `10×weight + 6.25×height − 5×age − 161`

**TDEE:** `BMR × activity_multiplier`

**Goal targets:**
- Fat Loss: `calories = TDEE − 500`, `protein = weight × 2.2g`
- Maintenance: `calories = TDEE`, `protein = weight × 1.8g`
- Muscle Gain: `calories = TDEE + 300`, `protein = weight × 2.0g`
- Fat: `calories × 25% ÷ 9`
- Carbs: remaining calories ÷ 4
