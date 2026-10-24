# Security Policy

## Supported code

Security reports are most useful when tested against the current default branch. MirrorHub is evolving, and the current automated checks do not replace a full security assessment or a production hardening review.

## Reporting a vulnerability

Please do not publish credentials, private user data, exploitable URLs, or step-by-step exploit details in a public issue.

Use GitHub's private vulnerability reporting / Security Advisory feature for this repository if it is enabled. If private reporting is unavailable, contact the repository maintainer privately through GitHub before publishing technical details. Do not include live secrets in the initial report.

Please include, where safe:
- affected commit or file and route;
- impact and prerequisites;
- minimal reproduction steps using a test environment;
- whether the issue is reproducible on the current default branch;
- a suggested mitigation, if known.

## Deployment notes

- Keep database credentials and bot/Cron secrets out of Git.
- Use HTTPS and keep TLS validation enabled.
- Delete or block install.php after initial setup.
- Protect backups and restrict web-server access to local configuration.
- Use real server Cron in production and keep the Cron key private.
- Public API contribution requests are untrusted input; a suggestion must not become a public mirror without administrator review.
- Repository metadata freshness is not equivalent to GPG signature verification or validating every package hash.
