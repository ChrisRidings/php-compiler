#ifndef LIBPHP_STDLIB_H
#define LIBPHP_STDLIB_H

#include <stdio.h>
#include <stdlib.h>

#ifdef __cplusplus
extern "C" {
#endif

/**
 * Prints a string to standard output.
 *
 * @param str The string to print (null-terminated)
 */
void php_echo(const char* str);

#ifdef __cplusplus
}
#endif

#endif // LIBPHP_STDLIB_H
