# Contributing

Thank you for considering a contribution to Minimal Support Ticket.

## Before opening an issue

- Search existing issues to avoid duplicates.
- Include your WordPress version, PHP version, active theme, relevant plugins, and hosting environment.
- Describe the expected behavior and the behavior you observed.
- Include clear reproduction steps and sanitized error messages.
- Never include API keys, passwords, customer data, phone numbers, database dumps, or private URLs.

## Pull requests

1. Keep changes focused and explain the problem they solve.
2. Follow the existing WordPress/PHP coding style where practical.
3. Escape output and sanitize input using WordPress APIs.
4. Use capability checks and nonce verification for privileged or state-changing actions.
5. Use prepared SQL queries for variable database values.
6. Avoid adding dependencies unless they are necessary and maintained.
7. Update documentation when behavior, settings, or shortcodes change.
8. Test on a staging WordPress installation and include the exact test steps in the pull request.

## Tests

The repository does not currently include an automated test suite or CI pipeline. Do not describe changes as fully tested unless you have actually run and documented the relevant tests.

## License

By submitting a contribution, you agree that it will be distributed under the project's GPL-2.0-or-later license. You must have the right to submit the code and must identify any third-party code or assets you include.
