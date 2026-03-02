#include "stdlib.h"

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
