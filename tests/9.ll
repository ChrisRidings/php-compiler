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

define %struct.zval @factorial(%struct.zval) {
entry:
  %n = alloca %struct.zval
  store %struct.zval %0, %struct.zval* %n
  %tmp_1 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_1, i32 1)
  %tmp_2 = alloca %struct.zval
  %tmp_3 = call i32 @php_zval_to_int(%struct.zval* %n)
  %tmp_4 = call i32 @php_zval_to_int(%struct.zval* %tmp_1)
  %tmp_5 = icmp sle i32 %tmp_3, %tmp_4
  %tmp_6 = zext i1 %tmp_5 to i32
  call void @php_zval_bool(%struct.zval* %tmp_2, i32 %tmp_6)
  %tmp_7 = load %struct.zval, %struct.zval* %tmp_2
  %tmp_8 = call i32 @php_zval_to_int(%struct.zval* %tmp_2)
  %tmp_9 = icmp ne i32 %tmp_8, 0
  br i1 %tmp_9, label %then_1, label %else_1
then_1:
  %tmp_10 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_10, i32 1)
  %tmp_11 = load %struct.zval, %struct.zval* %tmp_10
  ret %struct.zval %tmp_11

else_1:
  br label %merge_1
merge_1:

  %tmp_12 = alloca %struct.zval
  %tmp_13 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_13, i32 1)
  %tmp_14 = alloca %struct.zval
  %tmp_15 = call i32 @php_zval_to_int(%struct.zval* %n)
  %tmp_16 = call i32 @php_zval_to_int(%struct.zval* %tmp_13)
  %tmp_17 = sub i32 %tmp_15, %tmp_16
  call void @php_zval_int(%struct.zval* %tmp_14, i32 %tmp_17)
  %tmp_18 = load %struct.zval, %struct.zval* %tmp_14
  %tmp_19 = call %struct.zval @factorial(%struct.zval %tmp_18)
  store %struct.zval %tmp_19, %struct.zval* %tmp_12
  %tmp_20 = alloca %struct.zval
  %tmp_21 = call i32 @php_zval_to_int(%struct.zval* %n)
  %tmp_22 = call i32 @php_zval_to_int(%struct.zval* %tmp_12)
  %tmp_23 = mul i32 %tmp_21, %tmp_22
  call void @php_zval_int(%struct.zval* %tmp_20, i32 %tmp_23)
  %tmp_24 = load %struct.zval, %struct.zval* %tmp_20
  ret %struct.zval %tmp_24

}

define i32 @main() {
entry:
  %tmp_25 = alloca %struct.zval
  %tmp_26 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_26, i32 5)
  %tmp_27 = load %struct.zval, %struct.zval* %tmp_26
  %tmp_28 = call %struct.zval @factorial(%struct.zval %tmp_27)
  store %struct.zval %tmp_28, %struct.zval* %tmp_25
  call void @php_echo_zval(%struct.zval* %tmp_25)

  ret i32 0
}
