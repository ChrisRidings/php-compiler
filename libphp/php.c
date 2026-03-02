#include <stdlib.h>
#include <stdio.h>
#include <string.h>

#include "php.h"

// Thread-local buffer index for php_zval_to_string
_Thread_local int zval_buffer_index = 0;

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

// Zval conversion functions - uses rotating buffers to avoid overwriting
char* php_zval_to_string(const zval* z) {
    // Use multiple buffers to avoid overwriting during nested calls
    static _Thread_local char buffers[8][32];
    static _Thread_local int index = 0;

    char* buffer = buffers[index];
    index = (index + 1) % 8;

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

    while (*str) {
        if (*str == '\\') {
            str++;
            switch (*str) {
                case 'n':
                    putchar('\n');
                    str++;
                    break;
                case 't':
                    putchar('\t');
                    str++;
                    break;
                case 'r':
                    putchar('\r');
                    str++;
                    break;
                case 'b':
                    putchar('\b');
                    str++;
                    break;
                case 'f':
                    putchar('\f');
                    str++;
                    break;
                case '"':
                    putchar('"');
                    str++;
                    break;
                case '\'':
                    putchar('\'');
                    str++;
                    break;
                case '\\':
                    putchar('\\');
                    str++;
                    break;
                default:
                    putchar('\\');
                    // If it's an unrecognized escape sequence, just print it as-is
                    putchar(*str);
                    str++;
                    break;
            }
        } else {
            putchar(*str);
            str++;
        }
    }
}

char* php_itoa(int num) {
    static char buffer[12];
    sprintf(buffer, "%d", num);
    return buffer;
}

char* php_concat_strings(const char* str1, const char* str2) {
    // Calculate required size
    size_t len1 = str1 ? strlen(str1) : 0;
    size_t len2 = str2 ? strlen(str2) : 0;

    // Allocate new buffer
    char* result = (char*)malloc(len1 + len2 + 1);
    if (!result) return NULL;

    // Copy strings
    if (str1) {
        strcpy(result, str1);
    } else {
        result[0] = '\0';
    }
    if (str2) {
        strcat(result, str2);
    }

    return result;
}
