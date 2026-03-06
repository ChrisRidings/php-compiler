del test.exe
del output.bc
del php.obj
del output.obj
del output.ll
php llvm/run.php test.php
"C:\Program Files\LLVM\bin\llvm-as" output.ll -o output.bc
"c:\program files\llvm\bin\clang.exe" -O3 -c libphp\php.c -o php.obj
"c:\program files\llvm\bin\clang.exe" -O3 -c output.bc -o output.obj
"c:\program files\llvm\bin\clang.exe" -O3 -march=native output.obj php.obj -o test.exe