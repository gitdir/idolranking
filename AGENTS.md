# IdolRanking - Codex Development Instructions

## 1. Project Overview

Project name: IdolRanking

IdolRanking is a Laravel-based idol ranking and global fan community web application.

### Local Development Environment

- OS: Windows 11
- Web Server: Apache from XAMPP
- PHP: 8.4.x
- Laravel: 13.x
- Database: MySQL 8.4 LTS
- Composer: latest stable
- Node.js: LTS
- Editor: Visual Studio Code
- AI Coding: OpenAI Codex

Local project path:

```text
D:\www\idolranking
```

Local URL:

```text
http://idolranking.test
```

### Production Environment

Production will use:

- Linux
- Apache
- PHP 8.4 / PHP-FPM
- MySQL 8.4 LTS
- Laravel 13

The local Apache environment should remain compatible with the production Apache environment as much as practical.

Apache-specific behavior should be testable locally before deployment.

---

## 2. Core Development Principles

Before modifying code:

1. Read and understand the existing related files.
2. Inspect the current implementation before changing it.
3. Do not modify unrelated files.
4. Do not perform unnecessary refactoring.
5. Do not replace working code without a clear reason.
6. Keep changes focused on the requested feature.
7. Preserve existing functionality.
8. Prefer simple and maintainable implementations.
9. Do not overengineer.
10. Do not add features that were not requested.

For large changes that affect architecture, database structure, authentication, Apache configuration, or multiple major features, provide a short implementation plan first.

For small and obvious changes, proceed without unnecessary clarification.

---

## 3. Laravel Rules

Use Laravel 13 conventions unless they conflict with this file.

### Eloquent

Do NOT use Eloquent ORM.

Do NOT create Eloquent Models unless explicitly requested by the user.

### Migration

Do NOT use Laravel migrations.

Do NOT use Laravel Schema Builder for database schema management.

Do NOT create migration files unless explicitly requested.

### Database Access

Use primarily:

- `DB` Facade
- Laravel Query Builder
- Database transactions
- Raw SQL only when Query Builder is not appropriate

Example:

```php
use Illuminate\Support\Facades\DB;

$rows = DB::table('example')
    ->where('status', 1)
    ->get();
```

Do not introduce Repository patterns, Service layers, DTOs, or other abstractions unless they provide a clear practical benefit.

Controllers may contain practical application logic, but avoid large duplicated blocks of code.

---

## 4. Database Rules

Database engine:

```text
MySQL 8.4 LTS
```

Default character set:

```text
utf8mb4
```

Default collation:

```text
utf8mb4_0900_ai_ci
```

Database schema must be managed using SQL files rather than Laravel migrations.

When database work begins, use the following project structure:

```text
/sql
├─ schema
├─ patches
└─ seed
```

### `/sql/schema`

Contains the current complete schema definition.

Example:

```text
/sql/schema/members.sql
/sql/schema/idols.sql
/sql/schema/idol_groups.sql
/sql/schema/votes.sql
```

### `/sql/patches`

Contains SQL required to update an existing database.

Use filenames such as:

```text
20260924_001_create_members.sql
20260924_002_create_idols.sql
20260924_003_add_status_to_members.sql
```

Patch SQL must be ordered and understandable.

### `/sql/seed`

Contains optional initial or reference data.

Example:

```text
/sql/seed/countries.sql
/sql/seed/default_settings.sql
```

Whenever a database structure changes:

1. Update the related schema SQL.
2. Create a patch SQL when needed.
3. Update PHP code that depends on the schema.
4. Consider indexes and constraints.
5. Do not assume Laravel migrations will ever be executed.

---

## 5. Database Query Rules

Avoid unnecessary queries.

Do not execute queries repeatedly inside loops when the same data can be retrieved efficiently in one query.

Avoid N+1 query patterns.

Use appropriate indexes for columns commonly used in:

