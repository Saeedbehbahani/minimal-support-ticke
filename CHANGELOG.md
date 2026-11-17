# Changelog

All notable changes to this project will be documented here.

## 1.0.1 — 2026-11-17

- Added project documentation, contribution guidelines, and responsible security disclosure instructions.
- Added the GPL-2.0-or-later license.
- Hardened SMS diagnostics: removed API-key-bearing URLs, SMS payloads, recipient phone numbers, and raw provider response bodies from log output.
- Moved new SMS diagnostic logs outside the public WordPress uploads directory and added best-effort cleanup of the legacy public log.
- Added PHP syntax-check automation for PHP 7.4 through 8.3.
- Added issue templates and ignored common local secrets, logs, and database exports.

## 1.0.0

- Initial repository release.
- Customer ticket creation, list, and conversation views.
- Administrator ticket management and replies.
- Optional pattern-based SMS notifications.
