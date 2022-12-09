# Changelog #

## v1.1.5 (2022-12-09) ##

* Add support for PHP 8.2

## v1.1.4 (2022-01-02) ##

* Improve Github action workflow
* Fix additional PHP 8.1 deprecations
* Fix issue with `JsonStream::getContents()` no returning buffer contents

## v1.1.3 (2021-12-27) ##

* Add support for PHP 8.1

## v1.1.2 (2020-11-29) ##

 * HHVM support has been dropped due to HHVM's renewed focus on Hack
 * CI build has been migrated to github actions
 * The CI build now tests for PHP version from 5.6 to 8.0

## v1.1.1 (2017-07-09) ##

 * Return `UNKNOWN_ERROR` as error code if valid error constant is not found
 * Minor improvements to the travis build process
 * Slightly improve the bundled autoloader

## v1.1.0 (2017-06-28) ##

 * For `json_encode()` compatibility, all objects are encoded as JSON objects
   unless they implement `Traversable` and either return `0` as the first key or
   return no values at all.
 * Testing for associative arrays is now more memory efficient and fails faster.
 * JSON encoding errors now contain the error constant.
 * The visibility for `StreamJsonEncoder::write()` and `BufferJsonEncoder::write()`
   has been changed to protected (as was originally intended).
 * A new protected method `AbstractJsonEncoder::getValueStack()` has been added
   that returns the current unresolved value stack (for special write method
   implementations). 
 * An overridable protected method `AbstractJsonEncoder::resolveValue()` has
   been added which is used to resolve objects (i.e. resolving the value of
   closures and objects implementing `JsonSerializable`).

## v1.0.0 (2017-02-26) ##

  * Initial release
