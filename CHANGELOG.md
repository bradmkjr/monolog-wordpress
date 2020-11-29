# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.7.1] - 2020-11-29
### Improved
- The logging table size limiter caused slow logging once the limit was reached, due to the overhead of truncating the table after every row written. This is now fixed by doing the truncations in batches.

### Changed
- The `set_max_table_rows()` method is deprecated, use `conf_table_size_limiter()` instead.
- The `maybe_truncate()` method was not intended to be used outside of the class, so it is not a public method anymore.

## [1.7.0] - 2020-10-15
### Added
- Feature to limit the maximum number of rows to keep in the log table. Use `set_max_table_rows()` method on the handler instance to configure the limit.

### Improved
- README.md now has a section about v2 and v1 differences.

## [1.6.5] - 2020-04-11
### Fixed
- Limitations of WordPressHandler regarding formatters, whereas formatted data was only respected in the 'extra' part of the records, but not for 'message' or 'context' (https://github.com/bradmkjr/monolog-wordpress/issues/11). **Note:** the time column still does not follow the formatted datetime to keep compatibility with existing deployments.

## [1.6.4] - 2020-04-11
### Fixed
- Previous Fix in v.1.6.3 broke compatibility with Monolog v1 and PHP versions before 7.1. Monolog v2 compatibility will be reintroduced in v2 of this package.

### Improved
- PHP v5.3 compatibility made explicit in composer.json
- Added Wordpress as a development requirement
- WordPressHandler::$wpdb type declaration was incorrect in PHPDoc, set to \wpdb to point to the correct class in Wordpress

### Added
- CHANGELOG.md

## [1.6.3] - 2019-11-12
### Fixed
- Added ` quotes around additional and extra fields. This fixes issue when an extra/additional field is tried to be defined using a reserved SQL keyword as name. Example: "procedure" will cause "WordPress database error" written directly to the admin UI, and indeed failure to create logging table.
- Fixed Fatal error: Declaration of WordPressHandler\WordPressHandler->write(array $record) must be compatible with that of Monolog\Handler\AbstractProcessingHandler->write(array $record): void

### Reverted
- It was a mistake taking out extra fields, added back in

## [1.6.2] - 2017-05-26
### Reverted
- It was a mistake taking out extra fields, added back in

## [1.6.1] - 2017-05-26 [YANKED]
### Removed
- $extraFields was duplication of $additionalFields, so have removed it

## [1.6.0] - 2017-05-26
No changelog had been maintained up to this point. Refer to the GIT commit history for more details.


[Unreleased]: https://github.com/bradmkjr/monolog-wordpress/compare/1.7.0...v1
[1.7.0]: https://github.com/bradmkjr/monolog-wordpress/tree/1.7.0
[1.6.5]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.5
[1.6.4]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.4
[1.6.3]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.3
[1.6.2]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.2
[1.6.1]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.1
[1.6.0]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.0

