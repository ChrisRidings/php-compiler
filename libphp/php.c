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

void php_array_values(zval* arr, zval* result) {
    if (arr->type != PHP_TYPE_ARRAY) {
        php_zval_null(result);
        return;
    }

    php_array* array = (php_array*)((long long)arr->value.ptr_val);
    if (!array) {
        php_zval_null(result);
        return;
    }

    // Create result array
    php_array_create(result, array->size);
    php_array* result_array = (php_array*)((long long)result->value.ptr_val);

    // Copy values with numeric indices
    for (int i = 0; i < array->size; i++) {
        php_array_append(result, &array->elements[i].value);
    }
}

// Directory functions - Windows compatible
#ifdef _WIN32
#include <windows.h>
#else
#include <dirent.h>
#endif

// Directory handle structure to maintain state
#define MAX_DIR_HANDLES 16

typedef struct {
    int used;
    char path[512];
    #ifdef _WIN32
    HANDLE hFind;
    WIN32_FIND_DATA findData;
    int firstEntry;  // Track if we need to call FindFirstFile
    #else
    DIR* dir;
    #endif
} php_dir_handle;

static php_dir_handle dir_handles[MAX_DIR_HANDLES];
static int next_dir_handle = 1;

void php_opendir(zval* path, zval* result) {
    if (path->type != PHP_TYPE_STRING || path->value.str_val == NULL) {
        php_zval_bool(result, 0);  // false
        return;
    }

    // Find a free handle slot
    int handle_id = -1;
    for (int i = 0; i < MAX_DIR_HANDLES; i++) {
        if (!dir_handles[i].used) {
            handle_id = i;
            break;
        }
    }

    if (handle_id == -1) {
        php_zval_bool(result, 0);  // false - no free handles
        return;
    }

    php_dir_handle* dh = &dir_handles[handle_id];

    #ifdef _WIN32
    // Windows: Use FindFirstFile with * wildcard to get all files
    snprintf(dh->path, sizeof(dh->path), "%s\\*", path->value.str_val);
    dh->hFind = FindFirstFile(dh->path, &dh->findData);
    if (dh->hFind == INVALID_HANDLE_VALUE) {
        php_zval_bool(result, 0);  // false
        return;
    }
    dh->firstEntry = 1;
    #else
    dh->dir = opendir(path->value.str_val);
    if (!dh->dir) {
        php_zval_bool(result, 0);  // false
        return;
    }
    #endif

    dh->used = 1;
    php_zval_int(result, handle_id + 1);  // Return 1-based handle id
}

void php_readdir(zval* handle, zval* result) {
    if (handle->type != PHP_TYPE_INT) {
        php_zval_bool(result, 0);  // false
        return;
    }

    int handle_id = handle->value.int_val - 1;  // Convert to 0-based index
    if (handle_id < 0 || handle_id >= MAX_DIR_HANDLES || !dir_handles[handle_id].used) {
        php_zval_bool(result, 0);  // false - invalid handle
        return;
    }

    php_dir_handle* dh = &dir_handles[handle_id];

    #ifdef _WIN32
    // Windows implementation
    if (!dh->firstEntry) {
        // Get next entry
        if (!FindNextFile(dh->hFind, &dh->findData)) {
            // No more entries
            php_zval_bool(result, 0);  // false
            return;
        }
    }
    dh->firstEntry = 0;

    // Skip . and .. entries
    while (strcmp(dh->findData.cFileName, ".") == 0 || strcmp(dh->findData.cFileName, "..") == 0) {
        if (!FindNextFile(dh->hFind, &dh->findData)) {
            php_zval_bool(result, 0);  // false
            return;
        }
    }

    php_zval_string(result, _strdup(dh->findData.cFileName));
    #else
    // POSIX implementation
    struct dirent* entry;
    do {
        entry = readdir(dh->dir);
        if (!entry) {
            php_zval_bool(result, 0);  // false - no more entries
            return;
        }
    } while (strcmp(entry->d_name, ".") == 0 || strcmp(entry->d_name, "..") == 0);

    php_zval_string(result, strdup(entry->d_name));
    #endif
}

void php_closedir(zval* handle, zval* result) {
    if (handle->type != PHP_TYPE_INT) {
        php_zval_bool(result, 0);  // false
        return;
    }

    int handle_id = handle->value.int_val - 1;  // Convert to 0-based index
    if (handle_id < 0 || handle_id >= MAX_DIR_HANDLES || !dir_handles[handle_id].used) {
        php_zval_bool(result, 0);  // false - invalid handle
        return;
    }

    php_dir_handle* dh = &dir_handles[handle_id];

    #ifdef _WIN32
    FindClose(dh->hFind);
    #else
    closedir(dh->dir);
    #endif

    dh->used = 0;
    php_zval_bool(result, 1);  // true - success
}

