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
@__str_const_8b18988784168c7c0cc7be470169013b = private unnamed_addr constant [26 x i8] c"=== While Loop Tests ===
\00"

; Global string constant
@__str_const_7574e188fbb1cea7b7c4fe40275bcdb7 = private unnamed_addr constant [17 x i8] c"Ascending 0..4: \00"

; Global string constant
@__str_const_7215ee9c7d9dc229d2921a40e899ec5f = private unnamed_addr constant [2 x i8] c" \00"

; Global string constant
@__str_const_68b329da9893e34099c7d8ad5cb9c940 = private unnamed_addr constant [2 x i8] c"
\00"

; Global string constant
@__str_const_d6f285cf0457cc09a7b732f30c5e864a = private unnamed_addr constant [18 x i8] c"Descending 5..1: \00"

; Global string constant
@__str_const_738b89736bc30638e0891a66bfc4ac4d = private unnamed_addr constant [21 x i8] c"Do-while loop 1..3: \00"

define i32 @main() {
entry:
  %tmp_1 = getelementptr inbounds [25 x i8], [25 x i8]* @__str_const_8b18988784168c7c0cc7be470169013b, i64 0, i64 0
  %tmp_2 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_2, i8* %tmp_1)
  call void @php_echo_zval(%struct.zval* %tmp_2)

  %tmp_3 = getelementptr inbounds [16 x i8], [16 x i8]* @__str_const_7574e188fbb1cea7b7c4fe40275bcdb7, i64 0, i64 0
  %tmp_4 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_4, i8* %tmp_3)
  call void @php_echo_zval(%struct.zval* %tmp_4)

  %i = alloca %struct.zval
  %tmp_5 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_5, i32 0)
  %tmp_6 = load %struct.zval, %struct.zval* %tmp_5
  store %struct.zval %tmp_6, %struct.zval* %i
  %tmp_7 = alloca %struct.zval
  %tmp_8 = load %struct.zval, %struct.zval* %i
  store %struct.zval %tmp_8, %struct.zval* %tmp_7
  br label %while_header_1
while_header_1:
  %tmp_9 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_9, i32 5)
  %tmp_10 = alloca %struct.zval
  %tmp_11 = call i32 @php_zval_to_int(%struct.zval* %i)
  %tmp_12 = call i32 @php_zval_to_int(%struct.zval* %tmp_9)
  %tmp_13 = icmp slt i32 %tmp_11, %tmp_12
  %tmp_14 = zext i1 %tmp_13 to i32
  call void @php_zval_bool(%struct.zval* %tmp_10, i32 %tmp_14)
  %tmp_15 = call i32 @php_zval_to_int(%struct.zval* %tmp_10)
  %tmp_16 = icmp eq i32 %tmp_15, 0
  br i1 %tmp_16, label %while_after_1, label %while_body_1
while_body_1:
  %tmp_17 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_18 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_18, i8* %tmp_17)
  %tmp_19 = alloca %struct.zval
  %tmp_20 = call i8* @php_zval_to_string(%struct.zval* %i)
  %tmp_21 = call i8* @php_zval_to_string(%struct.zval* %tmp_18)
  %tmp_22 = call i8* @php_concat_strings(i8* %tmp_20, i8* %tmp_21)
  call void @php_zval_string(%struct.zval* %tmp_19, i8* %tmp_22)
  call void @php_echo_zval(%struct.zval* %tmp_19)

  %tmp_23 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_23, i32 1)
  %tmp_24 = call i32 @php_zval_to_int(%struct.zval* %i)
  %tmp_25 = call i32 @php_zval_to_int(%struct.zval* %tmp_23)
  %tmp_26 = add i32 %tmp_24, %tmp_25
  call void @php_zval_int(%struct.zval* %i, i32 %tmp_26)
  %tmp_27 = alloca %struct.zval
  %tmp_28 = load %struct.zval, %struct.zval* %i
  store %struct.zval %tmp_28, %struct.zval* %tmp_27
  br label %while_header_1
while_after_1:

  %tmp_29 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_30 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_30, i8* %tmp_29)
  call void @php_echo_zval(%struct.zval* %tmp_30)

  %tmp_31 = getelementptr inbounds [17 x i8], [17 x i8]* @__str_const_d6f285cf0457cc09a7b732f30c5e864a, i64 0, i64 0
  %tmp_32 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_32, i8* %tmp_31)
  call void @php_echo_zval(%struct.zval* %tmp_32)

  %j = alloca %struct.zval
  %tmp_33 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_33, i32 5)
  %tmp_34 = load %struct.zval, %struct.zval* %tmp_33
  store %struct.zval %tmp_34, %struct.zval* %j
  %tmp_35 = alloca %struct.zval
  %tmp_36 = load %struct.zval, %struct.zval* %j
  store %struct.zval %tmp_36, %struct.zval* %tmp_35
  br label %while_header_2
while_header_2:
  %tmp_37 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_37, i32 0)
  %tmp_38 = alloca %struct.zval
  %tmp_39 = call i32 @php_zval_to_int(%struct.zval* %j)
  %tmp_40 = call i32 @php_zval_to_int(%struct.zval* %tmp_37)
  %tmp_41 = icmp sgt i32 %tmp_39, %tmp_40
  %tmp_42 = zext i1 %tmp_41 to i32
  call void @php_zval_bool(%struct.zval* %tmp_38, i32 %tmp_42)
  %tmp_43 = call i32 @php_zval_to_int(%struct.zval* %tmp_38)
  %tmp_44 = icmp eq i32 %tmp_43, 0
  br i1 %tmp_44, label %while_after_2, label %while_body_2
