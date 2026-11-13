# Changelog

Notable changes are recorded here. Entries describe repository changes; a tagged GitHub Release is published separately.

## Unreleased

### Added
- Expanded English and Persian project documentation, including a full English installation guide and bilingual first-release notes.
- Semgrep static-analysis workflow for PHP.
- Weekly Dependabot updates for GitHub Actions.
- Pull request template, issue contact configuration, community code of conduct, and support policy.
- Manual draft-release workflow for preparing the initial v0.1.0 GitHub Release.

### Improved
- Replaced outdated CI badge URLs that pointed to the former repository owner.
- Documented the actual feature set, architecture, installation requirements, API contract, monitoring semantics, backup behavior, security controls, and current limitations.
- Clarified that the project license is present and identified as GPL-3.0-only; the bundled Vazirmatn font has its own SIL OFL 1.1 notice.

## Release preparation: v0.1.0

The proposed initial public-preview notes are maintained in [docs/releases/v0.1.0.md](docs/releases/v0.1.0.md). The GitHub Release and tag are not created by this changelog update. Use the manual **Draft Release** workflow to create a draft release, review it, and publish it from GitHub when ready.

### Current known limitations
- Automated checks do not replace integration tests against a real MySQL/MariaDB instance, browser-based end-to-end tests, or a tested production restore.
- Repository-health profiles do not perform complete cryptographic signature verification or validate every package hash.
- Browser-side Ping measures HTTP round-trip time, not ICMP reachability.
- Provider monitoring covers active HTTP/HTTPS mirrors attached to provider categories.
- The project remains pre-1.0; maintainers should validate staging deployments and collect real operator feedback before treating it as a mature production dependency.
