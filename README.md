# PHP to LLVM Compiler

A PHP-to-native compiler that translates PHP code into LLVM IR and compiles it to executable machine code.

## Overview

This project implements a compiler for a subset of PHP, translating PHP source code into LLVM Intermediate Representation (IR) which is then compiled to native executables using the LLVM toolchain. The compiler includes:

- **Lexer**: Tokenizes PHP source code
- **Parser**: Builds an Abstract Syntax Tree (AST) from tokens
- **LLVM Code Generator**: Generates LLVM IR from the AST
- **Runtime Library (libphp)**: C-based runtime providing PHP-compatible functions and types

## Potential Benefits & Future Directions

This project is currently a **prototype/Proof of Concept**, but a production-ready PHP-to-native compiler could offer significant advantages:

### Performance Improvements
- **Native execution speed**: Compiled machine code runs significantly faster than interpreted PHP
- **Reduced memory overhead**: No need for a full PHP interpreter/runtime in memory
- **Better CPU cache utilization**: Static compilation enables better optimization

### Deployment Possibilities
- **Serverless platforms**: Deploy compiled binaries to AWS Lambda, Azure Functions, etc. with millisecond cold starts (vs. seconds for PHP-FPM startup)
- **Edge computing via WASM**: Compile PHP to WebAssembly and run on CDN edge workers (Cloudflare Workers, Fastly Compute@Edge)
- **Frontend PHP via WASM**: Run PHP directly in browsers without a server
- **Microservices**: Lightweight binaries without interpreter/container overhead

### Use Cases
- CLI tools and build scripts
- High-performance API endpoints
- Background job workers
- Static site generators

## Supported PHP Features

### Data Types
- Integers (`int`)
- Strings (`string`)
- Booleans (`bool`)
- Null (`null`)
- Arrays
- Objects (classes with properties and methods)

### Control Structures
- If/else if/else statements
- While loops
- For loops
- Foreach loops
- Break and continue

### Functions
- Function definitions with parameters and return types
- Built-in functions: `echo`, `print_r`, `trim`, `str_replace`, `str_repeat`, etc.
- User-defined functions

### Object-Oriented Programming
- Class definitions
- Property declarations (public)
- Method definitions
- Object instantiation with `new`
- Property access with `->`
- Method calls with `->`
- `$this` reference

### Operators
- Arithmetic: `+`, `-`, `*`, `/`, `%`
- Comparison: `==`, `!=`, `<`, `>`, `<=`, `>=`, `===`, `!==`
- Logical: `&&`, `||`, `!`
- String concatenation: `.`
- Assignment: `=`, `+=`, `-=`, `*=`, `/=`

### Built-in Functions
- `echo` - Output strings
- `print_r` - Print human-readable representation
- `trim` - Strip whitespace from strings
- `str_replace` - Replace text within a string
- `str_repeat` - Repeat a string
- `preg_match` - Regular expression matching
- `file_exists` - Check if a file exists
- `shell_exec` - Execute shell commands
- `opendir`/`readdir`/`closedir` - Directory operations
- `unlink`/`rename` - File operations
- `natsort` - Natural order sorting
- `pathinfo` - Return information about a file path

## Project Structure

```
phpcompiler/
├── lexer/           # Lexical analysis
│   ├── Lexer.php    # Tokenizer
│   ├── Token.php    # Token definitions
│   └── run.php      # Lexer test runner
├── ast/             # Abstract Syntax Tree
│   ├── Parser.php   # Parser implementation
│   ├── Node.php     # Base AST node
│   ├── *.php        # AST node types
│   └── run.php      # Parser test runner
├── llvm/            # LLVM Code Generation
│   ├── Generator.php # LLVM IR generator
│   └── run.php       # Code generation test runner
├── libphp/          # Runtime library
│   ├── php.h        # Header file
│   ├── php.c        # Runtime implementation
│   └── Makefile     # Build configuration
├── tests/           # Test cases
│   ├── 1.php        # Basic arithmetic
│   ├── 2.php        # Functions
│   ├── 3.php        # Loops
│   ├── 4.php        # String operations
│   ├── 5.php        # File operations
│   └── ...          # Additional tests
├── test.php         # Main test file
├── runtests.php     # Test suite runner
└── compile.bat      # Windows compilation script
```

## Building

**Note: This project currently only works on Windows.** The runtime library (`libphp`) uses Windows-specific APIs (CreateProcess, FindFirstFile, etc.) for features like directory operations and shell execution. Unix support to come later.

### Prerequisites
- Windows 10/11
- PHP 8.1+
- LLVM toolchain (clang, llc, opt)
- MinGW-w64

### Build Runtime Library

Open a terminal (Command Prompt or PowerShell) and run:

```bash
cd libphp
make clean
make
```

This creates `libphp.a` (static library) and `php.o` (object file) required for linking.

## Usage

### Quick Compile (test.php)

The provided batch script compiles [`test.php`](test.php) to `test.exe`:

```batch
compile.bat
```

Then run the compiled executable:

```batch
test.exe
```

### Compile Any PHP File

Use the LLVM code generator directly:

```batch
php llvm/run.php input.php output.exe
```

Then run:

```batch
output.exe
```

### Run Tests

Run with PHP interpreter:

```batch
php runtests.php
```

Or compile the test runner to native code:

```batch
php llvm/run.php runtests.php runtests.exe
runtests.exe
```

This will compile and run all test files in the `tests/` directory, comparing output with the reference PHP interpreter.

## Example

Input PHP file:
```php
<?php
class Counter {
    public int $value = 0;

    public function increment() {
        $this->value = $this->value + 1;
    }

    public function getValue(): int {
        return $this->value;
    }
}

$counter = new Counter();
$counter->increment();
$counter->increment();
echo $counter->getValue() . "\n";
```

Compiles to native code that outputs:
```
2
```

## Architecture

### Compilation Pipeline

1. **Lexical Analysis**: Source code → Tokens
2. **Parsing**: Tokens → AST
3. **Code Generation**: AST → LLVM IR
4. **Optimization**: LLVM IR passes through optimizer (`opt`)
5. **Assembly**: LLVM IR → Object code (`llc`)
6. **Linking**: Object code + Runtime → Executable (`clang`)

### Type System

The compiler uses a unified `zval` (Zend Value) structure from the runtime library to represent PHP values:

```c
typedef struct {
    int type;
    union {
        int int_val;
        char* str_val;
        int bool_val;
        php_object* obj_val;
        long long ptr_val;
    } value;
} zval;
```

## Limitations

This is a prototype compiler implementation. Notable limitations:

- No support for PHP's dynamic typing (requires type hints)
- Limited standard library coverage
- **Windows only** - uses Windows APIs in runtime library
- No garbage collection (memory leaks possible)
- No exception handling
- No namespaces
- Limited reflection capabilities
- Classes must declare properties
- No support for static methods/properties
- No inheritance or interfaces
