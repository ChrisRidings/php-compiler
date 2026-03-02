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
@__str_const_1679091c5a880faf6fb5e6087eb1b2dc = private unnamed_addr constant [2 x i8] c"6\00"

define i32 @main() {
entry:
  %x = alloca %struct.zval
  %tmp_1 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_1679091c5a880faf6fb5e6087eb1b2dc, i64 0, i64 0
  %tmp_2 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_2, i8* %tmp_1)
  %tmp_3 = load %struct.zval, %struct.zval* %tmp_2
  store %struct.zval %tmp_3, %struct.zval* %x
  %tmp_4 = alloca %struct.zval
  %tmp_5 = load %struct.zval, %struct.zval* %x
  store %struct.zval %tmp_5, %struct.zval* %tmp_4
  %y = alloca %struct.zval
  %tmp_6 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_6, i32 3)
  %tmp_7 = load %struct.zval, %struct.zval* %tmp_6
  store %struct.zval %tmp_7, %struct.zval* %y
  %tmp_8 = alloca %struct.zval
  %tmp_9 = load %struct.zval, %struct.zval* %y
  store %struct.zval %tmp_9, %struct.zval* %tmp_8
  %z = alloca %struct.zval
  %tmp_10 = alloca %struct.zval
  %tmp_11 = call i32 @php_zval_to_int(%struct.zval* %x)
  %tmp_12 = call i32 @php_zval_to_int(%struct.zval* %y)
  %tmp_13 = add i32 %tmp_11, %tmp_12
  call void @php_zval_int(%struct.zval* %tmp_10, i32 %tmp_13)
  %tmp_14 = load %struct.zval, %struct.zval* %tmp_10
  store %struct.zval %tmp_14, %struct.zval* %z
  %tmp_15 = alloca %struct.zval
  %tmp_16 = load %struct.zval, %struct.zval* %z
  store %struct.zval %tmp_16, %struct.zval* %tmp_15
  call void @php_echo_zval(%struct.zval* %z)

  ret i32 0
}
