# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.10.4] - 2022-11-09

### Fixed
- Handling of non-string `op` values, [#63](https://github.com/swaggest/json-diff/pull/63).

## [3.10.3] - 2022-11-08

### Added
- Option to skip paths, [#39](https://github.com/swaggest/json-diff/pull/39).

## [3.10.2] - 2022-11-08

### Added
- Exceptions improved, [#60](https://github.com/swaggest/json-diff/pull/60).

## [3.10.1] - 2022-10-24

### Added
- Exception type hints improved, [#59](https://github.com/swaggest/json-diff/pull/59).

## [3.10.0] - 2022-10-21

### Added
- More specific exception types added for failed test operation, [#57](https://github.com/swaggest/json-diff/pull/57).


## [3.9.0] - 2022-08-29

### Added
- Specific exception type `PatchTestOperationFailedException` for failed test operation.

## [3.8.3] - 2021-09-25

### Fixed
- Redundant operations in array patches that strip first element.
- XOR hash collision for properties having equal parts.

### Added
- Rearrange indexing by non-scalar properties that contain object, using XOR hash.

## [3.8.2] - 2021-09-17

### Fixed
- PHP 8.1 Deprecation notices with missing returns.

## [3.8.1] - 2020-09-25

### Fixed
- Rearrangement of equal array items is corrupting data by redundant replaces.

## [3.8.0] - 2020-09-25

### Added
- Rearrangement of equal items for non-homogeneous arrays with `JsonDiff::REARRANGE_ARRAYS` option.

## [3.7.5] - 2020-05-26

### Fixed
- Accidental array to associative array conversion ([#31](https://github.com/swaggest/json-diff/issues/31)).

## [3.7.4] - 2020-01-26

### Fixed
- PHP version check for empty property name support.

## [3.7.3] - 2020-01-24

### Fixed
- Merge patch was not replacing partially different arrays.

## [3.7.2] - 2019-10-23

### Added
- Change log.

### Fixed
- Few irrelevant files not mentioned in `.gitattributes`.

## [3.7.1] - 2019-09-26

### Added
- Benchmarks to CI.

### Fixed
- Unstable array rearrange order.

## [3.7.0] - 2019-04-25

### Added
- `getModifiedDiff()` and `COLLECT_MODIFIED_DIFF` option to return paths with original and new values.

## [3.6.0] - 2019-04-24

### Added
- Compatibility option to `TOLERATE_ASSOCIATIVE_ARRAYS` that mimic JSON objects.

[3.10.4]: https://github.com/swaggest/json-diff/compare/v3.10.3...v3.10.4
[3.10.3]: https://github.com/swaggest/json-diff/compare/v3.10.2...v3.10.3
[3.10.2]: https://github.com/swaggest/json-diff/compare/v3.10.1...v3.10.2
[3.10.1]: https://github.com/swaggest/json-diff/compare/v3.10.0...v3.10.1
[3.10.0]: https://github.com/swaggest/json-diff/compare/v3.9.0...v3.10.0
[3.9.0]: https://github.com/swaggest/json-diff/compare/v3.8.3...v3.9.0
[3.8.3]: https://github.com/swaggest/json-diff/compare/v3.8.2...v3.8.3
[3.8.2]: https://github.com/swaggest/json-diff/compare/v3.8.1...v3.8.2
[3.8.1]: https://github.com/swaggest/json-diff/compare/v3.8.0...v3.8.1
[3.8.0]: https://github.com/swaggest/json-diff/compare/v3.7.5...v3.8.0
[3.7.5]: https://github.com/swaggest/json-diff/compare/v3.7.4...v3.7.5
[3.7.4]: https://github.com/swaggest/json-diff/compare/v3.7.3...v3.7.4
[3.7.3]: https://github.com/swaggest/json-diff/compare/v3.7.2...v3.7.3
[3.7.2]: https://github.com/swaggest/json-diff/compare/v3.7.1...v3.7.2
[3.7.1]: https://github.com/swaggest/json-diff/compare/v3.7.0...v3.7.1
[3.7.0]: https://github.com/swaggest/json-diff/compare/v3.6.0...v3.7.0
[3.6.0]: https://github.com/swaggest/json-diff/compare/v3.5.1...v3.6.0
