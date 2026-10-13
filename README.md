# Faculty Consultation Booking System

A web-based consultation booking platform for universities, built with native PHP, MySQL, HTML5, CSS3, and JavaScript.

## Features
- 🔍 Browse and filter faculty by department and consultation mode
- 📅 Interactive booking modal with real-time slot selection
- 👥 Role-based access (students, faculty, admin)
- ✅ Faculty can approve/decline/complete requests
- 📊 Admin dashboard with faculty activity reports
- 🔒 Secure with PDO prepared statements, CSRF protection, hashed passwords

## Tech Stack
- **Backend:** PHP 8+, MySQL/MariaDB
- **Frontend:** HTML5, CSS3, vanilla JavaScript
- **Server:** XAMPP (Apache + MySQL)

## Setup
1. Clone the repo
2. Move to `C:\xampp\htdocs\`
3. Import `database.sql` into phpMyAdmin
4. Copy `config/db.example.php` → `config/db.php` and fill credentials
5. Visit `http://localhost/faculty-booking/`

## Demo Accounts (password: `password123`)
| Role | Email |
|------|-------|
| Admin | admin@university.edu |
| Faculty | elena.marquez@university.edu |
| Student | mark.villanueva@student.edu |
