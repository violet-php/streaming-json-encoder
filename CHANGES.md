# Changelog #

## v1.1.0 (2017-06-28) ##

 * Empty objects and objects that don't implement `Traversable` are now always
   encoded as JSON objects (for `json_encode()` compatibility).
 * Associative array testing now returns faster and more memory efficiently
 * JSON encoding errors now contain the error constant
 * The visibility for `write()` method in `StreamJsonEncoder` and
   `BufferJsonEncoder` has been changed to protected (as originally intended)
 * A protected method `getValueStack()` has been added to `AbstractJsonEncoder`
   that returns current unresolved value stack (for special write method
   implementations). 
 * An overridable protected method `resolveValue()` has been added to
   `AbstractJsonEncoder` which is used to resolve closures and objects
   implementing `JsonSerializable`.

## v1.0.0 (2017-02-26) ##

  * Initial release
