# Minimal Support Ticket

[![PHP syntax](https://github.com/Saeedbehbahani/minimal-support-ticke/actions/workflows/php-lint.yml/badge.svg)](https://github.com/Saeedbehbahani/minimal-support-ticke/actions/workflows/php-lint.yml) [![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%20or%20later-blue.svg)](LICENSE)

A lightweight, RTL-friendly support ticket system for WordPress websites. It provides a customer-facing ticket workflow, an administrator inbox, ticket conversations, file attachments, and configurable SMS notifications through a Kavenegar-compatible pattern endpoint.

**Status:** Early public release — contributions and real-world testing are welcome.

## Features

- **Customer ticket portal:** logged-in users can create tickets, view their own tickets, and continue conversations.
- **Separate support views:** list, new-ticket form, and ticket conversation views.
- **Administrator workflow:** review tickets, reply, update status, and delete individual or selected tickets.
- **Ticket statuses:** open, in review, answered, and closed.
- **File attachments:** up to 3 files per upload operation, with a 5 MB per-file limit. The configured allowlist includes JPG/JPEG, PNG, WebP, PDF, ZIP, RAR, and TXT.
- **SMS notifications:** optional pattern-based SMS notifications for new tickets and the first administrator reply, using a Kavenegar-compatible API.
- **Configurable login call-to-action:** configure a login URL, WordPress page, or shortcode and customize the button and helper text.
- **Responsive RTL interface:** Persian-first interface with mobile-oriented ticket list and chat layouts.
- **WordPress integration:** custom database tables, activation-time page setup, shortcodes, capability checks, and WordPress form handlers.

## Requirements

- WordPress
- PHP 7.4 or later (the code uses typed properties)
- A working WordPress database
- Optional: a Kavenegar account or compatible SMS endpoint for pattern notifications

The project has not yet published a compatibility matrix for WordPress or PHP versions. Test it on a staging site before production use.

## Installation

1. Download the repository as a ZIP from GitHub.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP and activate **Minimal Support Ticket**.
4. On activation, the plugin creates or reuses its required frontend pages:
   - `support` — support list
   - `new-ticket` — new ticket form
   - `ticket-chat` — ticket conversation
5. Open the plugin's administration pages to manage tickets and configure optional SMS settings.

Back up your database and files before installing or updating the plugin on a production website.

## Shortcodes

| Shortcode | Purpose |
| --- | --- |
| `[mst_support]` | Combined support experience with list/new views |
| `[mst_support_list]` | Current user's ticket list |
| `[mst_support_new]` | New ticket form |
| `[mst_ticket_chat]` | Ticket conversation view |
| `[mst_ticket_form]` | Legacy-compatible ticket form |
| `[mst_ticket_list]` | Legacy-compatible ticket list |
| `[mst_ticket_view]` | Legacy-compatible ticket conversation |

The ticket conversation shortcode reads the `ticket_id` query parameter. Users must be logged in, and the plugin checks ticket ownership before showing a conversation.

## SMS configuration

SMS is optional. The plugin includes a pattern-based adapter compatible with the Kavenegar verify/lookup request format.

Configure the SMS API key, sender (if required by your provider), optional base URL, administrator phone numbers, and pattern codes in the plugin settings.

The current automatic notification events are:

- A new ticket is created → notify configured administrator numbers.
- An administrator replies → notify the ticket owner, subject to the plugin's one-time notification guard.

**Important:** never commit API keys, phone numbers, customer data, SMS tokens, production logs, database exports, or WordPress configuration files. Use test credentials while developing. Keep debug logging disabled unless needed for troubleshooting.

## Development

The plugin is organized into:

- `minimal-support-ticket.php` — plugin bootstrap and lifecycle hooks
- `includes/core/` — database, helpers, frontend shortcodes/handlers, attachments, and SMS integration
- `includes/admin/` — ticket administration, settings, and SMS test screen
- `assets/css/` — frontend and administration styles

There is currently no automated test suite or CI workflow in this repository. Contributions should include reproducible test steps and should be tested on a staging WordPress installation.

## Security

Please do not report vulnerabilities in public issues. Read [SECURITY.md](SECURITY.md) for responsible disclosure instructions.

Before using this plugin with real customer data, review your site's access controls, backups, file-upload configuration, HTTPS setup, and data-retention obligations. This is an early public release and has not undergone an independent security audit.

## Contributing

Bug reports, documentation improvements, accessibility feedback, and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

Copyright (C) 2026 Bilioneri.

This project is licensed under the **GNU General Public License v2.0 or later**. See [LICENSE](LICENSE) for the complete license text.
