# Changelog

All notable changes to this project will be documented here.

## Unreleased

- Public project documentation and contribution guidance.
- GPL-2.0-or-later license metadata.
- Hardened SMS diagnostic logging to avoid recording API-key-bearing URLs, SMS payloads, recipient phone numbers, and raw provider response bodies.
- Moved new diagnostic log files outside the public WordPress uploads directory and added best-effort cleanup of the legacy public log.

## 1.0.0

- Initial repository release.
- Customer ticket creation, list, and conversation views.
- Administrator ticket management and replies.
- Optional pattern-based SMS notifications.
