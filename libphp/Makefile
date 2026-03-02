# Makefile for libphp library

CC = gcc
AR ?= ar
CFLAGS ?= -Wall -Wextra -O2 -I.
TARGET = libphp.a

SRCS = php.c
OBJS = $(SRCS:.c=.o)

.PHONY: all clean

all: $(TARGET)

$(TARGET): $(OBJS)
	$(AR) rcs $@ $(OBJS)
	@echo "Library created: $@"

%.o: %.c
	$(CC) $(CFLAGS) -c $< -o $@

clean:
	rm -f $(OBJS) $(TARGET)
