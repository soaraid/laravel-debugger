# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.10] - 2026-11-24

### Fixed

- Log file sizes and the log index are read fresh from disk, so a cleared file shows as empty right away on PHP 8.2 and in long-running processes such as Octane or queue workers.

## [0.1.9] - 2026-11-24

### Changed

- Clear and Delete for a log file moved into a ⋮ menu on each file in the list, so the toolbar above the entries is only for search and filters.
- The connections example in the config and on the Connections page uses a neutral example API.

## [0.1.8] - 2026-11-24

### Added

- Logs has Clear and Delete for the open log file, with a confirmation, turned off in read-only mode and written to the audit log.

### Fixed

- On short screens, such as a tablet in landscape, list pages no longer squeeze the list down to a row or two: the list keeps at least about seven rows and the page scrolls instead.

## [0.1.7] - 2026-11-24

### Changed

- Backgrounds, borders and hover colours are now neutral grey, with green kept for the accent (active tab, selected file, primary button, success).

### Fixed

- The Mail form now uses the full width next to the mailer settings, like the other pages with a side column.

## [0.1.6] - 2026-11-24

### Changed

- A successful scheduler run is shown and recorded as `success` instead of `ran`; runs recorded as `ran` by earlier versions show as `success` too.

## [0.1.5] - 2026-11-23

### Changed

- Queue, Scheduler and Users fill the screen like Logs: the rows scroll inside the list and the pagination stays in view.
- Users shows recent sessions in a side column next to the user list.

## [0.1.4] - 2026-11-23

### Changed

- Pagination shows under every list that has rows, even when there is only one page.
- Scheduler's recent runs have page numbers and a total like the other lists, 25 per page instead of 50.

## [0.1.3] - 2026-11-23

### Added

- `php artisan debugger:publish` copies the debugger's CSS, JS and fonts to `public/vendor/debugger`, so the web server serves them as static files.

### Fixed

- Pages no longer break on servers whose Nginx answers 404 for `.css`, `.js` and `.woff2` files it can't find on disk; without publishing, or with an older publish, the debugger falls back to its asset route.

## [0.1.2] - 2026-11-23

### Fixed

- Pages that don't scroll no longer have an empty strip down the right side, and the header border reaches the edge.

## [0.1.1] - 2026-11-23

### Changed

- The page background is pure white.

## [0.1.0] - 2026-11-23

First release. A log viewer and the ops pages every Laravel app ends up needing, on one page at `/debugger`. Requires PHP 8.2+ and Laravel 12 or 13.

### Added

- Overview: one list that shows which parts need a look.
- Logs: read and search the files in `storage/logs`, filter by level, and open an entry for its stack trace.
- Queue: failed jobs with their exception, with Retry, Retry all and Delete.
- Scheduler: scheduled tasks with their next and last run, and a history of every run.
- Cache: clear the application, config, route and view caches, or rebuild them.
- Mail: send a test email through the queue or right away.
- Storage: upload a test file and check that disks can be written and read.
- Connections: ping the external APIs the app calls.
- Users: log in as another user and switch back.
- Read-only mode, on by default in production, and an audit log of every action.

[Unreleased]: https://github.com/soaraid/laravel-debugger/compare/v0.1.10...HEAD
[0.1.10]: https://github.com/soaraid/laravel-debugger/compare/v0.1.9...v0.1.10
[0.1.9]: https://github.com/soaraid/laravel-debugger/compare/v0.1.8...v0.1.9
[0.1.8]: https://github.com/soaraid/laravel-debugger/compare/v0.1.7...v0.1.8
[0.1.7]: https://github.com/soaraid/laravel-debugger/compare/v0.1.6...v0.1.7
[0.1.6]: https://github.com/soaraid/laravel-debugger/compare/v0.1.5...v0.1.6
[0.1.5]: https://github.com/soaraid/laravel-debugger/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/soaraid/laravel-debugger/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/soaraid/laravel-debugger/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/soaraid/laravel-debugger/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/soaraid/laravel-debugger/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/soaraid/laravel-debugger/releases/tag/v0.1.0
