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

// Array implementation
#define PHP_ARRAY_INITIAL_CAPACITY 8

typedef struct php_array_element {
    char* key;      // NULL for numeric indices
    zval value;
} php_array_element;

typedef struct php_array {
    php_array_element* elements;
    int size;
    int capacity;
} php_array;

void php_array_create(zval* z, int initial_capacity) {
    z->type = PHP_TYPE_ARRAY;
    php_array* arr = (php_array*)malloc(sizeof(php_array));
    if (!arr) return;

    arr->size = 0;
    arr->capacity = initial_capacity > 0 ? initial_capacity : PHP_ARRAY_INITIAL_CAPACITY;
    arr->elements = (php_array_element*)malloc(sizeof(php_array_element) * arr->capacity);
    if (!arr->elements) {
        free(arr);
        return;
    }

    // Store the array pointer in the zval's ptr_val
    z->value.ptr_val = (long long)arr;
}

void php_array_append(zval* arr, zval* elem) {
    if (arr->type != PHP_TYPE_ARRAY) return;

    php_array* array = (php_array*)((long long)arr->value.ptr_val);
    if (!array) return;

    // Resize if needed
    if (array->size >= array->capacity) {
        int new_capacity = array->capacity * 2;
        php_array_element* new_elements = (php_array_element*)realloc(array->elements, sizeof(php_array_element) * new_capacity);
        if (!new_elements) return;
        array->elements = new_elements;
        array->capacity = new_capacity;
    }

    // Copy the element (no key for numeric append)
    array->elements[array->size].key = NULL;
    array->elements[array->size].value = *elem;
    array->size++;
}

void php_array_get(zval* result, zval* arr, zval* index) {
    // Default to null if anything goes wrong
    php_zval_null(result);

    if (arr->type != PHP_TYPE_ARRAY) return;

    php_array* array = (php_array*)((long long)arr->value.ptr_val);
    if (!array) return;

    // If index is a string, look up by key
    if (index->type == PHP_TYPE_STRING && index->value.str_val != NULL) {
        const char* key = index->value.str_val;
        for (int i = 0; i < array->size; i++) {
            if (array->elements[i].key != NULL && strcmp(array->elements[i].key, key) == 0) {
                *result = array->elements[i].value;
                return;
            }
        }
        // Key not found - return null
        return;
    }

    // Otherwise, treat as numeric index
    int idx = php_zval_to_int(index);
    if (idx < 0 || idx >= array->size) return;

    *result = array->elements[idx].value;
}

void php_array_set(zval* arr, const char* key, zval* value) {
    if (arr->type != PHP_TYPE_ARRAY) return;

    php_array* array = (php_array*)((long long)arr->value.ptr_val);
    if (!array) return;

    // Check if key already exists - if so, update the value
    if (key != NULL) {
        for (int i = 0; i < array->size; i++) {
            if (array->elements[i].key != NULL && strcmp(array->elements[i].key, key) == 0) {
                array->elements[i].value = *value;
                return;
            }
        }
    }

    // Resize if needed
    if (array->size >= array->capacity) {
        int new_capacity = array->capacity * 2;
        php_array_element* new_elements = (php_array_element*)realloc(array->elements, sizeof(php_array_element) * new_capacity);
        if (!new_elements) return;
        array->elements = new_elements;
        array->capacity = new_capacity;
    }

    // Copy the key and value
    if (key != NULL) {
        array->elements[array->size].key = strdup(key);
    } else {
        array->elements[array->size].key = NULL;
    }
    array->elements[array->size].value = *value;
    array->size++;
}

void php_array_set_by_index(zval* arr, int index, zval* value) {
    if (arr->type != PHP_TYPE_ARRAY) return;

    php_array* array = (php_array*)((long long)arr->value.ptr_val);
    if (!array) return;

    // If index is within bounds, update the existing element
    if (index >= 0 && index < array->size) {
        array->elements[index].value = *value;
        return;
    }

    // If index is beyond current size, we need to grow the array
    // (This is a simplified implementation - in a full PHP implementation,
    // we'd need to handle sparse arrays differently)
    if (index >= array->size) {
        // Grow the array if needed
        while (index >= array->capacity) {
            int new_capacity = array->capacity * 2;
            php_array_element* new_elements = (php_array_element*)realloc(array->elements, sizeof(php_array_element) * new_capacity);
            if (!new_elements) return;
            array->elements = new_elements;
            array->capacity = new_capacity;
        }

        // Initialize intermediate elements with null if needed
        for (int i = array->size; i < index; i++) {
            array->elements[i].key = NULL;
            array->elements[i].value.type = PHP_TYPE_NULL;
        }

        // Set the value at the specified index
        array->elements[index].key = NULL;
        array->elements[index].value = *value;
        array->size = index + 1;
    }
}

int php_array_size(zval* arr) {
    if (arr->type != PHP_TYPE_ARRAY) return 0;

    php_array* array = (php_array*)((long long)arr->value.ptr_val);
    if (!array) return 0;

    return array->size;
}

char* php_array_get_key(zval* arr, int index) {
    if (arr->type != PHP_TYPE_ARRAY) return NULL;

    php_array* array = (php_array*)((long long)arr->value.ptr_val);
    if (!array) return NULL;

    if (index < 0 || index >= array->size) return NULL;

    return array->elements[index].key; // Returns NULL for numeric indices
}
