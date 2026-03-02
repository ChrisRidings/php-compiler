del hello.exe
del output.bc
del stdlib.obj
del output.obj
del output.ll
php llvm/run.php helloworld.php
"C:\Program Files\LLVM\bin\llvm-as" output.ll -o output.bc
"c:\program files\llvm\bin\clang.exe" -c libphp\stdlib.c -o stdlib.obj
"c:\program files\llvm\bin\clang.exe" -c output.bc -o output.obj
"c:\program files\llvm\bin\clang.exe" output.obj stdlib.obj -o hello.exe