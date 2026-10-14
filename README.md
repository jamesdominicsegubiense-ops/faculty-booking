# Faculink — Faculty Consultation Booking System

A web-based consultation booking platform for universities that makes it easy for students to book consultations with professors, for faculty to manage their availability, and for admins to oversee the whole system.

---

## 👥 Team Members

| Name | Role | GitHub |
|------|------|--------|
| [James Dominic Segubiense] | Lead Developer — Frontend, Backend, Database, Auth | [@jamesdominicsegubiense-ops](https://github.com/jamesdominicsegubiense-ops) |
| [Member 2 Full Name] | Design — UI/UX, CSS, JS | [@member2username](https://github.com/member2username) |
| [Member 3 Full Name] | QA / Documentation — Testing, Reports | [@member3username](https://github.com/member3username) |

---

## 🎯 Project Description

**Faculink** is a faculty consultation booking system built with native PHP (no framework) and MySQL. It provides a secure, role-based platform where:

- **Students** can browse faculty, filter by department/consultation mode, and book time slots.
- **Faculty** can publish weekly availability, approve/decline/complete consultation requests, and manage their public profile.
- **Admins** can create faculty accounts, monitor user activity, and view consultation reports.

The system emphasizes **security** (PDO prepared statements, CSRF protection, hashed passwords), **validation**, and **access control**.

---

## ✨ Features

### Student
- Browse faculty by name, department, or expertise
- Filter by department and consultation mode (In-Person / Online / Hybrid)
- Interactive booking modal with real-time slot selection
- Track request status (Pending → Approved → Completed / Declined)
- Cancel pending requests

### Faculty
- Dashboard with approve / decline / mark-as-complete actions
- Publish weekly consultation hours (with overlap prevention)
- Edit profile: office location, consultation mode, bio
- Locked department field (admin-managed only)

### Admin
- Create faculty accounts (with auto-generated passwords)
- View all users (activate / deactivate / delete)
- Faculty consultation report with activity metrics
- Date-range filter for analytics

### Security
- PDO prepared statements (SQL injection safe)
- CSRF tokens on all POST forms
- `password_hash()` / `password_verify()` (bcrypt)
- Session hardening (`httponly`, `samesite=Lax`, regeneration on login)
- Role-based access control (`require_role()`)
- Input validation on server + client

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.0+
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Dev Environment:** XAMPP (Apache + MySQL + phpMyAdmin)
- **Version Control:** Git + GitHub

---

## 📦 Installation / Setup Instructions

### Prerequisites
- XAMPP installed (or any LAMP/WAMP stack)
- Git installed
- A web browser

## 🤖 AI Assistance Disclosure

AI tools were used as a learning aid for:
- Boilerplate security patterns (PDO, CSRF, bcrypt)
- Debugging PHP errors
- Structuring this README

All code was understood, adapted, and tested by the team.
