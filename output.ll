; ModuleID = 'phpcompiler'
target datalayout = "e-m:w-p:64:64-i64:64-f80:128-n8:16:32:64-S128"
target triple = "x86_64-pc-windows-msvc"

declare void @php_echo(i8*)

define i32 @main() {
entry:
; Global string constant
@__str_const_6cd3556deb0da54bca060b4c39479839 = private unnamed_addr constant [14 x i8] c"Hello, world!\00"

  %__str_const_6cd3556deb0da54bca060b4c39479839_ptr = getelementptr inbounds [14 x i8], [14 x i8]* @__str_const_6cd3556deb0da54bca060b4c39479839, i64 0, i64 0
  call void @php_echo(i8* %__str_const_6cd3556deb0da54bca060b4c39479839_ptr)

  ret i32 0
}
