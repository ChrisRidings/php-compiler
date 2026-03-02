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

#ifdef __cplusplus
}
#endif

#endif // LIBPHP_STDLIB_H
