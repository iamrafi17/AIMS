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
- Laravel 12 (compatible with Laravel 11)
- Laravel Sanctum for authentication
- SQLite (can be switched to MySQL)
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
- Travel monitoring with checkpoints
- Announcement system
- Report generation

## Installation

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

# Run migrations and seed database
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

## Sample Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@marsu.edu.ph | password |
| Coordinator | coordinator@marsu.edu.ph | password |
| Program Head | prohead@marsu.edu.ph | password |
| VPAA | vpaa@marsu.edu.ph | password |
| Supervisor | supervisor@marsu.edu.ph | password |
| Student | student@marsu.edu.ph | password |

## API Endpoints

### Authentication
- `POST /api/login` - User login
- `POST /api/register` - Student registration
- `POST /api/logout` - User logout
- `GET /api/user` - Get current user

### Student
- `GET /api/student/dashboard` - Student dashboard
- `GET /api/student/attendance` - Attendance history
- `POST /api/student/attendance/clock-in` - Clock in
- `POST /api/student/attendance/clock-out` - Clock out
- `GET /api/student/requirements` - List requirements
- `POST /api/student/requirements/{id}/upload` - Upload requirement

### Coordinator
- `GET /api/coordinator/dashboard` - Coordinator dashboard
- `GET /api/coordinator/students` - List students
- `POST /api/coordinator/students/{id}/approve` - Approve registration
- `POST /api/coordinator/students/{id}/reject` - Reject registration

### Admin
- `GET /api/admin/dashboard` - Admin dashboard
- `GET /api/admin/users` - List users
- `POST /api/admin/users` - Create user
- `PUT /api/admin/users/{id}` - Update user
- `DELETE /api/admin/users/{id}` - Delete user

## Database Schema

The application uses the following main tables:
- users
- students
- colleges
- programs
- htes (Host Training Establishments)
- attendance
- internship_requirements
- announcements
- travel_logs
- travel_checkpoints
- evaluations
- audit_logs
- moas (Memorandum of Agreement)
- holidays

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

### Environment Variables

Backend `.env`:
```
APP_NAME="AIMS - MarSU Santa Cruz"
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

Frontend `.env`:
```
VITE_API_URL=http://localhost:8000/api
```

## License

This project is for educational purposes at MarSU Santa Cruz Campus.
# AIMS
