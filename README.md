# PMS Drive

PMS Drive is a secure internal document workspace for Petroleum Marine Services (PMS). It centralizes offshore and project documents, enforces security policies, and provides role-based sharing and auditing so teams can collaborate without losing governance.

## Meaning and Purpose

Offshore operations generate critical documents that must be controlled, searchable, and traceable. PMS Drive provides a single place to upload, organize, share, and audit these files while keeping storage limits and security policies enforced by administrators.

## Key Modules

- Authentication and access control
	- Token-based login for the SPA and API
	- Role and permission management for granular access
- Drive and folder explorer
	- Personal root folders per employee
	- Folder tree navigation, file metadata, and downloads
- Upload and storage management
	- Quota enforcement and usage tracking
	- File checksum tracking and metadata capture
- Internal sharing
	- Share files or folders with permission and expiry rules
	- Sharing policy controls (enable/disable, limits)
- Trash and restore
	- Soft delete for files and folders with restore support
- Administration console
	- Dashboard stats, users, roles, storage, audit logs
	- Security policies (password rules, lockouts, sessions)
	- System settings and backup configuration
- Auditing and activity logs
	- Track login activity and document actions
- Background jobs (queue ready)
	- Virus scanning, archive extraction, thumbnail generation

## Example Scenarios

1. Secure document upload and sharing
	 - An engineer uploads a new inspection report to their project folder.
	 - The system checks quota, stores the file, and logs the activity.
	 - The engineer shares the report with a supervisor, with an expiry date enforced by policy.

2. Admin governance and compliance
	 - An admin reviews the dashboard for storage usage and recent actions.
	 - The admin updates security policies and revokes active sessions if needed.
	 - Audit logs provide a trace for every upload, download, and share.

3. Controlled retention and recovery
	 - A file is deleted by mistake and moved to Trash.
	 - The owner restores it, and the restore is logged for traceability.

## Tech Stack

- Backend: Laravel 13, PHP 8.3, Sanctum, Fortify, Spatie Permissions
- Frontend: Vue 3, Vite, Pinia, Vue Router, Tailwind CSS, Axios
- Storage: Local filesystem (configurable via Laravel filesystem)
- Queue: Laravel queue workers (jobs included for scanning and previews)

## Project Structure (High Level)

- API routes and controllers are in `routes/api.php` and `app/Http/Controllers/Api`
- Frontend SPA is in `resources/js` and rendered by `resources/views/app.blade.php`
- File, folder, share, and policy data are in `app/Models`
- Background jobs are in `app/Jobs`

## API Overview

- Auth: `/api/auth/login`, `/api/auth/logout`, `/api/auth/me`
- Drive: `/api/folders/*`, `/api/files/*`, `/api/files/upload`
- Sharing: `/api/shares/*`
- Storage: `/api/storage/usage`, `/api/storage/quotas/*`
- Audit: `/api/audit-logs`
- Admin: `/api/admin/*`

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Or run the built-in setup script:

```bash
composer run setup
```

## Development

```bash
composer run dev
```

This starts the Laravel server, queue worker, log viewer, and Vite dev server.

## Testing

```bash
composer run test
```

## Screenshots

Screenshots are not yet included in the repository. Share the image files you want to include (login, drive explorer, admin dashboard, etc.) and where to store them, and this section will be updated with embedded images.