- `WHERE`
- `JOIN`
- `ORDER BY`
- `GROUP BY`
- duplicate checks
- unique lookups

Use explicit ordering when result order matters.

Never rely on undefined database row ordering.

Use parameter binding or Query Builder.

Never concatenate untrusted user input directly into SQL.

---

## 6. Database Transaction Rules

Use transactions whenever multiple related database modifications must succeed or fail together.

Typical examples:

- deducting hearts and inserting heart history
- inserting a vote and updating related totals
- creating a member and related records
- approving idol registration requests
- operations modifying multiple related tables

Example:

```php
DB::transaction(function () {
    // related database operations
});
```

Do not leave partially completed database states.

Use row locking when race conditions could cause incorrect balances, votes, points, or duplicate processing.

---

## 7. Frontend Rules

Primary frontend stack:

- Blade
- HTML
- CSS
- JavaScript
- `fetch`
- AJAX
- JSON API

Use Blade for normal page rendering.

Use JavaScript and `fetch` for interactive features where a full page reload is unnecessary.

Prefer native JavaScript when it is sufficient.

Do NOT introduce the following unless explicitly requested:

- React
- Vue
- Inertia
- Livewire
- Alpine
- jQuery
- large frontend frameworks

Do not install unnecessary npm packages.

---

## 8. UI Development Rules

The site must be responsive.

Mobile usability must always be considered.

Avoid excessive UI complexity.

Prefer:

- clear layouts
- readable spacing
- reusable CSS
- predictable interactions
- fast page rendering

Do not create unnecessary animations or heavy visual effects.

Do not redesign unrelated pages while implementing a specific feature.

User-facing text may be Korean or multilingual.

Internal code names, PHP variables, database columns, routes, and API fields should generally use clear English names.

---

## 9. AJAX / API Rules

Interactive actions should generally communicate through JSON APIs.

Successful response example:

```json
{
    "success": true,
    "message": "처리가 완료되었습니다.",
    "data": {}
}
```

Failure response example:

```json
{
    "success": false,
    "message": "처리 중 오류가 발생했습니다."
}
```

Use appropriate HTTP status codes.

Typical examples:

```text
200 OK
201 Created
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
500 Internal Server Error
```

Always validate server-side input.

Never trust validation performed only in JavaScript.

---

## 10. Security Rules

Never expose secrets.

Never commit:

- `.env`
- passwords
- database credentials
- API keys
- private tokens
- access tokens
- secret keys

Do not hardcode secrets inside:

- PHP
- Blade
- JavaScript
- JSON
- Git-tracked configuration files

Use environment variables where appropriate.

Always validate user input.

Use CSRF protection for state-changing web requests.

Use Laravel Query Builder or proper parameter binding.

Escape user-generated output appropriately.

Prevent:

- SQL injection
- XSS
- CSRF
- privilege escalation
- insecure direct object references
- duplicate reward processing
- duplicate voting caused by race conditions

Authorization must always be checked server-side.

---

## 11. Authentication Rules

Do not automatically assume Laravel's default `users` table or default authentication structure will be used.

The IdolRanking project may use a custom member table and custom authentication flow.

Do not generate Laravel starter-kit authentication unless explicitly requested.

Session-based authentication is preferred unless requirements later change.

Authentication structure must be designed according to IdolRanking requirements before implementation.

---

## 12. Session, Cache, and Queue

Current local defaults:

