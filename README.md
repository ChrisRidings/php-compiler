# PHP Compiler

A simple PHP compiler project.

## Current Progress

1. **Lexer** - Tokenizes PHP source code
2. **Parser** - Converts tokens to an Abstract Syntax Tree (AST)
3. **LLVM IR Generator** - Converts AST to LLVM Intermediate Representation
4. **C Library** - Provides PHP standard library functions (like `echo`)

## Requirements

- PHP 8.1+
- GCC (for compiling the C library)
- LLVM (for compiling LLVM IR to native code)

## Building libphp

The `libphp` directory contains the C library with PHP standard functions.

To compile the library:

```bash
cd libphp
make
```

This will create `libphp.a` static library.

## Running the Compiler

### Tokenizing (Lexer)

```bash
php lexer/run.php <filename.php>
```

### Parsing (AST)

```bash
php ast/run.php <filename.php>
```

### Generating LLVM IR

```bash
php llvm/run.php <filename.php> [output.ll]
```

## Example: helloworld.php

### Source Code
```php
<?php
echo "Hello, world!";
```

### Tokenization Output
```
Tokenization complete. Found 3 tokens:

[T_ECHO] 'echo' (line: 2, column: 1)
[T_STRING] '"Hello, world!"' (line: 2, column: 6)
[T_SEMICOLON] ';' (line: 2, column: 21)
```

### Parsing Output
```
Parsing complete. Found 1 statements:

EchoStatement(expressions: [StringLiteral(value: "Hello, world!")])
```

## Compiling to Native Code

After generating LLVM IR, you can compile it to native code:

1. First, compile libphp:
   ```bash
   cd libphp
   make
   ```

2. Generate LLVM IR:
   ```bash
   cd ..
   php llvm/run.php helloworld.php output.ll
   ```

3. Compile LLVM IR to object file:
   ```bash
   llc -filetype=obj output.ll -o output.o
   ```

4. Link with libphp to create executable:
   ```bash
   gcc output.o -Llibphp -lphp -o hello
   ```

5. Run the executable:
   ```bash
   ./hello
   ```
   Output: `Hello, world!`

## Project Structure

```
phpcompiler/
├── lexer/          # Tokenizer
├── ast/            # Parser and AST nodes
├── llvm/           # LLVM IR generator
├── libphp/         # C library for PHP functions
└── README.md       # This file
```
