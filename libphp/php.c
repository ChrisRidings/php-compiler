#include <stdlib.h>
#include <stdio.h>
#include <string.h>

#include "php.h"

// Zval creation functions
void php_zval_null(zval* z) {
    z->type = PHP_TYPE_NULL;
}

void php_zval_bool(zval* z, int bool_val) {
    z->type = PHP_TYPE_BOOL;
    z->value.bool_val = bool_val ? 1 : 0;
}

void php_zval_int(zval* z, int int_val) {
    z->type = PHP_TYPE_INT;
    z->value.int_val = int_val;
}

void php_zval_string(zval* z, const char* str) {
    z->type = PHP_TYPE_STRING;
    // For now, we'll just store the pointer directly without copying
    // This is not ideal, but will work for our test
    z->value.str_val = (char*)str;
}

// Zval conversion functions
char* php_zval_to_string(const zval* z) {
    static char buffer[100];

    switch (z->type) {
        case PHP_TYPE_NULL:
            return "NULL";
        case PHP_TYPE_BOOL:
            return z->value.bool_val ? "1" : "0";
        case PHP_TYPE_INT:
            sprintf(buffer, "%d", z->value.int_val);
            return buffer;
        case PHP_TYPE_STRING:
            return z->value.str_val ? z->value.str_val : "NULL";
        default:
            return "Unknown type";
    }
}

int php_zval_to_int(const zval* z) {
    switch (z->type) {
        case PHP_TYPE_NULL:
            return 0;
        case PHP_TYPE_BOOL:
            return z->value.bool_val;
        case PHP_TYPE_INT:
            return z->value.int_val;
        case PHP_TYPE_STRING:
            if (z->value.str_val == NULL) {
                return 0;
            }
            return atoi(z->value.str_val);
        default:
            return 0;
    }
}

void php_echo_zval(const zval* z) {
    const char* str = php_zval_to_string(z);
    php_echo(str);
}

void php_echo(const char* str) {
    if (str == NULL) {
        return;
    }

    printf("%s", str);
}

char* php_itoa(int num) {
    static char buffer[12];
    sprintf(buffer, "%d", num);
    return buffer;
}
