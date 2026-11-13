## Summary

Describe the problem and the change. Keep the summary focused on user-visible or maintainer-visible outcomes.

## Related issue

Link related issue(s), or write "Not applicable".

## Change type

- [ ] Bug fix
- [ ] Feature
- [ ] Security improvement
- [ ] Documentation
- [ ] Refactor / maintenance
- [ ] Database or migration change

## Implementation notes

Describe important design decisions, compatibility considerations, and any behavior that operators or API consumers should know about.

## Validation

- [ ] php tests/security-smoke.php passed.
- [ ] PHP syntax checks passed for changed PHP files.
- [ ] JavaScript syntax checks passed for changed JS files.
- [ ] I added or updated tests for changed behavior, or explained why this is not applicable.
- [ ] I did not claim a live database or browser test that I did not perform.

Commands and results:

~~~text
Paste sanitized command output here.
~~~

## Database, backup, and deployment impact

- [ ] No database change.
- [ ] Schema/install/migration path updated.
- [ ] Backup and restore coverage reviewed.
- [ ] Deployment/configuration documentation updated.
- [ ] Legacy API and existing installations considered.

Explain any operator action required:

## Security and privacy

- [ ] User input is validated server-side.
- [ ] Database values use prepared statements where applicable.
- [ ] State-changing browser forms retain CSRF protection.
- [ ] Output is escaped for its context.
- [ ] No credentials, tokens, production dumps, or private logs are included.
- [ ] TLS validation and package-signature checks are not weakened.

## Documentation and UX

- [ ] README or relevant documentation updated.
- [ ] Screenshots/GIFs attached for UI changes where useful (with private data removed).
- [ ] Accessibility, responsive layout, and Persian RTL behavior considered.
