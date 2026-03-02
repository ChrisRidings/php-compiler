#include "stdlib.h"

void php_echo(const char* str) {
    if (str == NULL) {
        return;
    }

    printf("%s", str);
}
