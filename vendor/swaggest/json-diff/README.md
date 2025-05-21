# JSON diff/rearrange/patch/pointer library for PHP

A PHP implementation for finding unordered diff between two `JSON` documents.

[![Build Status](https://travis-ci.org/swaggest/json-diff.svg?branch=master)](https://travis-ci.org/swaggest/json-diff)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/swaggest/json-diff/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/swaggest/json-diff/?branch=master)
[![Code Climate](https://codeclimate.com/github/swaggest/json-diff/badges/gpa.svg)](https://codeclimate.com/github/swaggest/json-diff)
[![Code Coverage](https://scrutinizer-ci.com/g/swaggest/json-diff/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/swaggest/json-diff/code-structure/master/code-coverage)
[![time tracker](https://wakatime.com/badge/github/swaggest/json-diff.svg)](https://wakatime.com/badge/github/swaggest/json-diff)


## Purpose

 * To simplify changes review between two `JSON` files you can use a standard `diff` tool on rearranged pretty-printed `JSON`.
 * To detect breaking changes by analyzing removals and changes from original `JSON`.
 * To keep original order of object sets (for example `swagger.json` [parameters](https://swagger.io/docs/specification/describing-parameters/) list).
 * To [make](#getpatch) and [apply](#jsonpatch) JSON Patches, specified in [RFC 6902](https://datatracker.ietf.org/doc/html/rfc6902) from the IETF.
 * To [make](#getmergepatch) and [apply](#jsonmergepatch) JSON Merge Patches, specified in [RFC 7386](https://datatracker.ietf.org/doc/html/rfc7386) from the IETF.
 * To retrieve and modify data by [JSON Pointer](https://datatracker.ietf.org/doc/html/rfc6901).
 * To recursively replace by JSON value.

## Installation

### Library

```bash
git clone https://github.com/swaggest/json-diff.git
```

### Composer

[Install PHP Composer](https://getcomposer.org/doc/00-intro.md)

```bash
composer require swaggest/json-diff
```

## Library usage

### `JsonDiff`

Create `JsonDiff` object from two values (`original` and `new`).

```php
$r = new JsonDiff(json_decode($originalJson), json_decode($newJson));
```

On construction `JsonDiff` will build `rearranged` value of `new` recursively keeping `original` keys order where possible. 
Keys that are missing in `original` will be appended to the end of `rearranged` value in same order they had in `new` value.

If two values are arrays of objects, `JsonDiff` will try to find a common unique field in those objects and use it as criteria for rearranging. 
You can enable this behaviour with `JsonDiff::REARRANGE_ARRAYS` option:
```php
$r = new JsonDiff(
    json_decode($originalJson), 
    json_decode($newJson),
    JsonDiff::REARRANGE_ARRAYS
);
```

Available options:
 * `REARRANGE_ARRAYS` is an option to enable [arrays rearrangement](#arrays-rearrangement) to minimize the difference.
 * `STOP_ON_DIFF` is an option to improve performance by stopping comparison when a difference is found.
 * `JSON_URI_FRAGMENT_ID` is an option to use URI Fragment Identifier Representation (example: "#/c%25d"). If not set default JSON String Representation (example: "/c%d").
 * `SKIP_JSON_PATCH` is an option to improve performance by not building JsonPatch for this diff.
 * `SKIP_JSON_MERGE_PATCH` is an option to improve performance by not building JSON Merge Patch value for this diff.
 * `TOLERATE_ASSOCIATIVE_ARRAYS` is an option to allow associative arrays to mimic JSON objects (not recommended).
 * `COLLECT_MODIFIED_DIFF` is an option to enable [getModifiedDiff](#getmodifieddiff).

Options can be combined, e.g. `JsonDiff::REARRANGE_ARRAYS + JsonDiff::STOP_ON_DIFF`.

#### `getDiffCnt`
Returns total number of differences

#### `getPatch`
Returns [`JsonPatch`](#jsonpatch) of difference

#### `getMergePatch`
Returns [JSON Merge Patch](https://datatracker.ietf.org/doc/html/rfc7386) value of difference

#### `getRearranged`
Returns new value, rearranged with original order.

#### `getRemoved`
Returns removals as partial value of original.

#### `getRemovedPaths`
Returns list of `JSON` paths that were removed from original.

#### `getRemovedCnt`
Returns number of removals.

#### `getAdded`
Returns additions as partial value of new.

#### `getAddedPaths`
Returns list of `JSON` paths that were added to new.

#### `getAddedCnt`
Returns number of additions.

#### `getModifiedOriginal`
Returns modifications as partial value of original.

#### `getModifiedNew`
Returns modifications as partial value of new.

#### `getModifiedDiff`
Returns list of [`ModifiedPathDiff`](src/ModifiedPathDiff.php) containing paths with original and new values.

Not collected by default, requires `JsonDiff::COLLECT_MODIFIED_DIFF` option.

#### `getModifiedPaths`
Returns list of `JSON` paths that were modified from original to new.

#### `getModifiedCnt`
Returns number of modifications.

### `JsonPatch`

#### `import`
Creates `JsonPatch` instance from `JSON`-decoded data.

#### `export`
Creates patch data from `JsonPatch` object.

#### `op`
Adds operation to `JsonPatch`.

#### `apply`
Applies patch to `JSON`-decoded data.

#### `setFlags`
Alters default behavior.

Available flags:

* `JsonPatch::STRICT_MODE` Disallow converting empty array to object for key creation.
* `JsonPatch::TOLERATE_ASSOCIATIVE_ARRAYS` Allow associative arrays to mimic JSON objects (not recommended).

### `JsonPointer`

#### `escapeSegment`
Escapes path segment.

#### `splitPath`
Creates array of unescaped segments from `JSON Pointer` string.

#### `buildPath`
Creates `JSON Pointer` string from array of unescaped segments.

#### `add`
Adds value to data at path specified by segments.

#### `get`
Gets value from data at path specified by segments.

#### `getByPointer`
Gets value from data at path specified `JSON Pointer` string.

#### `remove`
Removes value from data at path specified by segments.

### `JsonMergePatch`

#### `apply`
Applies patch to `JSON`-decoded data.

### `JsonValueReplace`

#### `process`
Recursively replaces all nodes equal to `search` value with `replace` value.

## Example

```php
$originalJson = <<<'JSON'
{
    "key1": [4, 1, 2, 3],
    "key2": 2,
    "key3": {
        "sub0": 0,
        "sub1": "a",
        "sub2": "b"
    },
    "key4": [
        {"a":1, "b":true, "subs": [{"s":1}, {"s":2}, {"s":3}]}, {"a":2, "b":false}, {"a":3}
    ]
}
JSON;

$newJson = <<<'JSON'
{
    "key5": "wat",
    "key1": [5, 1, 2, 3],
    "key4": [
        {"c":false, "a":2}, {"a":1, "b":true, "subs": [{"s":3, "add": true}, {"s":2}, {"s":1}]}, {"c":1, "a":3}
    ],
    "key3": {
        "sub3": 0,
        "sub2": false,
        "sub1": "c"
    }
}
JSON;

$patchJson = <<<'JSON'
[
    {"value":4,"op":"test","path":"/key1/0"},
    {"value":5,"op":"replace","path":"/key1/0"},
    
    {"op":"remove","path":"/key2"},
    
    {"op":"remove","path":"/key3/sub0"},
    
    {"value":"a","op":"test","path":"/key3/sub1"},
    {"value":"c","op":"replace","path":"/key3/sub1"},
    
    {"value":"b","op":"test","path":"/key3/sub2"},
    {"value":false,"op":"replace","path":"/key3/sub2"},
    
    {"value":0,"op":"add","path":"/key3/sub3"},

    {"value":true,"op":"add","path":"/key4/0/subs/2/add"},
    
    {"op":"remove","path":"/key4/1/b"},
    
    {"value":false,"op":"add","path":"/key4/1/c"},
    
    {"value":1,"op":"add","path":"/key4/2/c"},
    
    {"value":"wat","op":"add","path":"/key5"}
]
JSON;

$diff = new JsonDiff(json_decode($originalJson), json_decode($newJson), JsonDiff::REARRANGE_ARRAYS);
$this->assertEquals(json_decode($patchJson), $diff->getPatch()->jsonSerialize());

$original = json_decode($originalJson);
$patch = JsonPatch::import(json_decode($patchJson));
$patch->apply($original);
$this->assertEquals($diff->getRearranged(), $original);
```

## PHP Classes as JSON objects

Due to magical methods and other restrictions PHP classes can not be reliably mapped to/from JSON objects.
There is support for objects of PHP classes in `JsonPointer` with limitations:
* `null` is equal to non-existent

## Arrays Rearrangement

When `JsonDiff::REARRANGE_ARRAYS` option is enabled, array items are ordered to match the original array.

If arrays contain homogenous objects, and those objects have a common property with unique values, array is
ordered to match placement of items with same value of such property in the original array.

Example:
original
```json
[{"name": "Alex", "height": 180},{"name": "Joe", "height": 179},{"name": "Jane", "height": 165}]
```
vs new
```json
[{"name": "Joe", "height": 179},{"name": "Jane", "height": 168},{"name": "Alex", "height": 180}]
```
would produce a patch:
```json
[{"value":165,"op":"test","path":"/2/height"},{"value":168,"op":"replace","path":"/2/height"}]
```

If qualifying indexing property is not found, rearrangement is done based on items equality.

Example:
original
```json
{"data": [{"A": 1, "C": [1, 2, 3]}, {"B": 2}]}
```
vs new
```json
{"data": [{"B": 2}, {"A": 1, "C": [3, 2, 1]}]}
```
would produce no difference.

## CLI tool

Moved to [`swaggest/json-cli`](https://github.com/swaggest/json-cli)
