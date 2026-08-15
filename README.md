# Expense System

Production-focused finance management system for a social media advertising agency.  
Built with Laravel, Filament, Livewire, MySQL, and PWA support.

## Author

- Author: Moshiur Rahman
- Email: rmedha037@gmail.com
- GitHub: `mmrtonmoybd`
- Repository: `https://github.com/mmrtonmoybd/expense-system` (private)

## Project Overview

This application is designed for agencies that buy USD using BDT, maintain USD inventory using moving weighted average costing, and sell or use that USD for customer advertising on platforms like Facebook / Meta, TikTok, and Google.

The system is built for operational finance accuracy, auditability, and day-to-day usability on desktop and mobile devices.

## Core Business Coverage

- USD opening balance
- USD purchases
- USD inventory tracking
- Moving weighted average cost calculation
- Multi-platform customer sales
- Sale approval workflow
- Invoice generation
- PDF invoice download
- Print-friendly invoice view
- Inventory ledger
- Inventory adjustment
- Sale reversal
- Expense management
- Employee management
- Payroll and salary payment tracking
- Gross profit and net profit reporting
- Dashboard with KPI cards and charts
- Customer and platform performance reports
- XLSX, CSV, PDF, and print exports
- Audit logs
- Role and permission management
- PWA installation support
- Dynamic branding and settings

## Financial Rules

- Primary reporting currency: BDT
- Inventory currency: USD
- Financial values use `DECIMAL`, never `FLOAT`
- USD inventory costing uses moving weighted average
- Historical posted sale cost and selling rate snapshots never change
- Pending sales do not affect official inventory or profit
- Posted sales update inventory, revenue, and profit
- Overselling is blocked with backend validation and transaction locking

## Technology Stack

- PHP 8.4
- Laravel 13
- Filament 5
- Livewire 4
- MySQL
- Tailwind CSS 4
- Vite
- DomPDF
- Laravel Excel

## Main Modules

- Dashboard
- Sales
- Pending Approvals
- Invoices
- Customers
- Platforms
- USD Purchases
- Inventory Transactions
- Inventory Adjustment
- Expenses
- Expense Categories
- Employees
- Payrolls
- Reports
- Profit Predictor
- Users
- Roles & Permissions
- Audit Logs
- Settings

## Setup

```bash
composer install
php artisan migrate
php artisan db:seed --class=AuthorizationSeeder
php artisan storage:link
npm install
npm run build
```

## Local Development

```bash
composer run dev
```

This starts:

- Laravel development server
- Queue listener
- Vite dev server

## Demo Data

The demo dataset is deterministic and guarded. It is intended only for `local`, `testing`, or `demo` environments.

```bash
php artisan db:seed --class=DemoDataSeeder
```

Demo accounts use password `password`:

| Role        | Email                |
| ----------- | -------------------- |
| Super Admin | `super@demo.test`    |
| Owner       | `owner@demo.test`    |
| Admin       | `admin@demo.test`    |
| Employee    | `employee@demo.test` |
| Normal User | `user@demo.test`     |

Do not run the demo seeder in production.

## Important Commands

```bash
php artisan test --compact
vendor/bin/pint --format agent
composer lint
composer fix
```

## Configuration Notes

- Configure business, invoice, approval, and PWA settings from the admin panel after installation.
- Default timezone is `Asia/Dhaka`.
- Storage link is required for branding files, invoice assets, and uploads.
- Build assets again after frontend or theme changes:

```bash
npm run build
```

## PWA

The admin panel is installable as a Progressive Web App and includes:

- Web manifest
- Service worker
- Dynamic app icon support
- Offline indicator
- Safe non-queued financial posting behavior

## Usage Notice

This project is private and proprietary.

Unauthorized use, copying, distribution, modification, resale, deployment, or access without explicit permission from the author is prohibited.

See [LICENSE.md](LICENSE.md) for the full proprietary notice.
