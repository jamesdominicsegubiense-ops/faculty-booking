# Requirements Document — Faculink
## Faculty Consultation Booking System

**Course:** [BSIT 3A]
**Team:** [FacuLink]
**Date:** October 2026

---

## 1. Users and Stakeholders

### Primary Users

| User | Description |
|------|-------------|
| **Student** | Undergraduate/graduate students who need to book consultations with professors for academic advising, thesis review, or problem-set help. |
| **Faculty** | Professors and instructors who publish their availability and manage consultation requests. |
| **Admin** | Department staff or system administrators who manage accounts and monitor activity. |

### Stakeholders

| Stakeholder | Interest |
|-------------|----------|
| **Departments** | Ensures system security, uptime, and integration with university email/LDAP (future). |
| **Students** | Need quick, reliable access to faculty consultation slots. |
| **Faculty** | Need control over their schedule without administrative overhead. |

---

## 2. Functional Requirements

### 2.1 Student Features

| ID | Requirement |
|----|-------------|
| FR-S1 | Students shall be able to register an account using name, email, and password. |
| FR-S2 | Students shall be able to log in and log out securely. |
| FR-S3 | Students shall be able to browse a list of all active faculty members. |
| FR-S4 | Students shall be able to filter faculty by department and consultation mode. |
| FR-S5 | Students shall be able to search faculty by name or expertise. |
| FR-S6 | Students shall be able to view available weekly time slots for any faculty member. |
| FR-S7 | Students shall be able to book a consultation slot by providing a date, purpose, and optional notes. |
| FR-S8 | Students shall not be able to double-book a slot already taken. |
| FR-S9 | Students shall be able to view the status of every request (Pending, Approved, Completed, Declined). |
| FR-S10 | Students shall be able to cancel a pending request. |

### 2.2 Faculty Features

| ID | Requirement |
|----|-------------|
| FR-F1 | Faculty shall be able to log in to a personal dashboard. |
| FR-F2 | Faculty shall be able to publish weekly consultation slots (day + start/end time). |
| FR-F3 | Faculty shall not be able to create overlapping slots on the same day. |
| FR-F4 | Faculty shall be able to enable / disable individual slots. |
| FR-F5 | Faculty shall be able to delete slots that have no upcoming bookings. |
| FR-F6 | Faculty shall be able to view all incoming consultation requests. |
| FR-F7 | Faculty shall be able to approve or decline pending requests. |
| FR-F8 | Faculty shall be able to mark approved, past-dated requests as completed. |
| FR-F9 | Faculty shall be able to edit their office location, consultation mode, and bio. |
| FR-F10 | Faculty department shall be set by admin only (read-only for faculty). |

### 2.3 Admin Features

| ID | Requirement |
|----|-------------|
| FR-A1 | Admin shall be able to log in to a control panel. |
| FR-A2 | Admin shall be able to create faculty accounts (name, email, password, department, mode). |
| FR-A3 | Admin shall be able to activate / deactivate any user. |
| FR-A4 | Admin shall be able to delete users (with cascade to their bookings and schedules). |
| FR-A5 | Admin shall not be able to delete themselves or the last remaining admin. |
| FR-A6 | Admin shall be able to view a report of faculty consultation activity. |
| FR-A7 | Admin shall be able to filter the report by date range. |

### 2.4 System Requirements

| ID | Requirement |
|----|-------------|
| FR-Y1 | System shall store users with role-based permissions (student, faculty, admin). |
| FR-Y2 | System shall hash passwords using bcrypt. |
| FR-Y3 | System shall prevent SQL injection via prepared statements. |
| FR-Y4 | System shall prevent CSRF attacks via token validation. |
| FR-Y5 | System shall prevent double-booking via transaction-level locks. |
| FR-Y6 | System shall enforce role-based access control on every page. |

---

## 3. Non-Functional Requirements

### Performance
- **NFR-P1:** Page loads shall complete within 2 seconds on a standard university network.
- **NFR-P2:** The system shall handle at least 100 concurrent users (target, not tested).

### Security
- **NFR-S1:** All user input shall be validated server-side.
- **NFR-S2:** All POST requests shall include and validate a CSRF token.
- **NFR-S3:** Passwords shall never be stored in plain text.
- **NFR-S4:** Session cookies shall be `httponly` and `samesite=Lax`.
- **NFR-S5:** Database credentials shall never be committed to version control.

### Usability
- **NFR-U1:** The UI shall be responsive on mobile, tablet, and desktop.
- **NFR-U2:** All forms shall include clear error messages.
- **NFR-U3:** Booking shall be completable in under 30 seconds.

### Reliability
- **NFR-R1:** The system shall use database transactions for critical operations (booking, user creation).
- **NFR-R2:** Failed operations shall leave the database in a consistent state.

### Maintainability
- **NFR-M1:** Code shall follow a consistent naming convention (snake_case for DB, camelCase for JS).
- **NFR-M2:** Every file shall include header comments describing its purpose.

### Compatibility
- **NFR-C1:** The system shall work on Chrome, Firefox, and Edge (latest versions).
- **NFR-C2:** The system shall run on PHP 8.0+ and MySQL 5.7+ / MariaDB 10.4+.

---

## 4. Use Cases

### UC-1: Student Books a Consultation

**Actor:** Student
**Preconditions:** Student is logged in.
**Main Flow:**
1. Student browses the faculty listing.
2. Student clicks "Book consultation" on a faculty card.
3. System opens a modal showing the faculty's weekly slots.
4. Student selects a slot and confirms the date.
5. Student enters the purpose and optional notes.
6. Student clicks "Submit request".
7. System validates input, checks for double-booking, and inserts the booking.
8. System returns a success message.

**Alternative Flows:**
- **4a.** Student picks a slot with no future dates → system shows "no slots available".
- **7a.** Slot was just taken → system returns "Please pick another time".
- **7b.** Validation fails → system lists errors.

**Postconditions:** Booking is stored with status `Pending`.

---

### UC-2: Faculty Approves a Request

**Actor:** Faculty
**Preconditions:** Faculty is logged in; at least one pending request exists.
**Main Flow:**
1. Faculty opens the dashboard.
2. Faculty clicks "Approve" on a pending request.
3. System updates status to `Approved`.
4. Faculty sees confirmation.

**Postconditions:** Booking status = `Approved`.

---

### UC-3: Admin Creates a Faculty Account

**Actor:** Admin
**Preconditions:** Admin is logged in.
**Main Flow:**
1. Admin fills out the "Create faculty account" form.
2. Admin submits.
3. System validates email uniqueness and inputs.
4. System inserts a `users` row (role=`faculty`) and a matching `faculty_profiles` row.
5. Admin sees a success message.

**Alternative Flows:**
- **3a.** Email already exists → system shows error.

**Postconditions:** Faculty user can now log in.

---

### UC-4: Faculty Publishes a Weekly Slot

**Actor:** Faculty
**Preconditions:** Faculty is logged in.
**Main Flow:**
1. Faculty opens "Schedule".
2. Faculty selects a day and start/end times.
3. Faculty clicks "Add slot".
4. System checks for overlaps.
5. System inserts the slot as active.

**Alternative Flows:**
- **4a.** Slot overlaps with existing → system shows error.

**Postconditions:** Slot appears on the faculty card for students.

---

### UC-5: Student Cancels a Pending Request

**Actor:** Student
**Preconditions:** Student has a booking with status `Pending`.
**Main Flow:**
1. Student opens "My Bookings".
2. Student clicks "Cancel request" on a pending booking.
3. System confirms deletion.
4. Booking is deleted from the database.

**Postconditions:** Slot is freed for other students.
