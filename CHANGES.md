# Changelog #

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
