# Security Policy

## Supported versions

Security fixes are currently focused on the latest code in the default branch. No formal long-term support policy has been established yet.

## Reporting a vulnerability

Please do not disclose suspected vulnerabilities in public GitHub issues.

Use GitHub's **Report a vulnerability** feature on this repository, if available, to submit a private security advisory. Include:

- A concise description of the issue and its potential impact.
- The affected file, feature, and version or commit.
- Reproduction steps or a proof of concept that does not expose real user data.
- Any suggested mitigation.

Do not include real API keys, passwords, customer records, phone numbers, or production logs in the report.

## Response

This is a maintainer-led project. A response timeline has not yet been established. Please allow reasonable time for triage and avoid public disclosure until a fix or coordinated disclosure plan is available.

## Deployment precautions

- Keep WordPress, PHP, themes, and plugins updated.
- Use HTTPS and least-privilege WordPress accounts.
- Back up the database and uploads directory.
- Keep SMS debug mode disabled unless troubleshooting.
- Use test API credentials in development and rotate any credential that may have been exposed.
- Test file uploads and ticket access rules on a staging site before production use.
