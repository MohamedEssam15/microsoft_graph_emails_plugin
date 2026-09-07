# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-07

### Added

- Initial release.
- `GraphTransport` — Symfony Mailer transport for sending via Microsoft Graph `sendMail`.
- `MicrosoftGraphTokenService` — cached OAuth2 client-credentials token acquisition.
- `graph-mail:test` artisan command for end-to-end credential and transport verification.
- Per-message sender override via `->from()`, falling back to `MS_GRAPH_SENDER`.
- Attachment support via `fileAttachment` payloads.
- CC, BCC, and Reply-To support.
- Full Pest test suite using `Http::fake()`.
- GitHub Actions CI across PHP 8.1–8.3 and Laravel 10–12.
