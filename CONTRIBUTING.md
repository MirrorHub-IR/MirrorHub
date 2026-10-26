# Contributing to MirrorHub

Thanks for helping improve MirrorHub. Contributions should improve reliability, security, maintainability, or usability without weakening repository integrity.

## Before you start

- Search existing issues and discussions before opening a duplicate.
- Keep changes focused and describe the user problem they solve.
- Do not include database credentials, bot tokens, real contact details, production dumps, or logs containing private data.
- Do not commit config.local.php, backups, or uploaded/runtime content.
- Check whether a change affects the legacy API before modifying the versioned API.

## Development workflow

1. Fork the repository and create a focused branch in your fork.
2. Make the smallest coherent change.
3. Run the applicable checks locally:

~~~sh
php -l path/to/changed-file.php
php tests/security-smoke.php
node --check assets/js/changed-file.js
~~~

4. Open a pull request describing the problem, solution, migration/deployment impact, and test evidence.

The GitHub Actions workflow performs PHP lint, smoke/regression checks and JavaScript syntax checks. These are useful baseline checks, not full browser or live-database integration tests.

## PHP and database practices

- Use PDO prepared statements for values from users or requests.
- Keep validation, authorization, and output escaping on the server; client-side checks are not security controls.
- Preserve CSRF protections for browser forms that change state.
- Keep public API reads separate from privileged administration operations.
- Any new public write endpoint must consider input size, rate limits, spam prevention, moderation, and generic error responses.
- If database tables or columns change, update the installer, schema self-healing/migration path when applicable, backup/restore coverage, docs, and smoke tests.
- Never silently disable TLS validation or package-signature checks to make tests pass.
- For repository-health features, clearly state what is actually measured; do not label metadata freshness as cryptographic verification.

## Documentation and compatibility

- Update README and relevant docs when behavior, configuration, API, or deployment changes.
- Keep examples executable or clearly label placeholders.
- Preserve the legacy API until a documented migration path is available.
- Avoid undocumented breaking changes to API response fields.

## Reporting security issues

Do not publish exploitable vulnerability details in a public issue. Follow [SECURITY.md](SECURITY.md).
