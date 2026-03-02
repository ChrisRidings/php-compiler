; ModuleID = 'phpcompiler'
target datalayout = "e-m:w-p:64:64-i64:64-f80:128-n8:16:32:64-S128"
target triple = "x86_64-pc-windows-msvc"

%struct.zval = type { i32, %union.zval_value }
%union.zval_value = type { i64 }

declare void @php_echo_zval(%struct.zval*)
declare void @php_zval_null(%struct.zval*)
declare void @php_zval_bool(%struct.zval*, i32)
declare void @php_zval_int(%struct.zval*, i32)
declare void @php_zval_string(%struct.zval*, i8*)
declare i8* @php_zval_to_string(%struct.zval*)
declare i32 @php_zval_to_int(%struct.zval*)
declare void @php_echo(i8*)
declare i8* @php_itoa(i32)
declare i8* @php_concat_strings(i8*, i8*)
declare void @php_array_create(%struct.zval*, i32)
declare void @php_array_append(%struct.zval*, %struct.zval*)
declare void @php_array_get(%struct.zval*, %struct.zval*, %struct.zval*)
declare void @php_array_set(%struct.zval*, i8*, %struct.zval*)
declare void @php_array_set_by_index(%struct.zval*, i32, %struct.zval*)
declare i32 @php_array_size(%struct.zval*)
declare i8* @php_array_get_key(%struct.zval*, i32)
declare void @php_array_values(%struct.zval*, %struct.zval*)
declare void @php_opendir(%struct.zval*, %struct.zval*)
declare void @php_readdir(%struct.zval*, %struct.zval*)
declare void @php_closedir(%struct.zval*, %struct.zval*)
declare void @php_preg_match(%struct.zval*, %struct.zval*, %struct.zval*)
declare void @php_natsort(%struct.zval*, %struct.zval*)
declare void @php_print_r(%struct.zval*, %struct.zval*)
declare void @php_zval_strict_ne(%struct.zval*, %struct.zval*, %struct.zval*)
declare void @php_zval_strict_eq(%struct.zval*, %struct.zval*, %struct.zval*)
declare void @php_str_repeat(%struct.zval*, %struct.zval*, %struct.zval*)
declare void @php_file_exists(%struct.zval*, %struct.zval*)
declare void @php_shell_exec(%struct.zval*, %struct.zval*)
declare void @php_pathinfo(%struct.zval*, %struct.zval*, %struct.zval*)
declare void @php_rename(%struct.zval*, %struct.zval*, %struct.zval*)
declare void @php_unlink(%struct.zval*, %struct.zval*)

; Global string constant
@__str_const_c84cabbaebee9a9631c8be234ac64c26 = private unnamed_addr constant [8 x i8] c"Hello, \00"

; Global string constant
@__str_const_9033e0e305f247c0c3c80d0c7848c8b3 = private unnamed_addr constant [2 x i8] c"!\00"

; Global string constant
@__str_const_64489c85dc2fe0787b85cd87214b3810 = private unnamed_addr constant [6 x i8] c"Alice\00"

define %struct.zval @say_hello(%struct.zval) {
entry:
  %name = alloca %struct.zval
  store %struct.zval %0, %struct.zval* %name
  %tmp_1 = getelementptr inbounds [7 x i8], [7 x i8]* @__str_const_c84cabbaebee9a9631c8be234ac64c26, i64 0, i64 0
  %tmp_2 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_2, i8* %tmp_1)
  call void @php_echo_zval(%struct.zval* %tmp_2)

  call void @php_echo_zval(%struct.zval* %name)

  %tmp_3 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_9033e0e305f247c0c3c80d0c7848c8b3, i64 0, i64 0
  %tmp_4 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_4, i8* %tmp_3)
  call void @php_echo_zval(%struct.zval* %tmp_4)

  %tmp_5 = alloca %struct.zval
  call void @php_zval_null(%struct.zval* %tmp_5)
  %tmp_6 = load %struct.zval, %struct.zval* %tmp_5
  ret %struct.zval %tmp_6
}

define i32 @main() {
entry:
  %tmp_7 = alloca %struct.zval
  %tmp_8 = getelementptr inbounds [5 x i8], [5 x i8]* @__str_const_64489c85dc2fe0787b85cd87214b3810, i64 0, i64 0
  %tmp_9 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_9, i8* %tmp_8)
  %tmp_10 = load %struct.zval, %struct.zval* %tmp_9
  %tmp_11 = call %struct.zval @say_hello(%struct.zval %tmp_10)
  store %struct.zval %tmp_11, %struct.zval* %tmp_7
  ret i32 0
}
