# EduLearn LMS

PHP + MySQL + JavaScript LMS with Admin, Instructor and Learner roles.

## Installation

1. Create a MySQL database by importing `database.sql`.
2. Open `includes/db_connect.php`.
3. Update the database credentials.
4. Place the project inside your PHP server directory.
5. Make sure the `uploads` folder is writable by the web server.
6. Open `index.php`.

## Default admin

Admin Number: ADMIN001
Password: password

Change the password immediately in a production environment.
If the admin password is ever lost, run `php reset_password.php` from the
command line on the server. It cannot be run from a browser.

## Main modules

- Authentication and sessions
- Role-based access control
- CSRF protection on every form that changes data
- Admin user management
- Admin class management
- Course management
- Class assignment
- Instructor course access
- Learning material uploads and permission-checked downloads
- MCQ quizzes
- Quiz attempts and final-attempt logic
- Answer review for a submitted attempt
- Learner progress across lectures and quizzes
- REST API proxy: internal authentication endpoint and an external quotes API

## Roles at a glance

| Feature | Learner | Instructor | Admin |
|---|---|---|---|
| Courses | Sees enrolled courses | Sees assigned courses | Full CRUD |
| Classes | - | - | Full CRUD |
| Users | - | Sees own students | Full CRUD |
| Learning material | Download, mark complete | Upload, delete | Download any |
| Quizzes | Take and review | Create, add questions, view results | - |
| Progress | Own only | Own students | Everyone |
| Profile | Yes | Yes | Yes |

Every page checks the role on the server with `requireRole()`. Hiding a menu
item is never the only protection.

## Learning material

Instructors upload lecture files from **Material** in the sidebar. A lecture is
a name plus a file, stored in the `lectures` table.

- Accepted types: pdf, doc, docx, ppt, pptx, xls, xlsx, txt, zip, png, jpg, jpeg
- Maximum size: 10 MB
- Files are saved in `uploads/` under a random name, and that folder denies
  direct web access
- Every download goes through `download.php`, which allows an admin, the
  course instructor, or a learner enrolled in the course, and nobody else

Learners open **Learning Material** from a course card, download each lecture,
and mark it complete.

## Progress

Progress counts both parts of a course:

    percentage = (lectures completed + quizzes attempted) /
                 (total lectures + total quizzes) x 100

The calculation lives in `includes/progress.php` and runs whenever a learner
completes a lecture or submits a quiz, and whenever an instructor adds or
removes a lecture (because the course total has changed).

## Quizzes

- Instructors create a quiz for a course assigned to them, then add MCQ
  questions with four options and one correct answer.
- Learners take a quiz and see their score immediately.
- Retakes are allowed. Every attempt is kept, and **the last attempt is the
  final result** - earlier attempts are marked as superseded.
- **Review Answers** on any attempt shows each question with the option the
  learner chose and the correct one. Instructors can review attempts on their
  own quizzes.

## Web service integration

Both endpoints live in `api_proxy/` and return JSON.

| Endpoint | Purpose |
|---|---|
| `api_proxy/auth_endpoint.php` | Internal REST endpoint that verifies credentials |
| `api_proxy/auth_api.php` | Client used by `index.php` to call the endpoint over HTTP |
| `api_proxy/quote_api.php` | Reads a motivational quote from a free external API |

**Sign-in** posts the credentials to the authentication endpoint and reads the
JSON verdict. If the service cannot be reached, the login falls back to
checking the password locally so nobody is ever locked out.

**Daily Motivation** appears on all three dashboards. `quote_api.php` calls
[ZenQuotes](https://zenquotes.io/api/random) and falls back to
[DummyJSON](https://dummyjson.com/quotes/random) if that is unavailable.
Neither service needs an API key. The result is cached for one hour so a busy
dashboard does not send a request per page view, and `assets/js/Lms.js` fetches
it with JavaScript after the page loads. If the internet is down the card says
so and the rest of the dashboard is unaffected.

## Project files

    index.php               Login (REST authentication with local fallback)
    register.php            Learner self-registration
    logout.php              Ends the session
    profile.php             Personal details and password, all roles
    download.php            Permission-checked lecture downloads
    reset_password.php      Command-line admin password reset
    admin/                  dashboard, manage_users, manage_classes,
                            manage_courses, progress
    instructor/             dashboard, manage_quiz, manage_questions,
                            manage_lectures, view_results, view_students
    learner/                dashboard, view_courses, view_lectures,
                            take_quiz, quiz_results, quiz_review
    includes/               auth (sessions, roles, CSRF), db_connect,
                            progress, header, footer, delete_guard
    api_proxy/              auth_api, auth_endpoint, quote_api
    assets/css/Lms.css      All styling, light and dark mode
    assets/js/Lms.js        Sidebar, dark mode, form checks, quote card
    uploads/                Uploaded lecture files (direct access denied)
    database.sql            Full schema and starting data

## Security notes

- Passwords are hashed with `password_hash()`; plain text is never stored.
- Every query uses prepared statements.
- All output passes through `e()` to prevent XSS.
- Every data-changing form carries a CSRF token checked by `requireCsrf()`.
- Uploads are limited by type and size, stored under random names, and served
  only through a permission check.
- Instructors can only touch their own quizzes, questions and material;
  learners can only see courses they are enrolled in.
