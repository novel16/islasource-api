# IslaSource Seafoods — Laravel API

## Required workflow and current scope
- Use the installed **Ponytail** skill for coding, dependency selection, refactoring, and reviews: root-cause fixes, native Laravel capabilities, minimum necessary complexity.
- Use the applicable installed **Superpowers** skills, including `using-superpowers`, `systematic-debugging`, isolated parallel work when useful, and `verification-before-completion`. Read current skill files from the installed plugins; user instructions take precedence.
- The current authorized task changes this instructions file only. Build the prototype in `../frontend` and `../admin`; do not install Boost, modify API application code, create migrations, or connect live services for this prototype.

## Future backend implementation rules
- The public business name is **IslaSource Seafoods**; Bantayan Island remains its sourcing location. Keep that distinction in future business settings, email templates, quotations, and public metadata.
- Keep this Laravel API independent of the two Next.js apps. Admin authentication uses **Laravel Sanctum personal access bearer tokens**, not session cookies, SPA cookie authentication, or JWT.
- Require `Authorization: Bearer <token>` on protected admin endpoints. Configure token-only guards without session fallback; implement expiry, revocation, rate limits, policies, and resource ownership checks.
- Customers can submit RFQs without an account. Secure private customer quotations/orders using scoped expiring access links or equivalent verification, never reference-number secrecy alone.
- Keep supplier pricing, margins, internal notes, and personal data out of public API resources. Validate requests with Form Requests and authorize writes with policies.
- Laravel owns quotation calculations, quote revision/expiry rules, order transitions, amounts due and payment status. Use integer centavos or fixed-decimal arithmetic; never trust client totals.
- PayMongo QR Ph secrets stay server-side. Verify webhook signatures, amounts and currency; enforce idempotency and handle expiry/retries/late payments. Provider webhooks use provider verification, not admin bearer authentication.
- Products, categories and public pages need unique slugs plus `meta_title`, `meta_description`, OG fields, optional canonical override, publication and indexability. Preserve slug changes with redirects. Images need alt text.
- RFQs, quotations/versions, orders, payment obligations/attempts and fulfillment have separate statuses. Preserve immutable snapshots of accepted specs/prices.
- Prefer controllers, services only for substantial shared business logic, Eloquent, API Resources and queues where needed. Avoid generic repository layers, speculative packages and multi-vendor features.
- When API implementation is authorized, run appropriate Laravel tests/checks and inspect actual results before reporting completion.

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
