<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Support backed enums (`enum Foo: string`/`enum Foo: int`) as OpenAPI types, resolved from their native parameter type hint
- Support the built-in `SortDirection` enum from PHP 8.6 (unbacked, so its cases are mapped to the `'ASC'`/`'DESC'` strings)
- Ignore controller method parameters typed as an injected service (e.g. `\OCP\IUser`) instead of requiring docs for them and listing them in the generated OpenAPI file
- Support the `RequestFile` attribute (`requestKey`, `fieldName`, `multiple`, `required`, `indirect`) to describe uploaded files that can not be detected from `getUploadedFile()` calls, e.g. required files, several files (`files[]`) or files read outside of the controller method

### Fixed
- Clean whitespace in description fields

## [1.0.0] - 2024-08-20

### Added

- Initial stable release
