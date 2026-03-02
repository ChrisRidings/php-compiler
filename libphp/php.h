#ifndef LIBPHP_PHP_H
#define LIBPHP_PHP_H

// Include necessary C standard library headers
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

// Ensure C linkage for all functions
#ifdef __cplusplus
extern "C" {
#endif

// Zval type tags
typedef enum {
    PHP_TYPE_NULL,
    PHP_TYPE_BOOL,
    PHP_TYPE_INT,
    PHP_TYPE_STRING,
    PHP_TYPE_ARRAY
} php_type_t;

// Forward declaration for array
typedef struct php_array php_array;

// Zval struct representing a dynamically typed value
typedef struct {
    php_type_t type;
    union {
        int bool_val;           // for PHP_TYPE_BOOL
        int int_val;            // for PHP_TYPE_INT
        char* str_val;          // for PHP_TYPE_STRING (null-terminated)
        long long ptr_val;      // for PHP_TYPE_ARRAY (stores array pointer)
    } value;
} zval;

/**
 * Creates a null zval.
 */
void php_zval_null(zval* z);

/**
 * Creates a boolean zval.
 */
void php_zval_bool(zval* z, int bool_val);

/**
 * Creates an integer zval.
 */
void php_zval_int(zval* z, int int_val);

/**
 * Creates a string zval.
 */
void php_zval_string(zval* z, const char* str);

/**
 * Converts a zval to string.
 *
 * @param z The zval to convert
 * @return Pointer to static string buffer (thread-safe, but overwrites on each call)
 */
char* php_zval_to_string(const zval* z);

/**
 * Converts a zval to integer.
 *
 * @param z The zval to convert
 * @return Integer value
 */
int php_zval_to_int(const zval* z);

/**
 * Prints a zval to standard output.
 */
void php_echo_zval(const zval* z);

/**
 * Prints a string to standard output.
 *
 * @param str The string to print (null-terminated)
 */
void php_echo(const char* str);

/**
 * Converts an integer to a string.
 *
 * @param num The integer to convert
 * @return Pointer to static string buffer (thread-safe, but overwrites on each call)
 */
char* php_itoa(int num);

/**
 * Concatenates two strings.
 *
 * @param str1 First string
 * @param str2 Second string
 * @return Pointer to static string buffer containing the concatenated result
 */
char* php_concat_strings(const char* str1, const char* str2);

/**
 * Creates an array zval with initial capacity.
 *
 * @param z The zval to initialize as an array
 * @param initial_capacity Initial capacity for the array
 */
void php_array_create(zval* z, int initial_capacity);

/**
 * Appends an element to an array.
 *
 * @param arr The array zval
 * @param elem The element to append
 */
void php_array_append(zval* arr, zval* elem);

/**
 * Gets an element from an array by index.
 *
 * @param result The zval to store the result in
 * @param arr The array zval
 * @param index The index zval (should be an integer)
 */
void php_array_get(zval* result, zval* arr, zval* index);

/**
 * Sets a key-value pair in an associative array.
 *
 * @param arr The array zval
 * @param key The key string
 * @param value The value to set
 */
void php_array_set(zval* arr, const char* key, zval* value);

/**
 * Sets a value at a numeric index in an array.
 *
 * @param arr The array zval
 * @param index The numeric index
 * @param value The value to set
 */
void php_array_set_by_index(zval* arr, int index, zval* value);

/**
 * Gets the size of an array.
 *
 * @param arr The array zval
 * @return The number of elements in the array
 */
int php_array_size(zval* arr);

/**
 * Gets the key at a given index in an array.
 * Returns the string key if present, otherwise returns NULL.
 * For numeric indices, returns NULL (caller should use the index as key).
 *
 * @param arr The array zval
 * @param index The index
 * @return The key string or NULL
 */
char* php_array_get_key(zval* arr, int index);

/**
 * Returns all the values of an array.
 * For associative arrays, this will reindex numerically.
 *
 * @param arr The array zval
 * @param result The result zval (will be an array)
 */
void php_array_values(zval* arr, zval* result);

/**
 * Opens a directory for reading.
 *
 * @param path The directory path
 * @param result The result zval (will contain directory handle or false)
 */
void php_opendir(zval* path, zval* result);

/**
 * Reads the next entry from a directory.
 *
 * @param handle The directory handle
 * @param result The result zval (will contain filename or false)
 */
void php_readdir(zval* handle, zval* result);

/**
 * Closes a directory handle.
 *
 * @param handle The directory handle
 * @param result The result zval (will be null)
 */
void php_closedir(zval* handle, zval* result);

/**
 * Performs a regular expression match.
 *
 * @param pattern The regex pattern
 * @param subject The string to match against
 * @param result The result zval (1 if match found, 0 otherwise)
 */
void php_preg_match(zval* pattern, zval* subject, zval* result);

/**
 * Sorts an array using natural order algorithm.
 *
 * @param arr The array to sort
 * @param result The result zval (will contain sorted array)
 */
void php_natsort(zval* arr, zval* result);

/**
 * Prints human-readable information about a variable.
 *
 * @param value The value to print
 * @param result The result zval (will be true)
 */
void php_print_r(zval* value, zval* result);

/**
 * Repeats a string a specified number of times.
 *
 * @param str The string to repeat
 * @param count The number of times to repeat
 * @param result The result zval (will contain the repeated string)
 */
void php_str_repeat(zval* str, zval* count, zval* result);

/**
 * Check if a file exists.
 *
 * @param path The file path to check
 * @param result The result zval (will be true if file exists, false otherwise)
 */
void php_file_exists(zval* path, zval* result);

/**
 * Strict inequality comparison (!==).
 * Returns true if values are not identical (different types or different values).
 *
 * @param a First value
 * @param b Second value
 * @param result The result zval (will be boolean)
 */
void php_zval_strict_ne(zval* a, zval* b, zval* result);

/**
 * Strict equality comparison (===).
 * Returns true if values are identical (same type and same value).
 *
 * @param a First value
 * @param b Second value
 * @param result The result zval (will be boolean)
 */
void php_zval_strict_eq(zval* a, zval* b, zval* result);

#ifdef __cplusplus
}
#endif

#endif // LIBPHP_STDLIB_H
