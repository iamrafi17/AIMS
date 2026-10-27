# Academic Internship Monitoring System (AIMS)

A full-stack web application for MarSU Santa Cruz Campus to monitor and manage student internships.

## Tech Stack

### Frontend
- React 18+ with Vite
- React Router DOM v6
- Axios for API calls
- Tailwind CSS for styling
- React Icons & Heroicons
- Recharts for data visualization
- Context API for state management
- React Hot Toast for notifications

### Backend
- Laravel 12 (the Laravel 11 PRD architecture with current security fixes)
- Laravel Sanctum for authentication
- MySQL or MariaDB
- Eloquent ORM

## Features

### User Roles
1. **Student Intern** - Dashboard, attendance, requirements, travel monitoring
2. **Internship Coordinator** - Student management, attendance verification, HTE management
3. **Program Head** - Document review, travel monitoring, reports
4. **VPAA** - Approvals, MOA management, announcements
5. **Admin** - User management, system settings, audit logs
6. **Supervisor** - Student progress, evaluations

### Core Modules
- Authentication with role-based access
- Multi-step student registration
- GPS-enabled attendance tracking
- Internship requirements management
- Coordinator-scheduled, student-controlled travel monitoring with GPS departure, checkpoints, arrival, and photo evidence
- Announcement system
- Report generation

## Installation

Start MySQL in XAMPP, then run the backend and frontend in separate terminals. In `aims-backend`, run `php artisan serve` and wait for port 8000 to be ready. In `aims-frontend`, run `npm run dev`. Keep both terminals open while using the system.

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- npm

### Backend Setup

```bash
cd aims-backend

# Install dependencies
composer install

# Generate application key
php artisan key:generate

# Run migrations and seed reference data
php artisan migrate:fresh --seed

# Start development server
php artisan serve
```

The backend will run on `http://localhost:8000`

### Frontend Setup

```bash
cd aims-frontend

# Install dependencies
npm install

# Start development server
npm run dev
```

The frontend will run on `http://localhost:5173`

The production-safe seeder creates reference data only. Create authorized accounts through the administrator workflow; no shared sample credentials are installed.



## Project Structure

```
AIMS/
├── aims-backend/          # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   └── ...
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── routes/
│       └── api.php
│
└── aims-frontend/         # React Frontend
    └── src/
        ├── components/
        ├── context/
        ├── layouts/
        ├── pages/
        │   ├── auth/
        │   ├── student/
        │   ├── coordinator/
        │   ├── program-head/
        │   ├── vpaa/
        │   ├── admin/
        │   └── supervisor/
        ├── routes/
        └── services/
```

## Development

### Adding New Features

1. Create migration in `database/migrations/`
2. Create model in `app/Models/`
3. Create controller in `app/Http/Controllers/Api/`
4. Add routes in `routes/api.php`
5. Create React page in `src/pages/`
6. Add route in `src/App.jsx`

## Operations

- [Student deployment workflow](docs/DEPLOYMENT_WORKFLOW.md)

- [Scaling and production setup](aims-backend/docs/SCALING.md)
- [Database and private-file backup/restore](aims-backend/docs/BACKUP_AND_RESTORE.md)

## License

This project is for educational purposes at MarSU Santa Cruz Campus.
# AIMS