// Simple regex match function (simplified version)
void php_preg_match(zval* pattern, zval* subject, zval* result) {
    if (pattern->type != PHP_TYPE_STRING || subject->type != PHP_TYPE_STRING) {
        php_zval_int(result, 0);
        return;
    }

    // For now, do a simple string match instead of full regex
    // This is a simplified implementation
    const char* pat = pattern->value.str_val;
    const char* sub = subject->value.str_val;

    // Check if pattern starts with '/' (PCRE delimiter)
    if (pat[0] == '/') {
        // Skip delimiters and modifiers for now
        // This is a very basic implementation
        int match = strstr(sub, pat) != NULL;
        php_zval_int(result, match);
    } else {
        // Simple substring match
        int match = strstr(sub, pat) != NULL;
        php_zval_int(result, match);
    }
}

// Simple natural order comparison (fallback for systems without strverscmp)
static int natural_compare(const void* a, const void* b) {
    const php_array_element* ea = (const php_array_element*)a;
    const php_array_element* eb = (const php_array_element*)b;

    char* str_a = php_zval_to_string(&ea->value);
    char* str_b = php_zval_to_string(&eb->value);

    // Simple string comparison (not true natural order, but works for basic cases)
    // A full implementation would handle numbers specially
    return strcmp(str_a, str_b);
}

void php_natsort(zval* arr, zval* result) {
    if (arr->type != PHP_TYPE_ARRAY) {
        php_zval_null(result);
        return;
    }

    php_array* array = (php_array*)((long long)arr->value.ptr_val);
    if (!array) {
        php_zval_null(result);
        return;
    }

    // Sort the array using natural order comparison
    qsort(array->elements, array->size, sizeof(php_array_element), natural_compare);

    // Return the sorted array (same array, modified)
    *result = *arr;
}

// Print_r implementation
void php_print_r(zval* value, zval* result) {
    switch (value->type) {
        case PHP_TYPE_NULL:
            php_echo("\n");
            break;
        case PHP_TYPE_BOOL:
            php_echo(value->value.bool_val ? "1\n" : "\n");
            break;
        case PHP_TYPE_INT:
            {
                char buf[32];
                sprintf(buf, "%d\n", value->value.int_val);
                php_echo(buf);
            }
            break;
        case PHP_TYPE_STRING:
            if (value->value.str_val) {
                php_echo(value->value.str_val);
                php_echo("\n");
            }
            break;
        case PHP_TYPE_ARRAY:
            {
                php_array* arr = (php_array*)((long long)value->value.ptr_val);
                if (arr) {
                    php_echo("Array\n(\n");
                    for (int i = 0; i < arr->size; i++) {
                        php_echo("    [");
                        if (arr->elements[i].key) {
                            php_echo(arr->elements[i].key);
                        } else {
                            char idx[16];
                            sprintf(idx, "%d", i);
                            php_echo(idx);
                        }
                        php_echo("] => ");

                        zval elem_result;
                        php_print_r(&arr->elements[i].value, &elem_result);
                    }
                    php_echo(")\n");
                }
            }
            break;
    }
    php_zval_bool(result, 1);  // return true
}

// Strict inequality comparison (!==)
void php_zval_strict_ne(zval* a, zval* b, zval* result) {
    // If types are different, they are not identical
    if (a->type != b->type) {
        php_zval_bool(result, 1);  // true - not identical
        return;
    }

    // Same type - compare values
    int not_equal = 0;
    switch (a->type) {
        case PHP_TYPE_NULL:
            // Both null - they are identical
            not_equal = 0;
            break;
        case PHP_TYPE_BOOL:
            not_equal = (a->value.bool_val != b->value.bool_val);
            break;
        case PHP_TYPE_INT:
            not_equal = (a->value.int_val != b->value.int_val);
            break;
        case PHP_TYPE_STRING:
            if (a->value.str_val == NULL && b->value.str_val == NULL) {
                not_equal = 0;
            } else if (a->value.str_val == NULL || b->value.str_val == NULL) {
                not_equal = 1;
            } else {
                not_equal = (strcmp(a->value.str_val, b->value.str_val) != 0);
            }
            break;
        case PHP_TYPE_ARRAY:
            // For arrays, we compare pointers (same reference check)
            not_equal = (a->value.ptr_val != b->value.ptr_val);
            break;
        default:
            not_equal = 1;
    }

    php_zval_bool(result, not_equal);
}

// Strict equality comparison (===)
void php_zval_strict_eq(zval* a, zval* b, zval* result) {
    // If types are different, they are not identical
    if (a->type != b->type) {
        php_zval_bool(result, 0);  // false - not identical
        return;
    }

    // Same type - compare values
    int equal = 0;
    switch (a->type) {
        case PHP_TYPE_NULL:
            // Both null - they are identical
            equal = 1;
            break;
        case PHP_TYPE_BOOL:
            equal = (a->value.bool_val == b->value.bool_val);
            break;
        case PHP_TYPE_INT:
            equal = (a->value.int_val == b->value.int_val);
            break;
        case PHP_TYPE_STRING:
            if (a->value.str_val == NULL && b->value.str_val == NULL) {
                equal = 1;
            } else if (a->value.str_val == NULL || b->value.str_val == NULL) {
                equal = 0;
            } else {
                equal = (strcmp(a->value.str_val, b->value.str_val) == 0);
            }
            break;
        case PHP_TYPE_ARRAY:
            // For arrays, we compare pointers (same reference check)
            equal = (a->value.ptr_val == b->value.ptr_val);
            break;
        default:
            equal = 0;
    }

    php_zval_bool(result, equal);
}