```env
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Do not change these to database-backed drivers unless explicitly required.

If database-backed sessions, cache, or queues are later required:

- do not create Laravel migrations
- create the required SQL schema files under `/sql`
- update schema and patch files properly

---

## 13. Apache Rules

Local development uses Apache.

Production will also use Apache.

Laravel's `public` directory must always be the Apache DocumentRoot.

Local DocumentRoot:

```text
D:/www/idolranking/public
```

Local domain:

```text
idolranking.test
```

Do not create Nginx-specific dependencies.

Apache-related features may include:

- VirtualHost
- `.htaccess`
- `mod_rewrite`
- `mod_headers`
- `mod_expires`
- `mod_deflate`
- redirects
- caching rules
- security headers
- directory rules
- upload settings

When Apache configuration changes are necessary:

1. Clearly explain why the change is required.
2. Explain which local Apache file needs modification.
3. Explain whether the same setting is needed in production.
4. Avoid unrelated Apache configuration changes.

Do not modify Apache configuration merely to work around application bugs.

Prefer solving application problems inside Laravel when appropriate.

---

## 14. PHP Rules

Target PHP version:

```text
PHP 8.4
```

Use PHP 8.4-compatible syntax.

Prefer readable code.

Use early returns when they improve clarity.

Avoid deeply nested conditions.

Use strict comparisons where appropriate.

Do not suppress PHP errors with `@` unless there is an exceptional and documented reason.

Do not install Composer packages unless needed.

Before adding a new Composer dependency, check whether Laravel or PHP already provides the required functionality.

---

## 15. JavaScript / Node Rules

Node.js is primarily used for frontend development and asset building.

Do not assume Node.js must exist on the production server.

Production assets may be built locally before deployment.

Typical commands:

```bash
npm install
npm run dev
npm run build
```

Do not commit:

```text
node_modules/
```

Do commit dependency definitions such as:

```text
package.json
package-lock.json
```

Do not install frontend libraries unless there is a clear reason.

---

## 16. Composer Rules

Do not commit:

```text
vendor/
```

Do commit:

```text
composer.json
composer.lock
```

Production dependencies should normally be installed using:

```bash
composer install --no-dev --optimize-autoloader
```

Do not run `composer update` casually.

Prefer `composer install` when reproducing an existing project environment.

Do not modify `composer.lock` unnecessarily.

---

## 17. Git Rules

Primary branch:

```text
main
```

Do not commit:

```text
.env
vendor/
node_modules/
local logs
local cache files
secret credentials
temporary development files
```

Do not automatically run:

```text
git push
```

unless explicitly requested.

Do not automatically create commits unless explicitly requested.

Codex may show the commands the user should run, but should not silently push code.

Before completing major work, summarize:

- files created
- files modified
- database changes
- Apache/configuration changes
- dependencies added
- testing steps

---

## 18. File Modification Rules

Do not modify unrelated files.

Do not reformat entire files unnecessarily.

Avoid changing whitespace or code style in unrelated sections.

Do not rename existing files unless needed.

Do not rename database columns or routes without considering backward compatibility.

When deleting code, confirm it is no longer used.

Do not delete existing functionality solely because a different implementation appears cleaner.

---

## 19. Error Handling Rules

Do not silently ignore errors.

User-facing errors should be understandable.

AJAX/API errors should return JSON.

Do not expose the following to production users:

- stack traces
- database passwords
- SQL internals
- absolute filesystem paths
- sensitive environment variables

Detailed errors are acceptable during local development.

Application errors should be logged when appropriate.

---

## 20. Performance Rules

Avoid obvious performance problems.

Examples to avoid:

- querying the database repeatedly inside loops
- loading unnecessarily large result sets
- selecting columns that are not needed in large queries
- missing indexes on frequently searched columns
- recalculating expensive values repeatedly without reason

Use pagination for large lists.

Use database aggregation when appropriate.

Do not prematurely introduce complex caching systems.

Optimize only where there is a practical need.

---

## 21. IdolRanking Architecture Principles

IdolRanking is expected to include features such as:

- members
- idols
- idol groups
- rankings
- heart balances
- voting
- fan posts
- likes
- comments
- idol registration requests
- quizzes
- multilingual features
- admin management

Do not implement any of these features until requested.

Do not assume final table structures before they are designed.

Features should be implemented incrementally.

The application should remain easy to understand and maintain.

---

## 22. Idol Ranking Rules

When the ranking system is implemented:

- ranking must be based on actual votes given to idols
- member heart balance itself must not directly determine ranking
- vote processing must be transaction-safe
- heart deductions and vote records must remain consistent
- duplicate or race-condition processing must be prevented

Do not implement ranking logic until its detailed rules are confirmed.

---

## 23. Heart System Rules

When the heart system is implemented, prefer maintaining history records rather than only storing a mutable balance.

Possible heart history examples:

```text
+100 quiz reward
-50 idol vote
+10 event reward
```

Any heart balance modification must be auditable.

Operations that modify both heart balance and another table must use transactions.

Do not implement the final heart schema until requested.

---

## 24. Fan Post Rules

Fan post features may later include:

- image
- comment/text
- author
- likes
- comments
- created date
- idol association

Fan post likes and idol votes are separate concepts.

Do not mix fan post likes with idol ranking votes.

When implemented, representative idol images may later depend on fan post popularity.

Exact business rules must be confirmed before implementation.

---

## 25. Multilingual Rules

The site may support multiple interface languages.

Do not automatically machine-translate official idol or group names.

Official idol/group names may require explicit multilingual database fields.

User-generated content may later support optional translation.

Do not build translation infrastructure until requested.

---

## 26. Testing Rules

After modifying PHP:

- check syntax when appropriate
- verify affected routes
- test affected database operations

Useful commands:

```bash
php artisan route:list
php artisan optimize:clear
php artisan test
```

When frontend assets change:

```bash
npm run build
```

Do not claim a feature works unless there is a reasonable verification basis.

If something could not be tested, say so clearly.

---

## 27. Destructive Command Rules

Never execute destructive commands unless explicitly requested.

Examples requiring extreme caution:

```text
DROP DATABASE
DROP TABLE
TRUNCATE TABLE
DELETE without an appropriate WHERE condition
php artisan migrate:fresh
php artisan db:wipe
git reset --hard
git clean -fd
```

Do not delete production or user data.

When a potentially destructive operation is necessary, clearly explain it first.

---

## 28. Codex Workflow

For each task:

1. Read `AGENTS.md`.
2. Inspect relevant existing files.
3. Understand the existing implementation.
4. Determine the smallest appropriate change.
5. Modify only relevant files.
6. Check for syntax or obvious runtime problems.
7. Verify routes/database logic when relevant.
8. Summarize the work.
9. Explain how the user can test it.

For large features:

1. Inspect the project first.
2. Present a short implementation plan.
3. Wait for confirmation only when architecture or requirements are materially ambiguous.
4. Otherwise proceed with the agreed task.

Do not repeatedly ask unnecessary questions.

---

## 29. Codex Response Format After Implementation

After completing a coding task, provide a concise summary containing:

### Created

Files newly created.

### Modified

Files modified.

### Database

Schema, query, or SQL changes.

### Configuration

Apache, `.env`, Composer, npm, or other configuration changes.

### Test

How to verify the feature.

Do not produce unnecessarily long reports for very small changes.

---

## 30. Useful Commands

Laravel:

```bash
php artisan --version
php artisan optimize:clear
php artisan route:list
php artisan test
```

Composer:

```bash
composer install
composer dump-autoload
```

Node:

```bash
npm install
npm run dev
npm run build
```

Git:

```bash
git status
git add .
git commit -m "message"
git push
```

MySQL connection example:

```bash
mysql -h 127.0.0.1 -u idolranking -p idolranking
```

---

## 31. Current Development Philosophy

This project is currently in the early development stage.

Priority order:

1. correctness
2. maintainability
3. security
4. simplicity
5. performance
6. abstraction

Prefer:

- simple structure
- explicit code
- predictable behavior
- easy debugging
- easy maintenance

Avoid:

- premature abstractions
- unnecessary packages
- unnecessary frameworks
- large unrelated refactors
- speculative features

Build one feature at a time.

Preserve a stable working project after each feature.