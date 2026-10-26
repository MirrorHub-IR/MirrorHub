# Changelog

Notable changes are recorded here. This file tracks the development state and does not imply a tagged release.

## Unreleased

### Added
- Mirror detail pages with separate HTTP status and repository metadata health panels.
- Mirror discovery filters for status, protocol, category and metadata-health state.
- Copyable repository-configuration templates for APT, APK, Pacman and generic RPM use.
- Versioned public API v1 for mirror listing/details, categories, statistics, provider status and recent monitoring history.
- API contribution intake with input validation, a honeypot and per-IP request throttling; contributions remain pending for administrator review.
- OpenAPI 3.1 description, API usage guide, shell examples, Persian/English README files and maintainer/developer documentation.

### Improved
- Backup/restore coverage for repository metadata health and earlier-schema compatibility.
- Regression smoke checks and GitHub Actions quality workflow.
- Deployment and monitoring documentation around secrets, metadata profiles and limitations.

### Known limitations
- Automated checks do not replace integration tests on a real database and web-server configuration.
- Repository-health profiles do not perform complete cryptographic signature verification or validate every package hash.
- No LICENSE file is currently present; maintainers should choose and add the intended license before publishing a reusable release.
