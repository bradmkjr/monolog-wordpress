# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
- Monolog v2 compatibility

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


[Unreleased]: https://github.com/bradmkjr/monolog-wordpress/compare/1.6.0...HEAD
[1.6.4]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.4
[1.6.3]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.3
[1.6.2]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.2
[1.6.1]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.1
[1.6.0]: https://github.com/bradmkjr/monolog-wordpress/tree/1.6.0

