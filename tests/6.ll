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
@__str_const_3b4df51f8f8f3229cb4b950d4f8b9175 = private unnamed_addr constant [5 x i8] c"big
\00"

; Global string constant
@__str_const_d15dbfcb847653913855e21370d83af1 = private unnamed_addr constant [7 x i8] c"small
\00"

; Global string constant
@__str_const_2c28e2edbe7f07985b4e9916715a4805 = private unnamed_addr constant [14 x i8] c"more than 8!
\00"

define i32 @main() {
entry:
  %x = alloca %struct.zval
  %tmp_1 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_1, i32 10)
  %tmp_2 = load %struct.zval, %struct.zval* %tmp_1
  store %struct.zval %tmp_2, %struct.zval* %x
  %tmp_3 = alloca %struct.zval
  %tmp_4 = load %struct.zval, %struct.zval* %x
  store %struct.zval %tmp_4, %struct.zval* %tmp_3
  %tmp_5 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_5, i32 5)
  %tmp_6 = alloca %struct.zval
  %tmp_7 = call i32 @php_zval_to_int(%struct.zval* %x)
  %tmp_8 = call i32 @php_zval_to_int(%struct.zval* %tmp_5)
  %tmp_9 = icmp sgt i32 %tmp_7, %tmp_8
  %tmp_10 = zext i1 %tmp_9 to i32
  call void @php_zval_bool(%struct.zval* %tmp_6, i32 %tmp_10)
  %tmp_11 = load %struct.zval, %struct.zval* %tmp_6
  %tmp_12 = call i32 @php_zval_to_int(%struct.zval* %tmp_6)
  %tmp_13 = icmp ne i32 %tmp_12, 0
  br i1 %tmp_13, label %then_1, label %else_1
then_1:
  %tmp_14 = getelementptr inbounds [4 x i8], [4 x i8]* @__str_const_3b4df51f8f8f3229cb4b950d4f8b9175, i64 0, i64 0
  %tmp_15 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_15, i8* %tmp_14)
  call void @php_echo_zval(%struct.zval* %tmp_15)

  br label %merge_1
else_1:
  %tmp_16 = getelementptr inbounds [6 x i8], [6 x i8]* @__str_const_d15dbfcb847653913855e21370d83af1, i64 0, i64 0
  %tmp_17 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_17, i8* %tmp_16)
  call void @php_echo_zval(%struct.zval* %tmp_17)

  br label %merge_1
merge_1:

  %tmp_18 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_18, i32 8)
  %tmp_19 = alloca %struct.zval
  %tmp_20 = call i32 @php_zval_to_int(%struct.zval* %x)
  %tmp_21 = call i32 @php_zval_to_int(%struct.zval* %tmp_18)
  %tmp_22 = icmp sgt i32 %tmp_20, %tmp_21
  %tmp_23 = zext i1 %tmp_22 to i32
  call void @php_zval_bool(%struct.zval* %tmp_19, i32 %tmp_23)
  %tmp_24 = load %struct.zval, %struct.zval* %tmp_19
  %tmp_25 = call i32 @php_zval_to_int(%struct.zval* %tmp_19)
  %tmp_26 = icmp ne i32 %tmp_25, 0
  br i1 %tmp_26, label %then_2, label %else_2
then_2:
  %tmp_27 = getelementptr inbounds [13 x i8], [13 x i8]* @__str_const_2c28e2edbe7f07985b4e9916715a4805, i64 0, i64 0
  %tmp_28 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_28, i8* %tmp_27)
  call void @php_echo_zval(%struct.zval* %tmp_28)

  br label %merge_2
else_2:
  br label %merge_2
merge_2:

  ret i32 0
}