while_body_2:
  %tmp_45 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_46 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_46, i8* %tmp_45)
  %tmp_47 = alloca %struct.zval
  %tmp_48 = call i8* @php_zval_to_string(%struct.zval* %j)
  %tmp_49 = call i8* @php_zval_to_string(%struct.zval* %tmp_46)
  %tmp_50 = call i8* @php_concat_strings(i8* %tmp_48, i8* %tmp_49)
  call void @php_zval_string(%struct.zval* %tmp_47, i8* %tmp_50)
  call void @php_echo_zval(%struct.zval* %tmp_47)

  %tmp_51 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_51, i32 1)
  %tmp_52 = call i32 @php_zval_to_int(%struct.zval* %j)
  %tmp_53 = call i32 @php_zval_to_int(%struct.zval* %tmp_51)
  %tmp_54 = sub i32 %tmp_52, %tmp_53
  call void @php_zval_int(%struct.zval* %j, i32 %tmp_54)
  %tmp_55 = alloca %struct.zval
  %tmp_56 = load %struct.zval, %struct.zval* %j
  store %struct.zval %tmp_56, %struct.zval* %tmp_55
  br label %while_header_2
while_after_2:

  %tmp_57 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_58 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_58, i8* %tmp_57)
  call void @php_echo_zval(%struct.zval* %tmp_58)

  %tmp_59 = getelementptr inbounds [20 x i8], [20 x i8]* @__str_const_738b89736bc30638e0891a66bfc4ac4d, i64 0, i64 0
  %tmp_60 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_60, i8* %tmp_59)
  call void @php_echo_zval(%struct.zval* %tmp_60)

  %k = alloca %struct.zval
  %tmp_61 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_61, i32 1)
  %tmp_62 = load %struct.zval, %struct.zval* %tmp_61
  store %struct.zval %tmp_62, %struct.zval* %k
  %tmp_63 = alloca %struct.zval
  %tmp_64 = load %struct.zval, %struct.zval* %k
  store %struct.zval %tmp_64, %struct.zval* %tmp_63
  br label %dowhile_body_1
dowhile_body_1:
  %tmp_65 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_66 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_66, i8* %tmp_65)
  %tmp_67 = alloca %struct.zval
  %tmp_68 = call i8* @php_zval_to_string(%struct.zval* %k)
  %tmp_69 = call i8* @php_zval_to_string(%struct.zval* %tmp_66)
  %tmp_70 = call i8* @php_concat_strings(i8* %tmp_68, i8* %tmp_69)
  call void @php_zval_string(%struct.zval* %tmp_67, i8* %tmp_70)
  call void @php_echo_zval(%struct.zval* %tmp_67)

  %tmp_71 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_71, i32 1)
  %tmp_72 = call i32 @php_zval_to_int(%struct.zval* %k)
  %tmp_73 = call i32 @php_zval_to_int(%struct.zval* %tmp_71)
  %tmp_74 = add i32 %tmp_72, %tmp_73
  call void @php_zval_int(%struct.zval* %k, i32 %tmp_74)
  %tmp_75 = alloca %struct.zval
  %tmp_76 = load %struct.zval, %struct.zval* %k
  store %struct.zval %tmp_76, %struct.zval* %tmp_75
  br label %dowhile_header_1
dowhile_header_1:
  %tmp_77 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_77, i32 3)
  %tmp_78 = alloca %struct.zval
  %tmp_79 = call i32 @php_zval_to_int(%struct.zval* %k)
  %tmp_80 = call i32 @php_zval_to_int(%struct.zval* %tmp_77)
  %tmp_81 = icmp sle i32 %tmp_79, %tmp_80
  %tmp_82 = zext i1 %tmp_81 to i32
  call void @php_zval_bool(%struct.zval* %tmp_78, i32 %tmp_82)
  %tmp_83 = call i32 @php_zval_to_int(%struct.zval* %tmp_78)
  %tmp_84 = icmp ne i32 %tmp_83, 0
  br i1 %tmp_84, label %dowhile_body_1, label %dowhile_after_1
dowhile_after_1:

  %tmp_85 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_86 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_86, i8* %tmp_85)
  call void @php_echo_zval(%struct.zval* %tmp_86)

  %l = alloca %struct.zval
  %tmp_87 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_87, i32 5)
  %tmp_88 = load %struct.zval, %struct.zval* %tmp_87
  store %struct.zval %tmp_88, %struct.zval* %l
  %tmp_89 = alloca %struct.zval
  %tmp_90 = load %struct.zval, %struct.zval* %l
  store %struct.zval %tmp_90, %struct.zval* %tmp_89
  br label %dowhile_body_2
dowhile_body_2:
  br label %dowhile_header_2
dowhile_header_2:
  %tmp_91 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_91, i32 0)
  %tmp_92 = alloca %struct.zval
  %tmp_93 = call i32 @php_zval_to_int(%struct.zval* %l)
  %tmp_94 = call i32 @php_zval_to_int(%struct.zval* %tmp_91)
  %tmp_95 = icmp slt i32 %tmp_93, %tmp_94
  %tmp_96 = zext i1 %tmp_95 to i32
  call void @php_zval_bool(%struct.zval* %tmp_92, i32 %tmp_96)
  %tmp_97 = call i32 @php_zval_to_int(%struct.zval* %tmp_92)
  %tmp_98 = icmp ne i32 %tmp_97, 0
  br i1 %tmp_98, label %dowhile_body_2, label %dowhile_after_2
dowhile_after_2:

  ret i32 0
}
