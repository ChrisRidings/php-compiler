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
@__str_const_954e6f62f0633521f4f31307f5e21684 = private unnamed_addr constant [21 x i8] c"=== Array Tests ===
\00"

; Global string constant
@__str_const_dba3d05a521b696ff6ff0035d1fbb5ee = private unnamed_addr constant [16 x i8] c"Numeric array:
\00"

; Global string constant
@__str_const_7215ee9c7d9dc229d2921a40e899ec5f = private unnamed_addr constant [2 x i8] c" \00"

; Global string constant
@__str_const_68b329da9893e34099c7d8ad5cb9c940 = private unnamed_addr constant [2 x i8] c"
\00"

; Global string constant
@__str_const_0cc175b9c0f1b6a831c399e269772661 = private unnamed_addr constant [2 x i8] c"a\00"

; Global string constant
@__str_const_92eb5ffee6ae2fec3ad71c777531578f = private unnamed_addr constant [2 x i8] c"b\00"

; Global string constant
@__str_const_4a8a08f09d37b73795649038408b5f33 = private unnamed_addr constant [2 x i8] c"c\00"

; Global string constant
@__str_const_806ece0c2fa260090a035ca8e59ff4bb = private unnamed_addr constant [20 x i8] c"Associative array:
\00"

; Global string constant
@__str_const_d02c4c4cde7ae76252540d116a40f23a = private unnamed_addr constant [5 x i8] c"zero\00"

; Global string constant
@__str_const_b8a9f715dbb64fd5c56e7783c6820a61 = private unnamed_addr constant [4 x i8] c"two\00"

; Global string constant
@__str_const_f97c5d29941bfb1b2fdab0874906ab82 = private unnamed_addr constant [4 x i8] c"one\00"

; Global string constant
@__str_const_8c1013e9e5b4e88ce3dbdb44d0151dc4 = private unnamed_addr constant [14 x i8] c"Mixed array:
\00"

; Global string constant
@__str_const_6a97c1c166bf06f79b96fd214b14b47a = private unnamed_addr constant [24 x i8] c"Updated numeric array:
\00"

; Global string constant
@__str_const_ac0adc6ee2133eaf8c58c227a26b3ae4 = private unnamed_addr constant [28 x i8] c"Updated associative array:
\00"

; Global string constant
@__str_const_93a2c6f9f4ef533db1f4cbd34d16f8cd = private unnamed_addr constant [26 x i8] c"Iterating numeric array:
\00"

; Global string constant
@__str_const_fea32c352d2025a7adbd13eb20933c18 = private unnamed_addr constant [30 x i8] c"Iterating associative array:
\00"

; Global string constant
@__str_const_43ec3e5dee6e706af7766fffea512721 = private unnamed_addr constant [2 x i8] c"=\00"

; Global string constant
@__str_const_d74b9ae2a12865e2a84d0fab2a60c6d0 = private unnamed_addr constant [21 x i8] c"Empty array length: \00"

; Global string constant
@__str_const_91c806590758cf507461943dd9bf1485 = private unnamed_addr constant [23 x i8] c"Single element array: \00"

; Global string constant
@__str_const_ee5c35ce3d081da90622a10d2272ed5e = private unnamed_addr constant [8 x i8] c"numbers\00"

; Global string constant
@__str_const_9a31962b953d38f5702495fdf5b44ad0 = private unnamed_addr constant [8 x i8] c"letters\00"

; Global string constant
@__str_const_03aaab3ff080774fc465846676e662d3 = private unnamed_addr constant [15 x i8] c"Nested array:
\00"

define i32 @main() {
entry:
  %tmp_1 = getelementptr inbounds [20 x i8], [20 x i8]* @__str_const_954e6f62f0633521f4f31307f5e21684, i64 0, i64 0
  %tmp_2 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_2, i8* %tmp_1)
  call void @php_echo_zval(%struct.zval* %tmp_2)

  %nums = alloca %struct.zval
  %tmp_3 = alloca %struct.zval
  call void @php_array_create(%struct.zval* %tmp_3, i32 3)
  %tmp_4 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_4, i32 10)
  call void @php_array_append(%struct.zval* %tmp_3, %struct.zval* %tmp_4)
  %tmp_5 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_5, i32 20)
  call void @php_array_append(%struct.zval* %tmp_3, %struct.zval* %tmp_5)
  %tmp_6 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_6, i32 30)
  call void @php_array_append(%struct.zval* %tmp_3, %struct.zval* %tmp_6)
  %tmp_7 = load %struct.zval, %struct.zval* %tmp_3
  store %struct.zval %tmp_7, %struct.zval* %nums
  %tmp_8 = alloca %struct.zval
  %tmp_9 = load %struct.zval, %struct.zval* %nums
  store %struct.zval %tmp_9, %struct.zval* %tmp_8
  %tmp_10 = getelementptr inbounds [15 x i8], [15 x i8]* @__str_const_dba3d05a521b696ff6ff0035d1fbb5ee, i64 0, i64 0
  %tmp_11 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_11, i8* %tmp_10)
  call void @php_echo_zval(%struct.zval* %tmp_11)

  %tmp_12 = alloca %struct.zval
  %tmp_13 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_13, i32 0)
  call void @php_array_get(%struct.zval* %tmp_12, %struct.zval* %nums, %struct.zval* %tmp_13)
  %tmp_14 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_15 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_15, i8* %tmp_14)
  %tmp_16 = alloca %struct.zval
  %tmp_17 = call i8* @php_zval_to_string(%struct.zval* %tmp_12)
  %tmp_18 = call i8* @php_zval_to_string(%struct.zval* %tmp_15)
  %tmp_19 = call i8* @php_concat_strings(i8* %tmp_17, i8* %tmp_18)
  call void @php_zval_string(%struct.zval* %tmp_16, i8* %tmp_19)
  call void @php_echo_zval(%struct.zval* %tmp_16)

  %tmp_20 = alloca %struct.zval
  %tmp_21 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_21, i32 1)
  call void @php_array_get(%struct.zval* %tmp_20, %struct.zval* %nums, %struct.zval* %tmp_21)
  %tmp_22 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_23 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_23, i8* %tmp_22)
  %tmp_24 = alloca %struct.zval
  %tmp_25 = call i8* @php_zval_to_string(%struct.zval* %tmp_20)
  %tmp_26 = call i8* @php_zval_to_string(%struct.zval* %tmp_23)
  %tmp_27 = call i8* @php_concat_strings(i8* %tmp_25, i8* %tmp_26)
  call void @php_zval_string(%struct.zval* %tmp_24, i8* %tmp_27)
  call void @php_echo_zval(%struct.zval* %tmp_24)

  %tmp_28 = alloca %struct.zval
  %tmp_29 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_29, i32 2)
  call void @php_array_get(%struct.zval* %tmp_28, %struct.zval* %nums, %struct.zval* %tmp_29)
  %tmp_30 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_31 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_31, i8* %tmp_30)
  %tmp_32 = alloca %struct.zval
  %tmp_33 = call i8* @php_zval_to_string(%struct.zval* %tmp_28)
  %tmp_34 = call i8* @php_zval_to_string(%struct.zval* %tmp_31)
  %tmp_35 = call i8* @php_concat_strings(i8* %tmp_33, i8* %tmp_34)
  call void @php_zval_string(%struct.zval* %tmp_32, i8* %tmp_35)
  call void @php_echo_zval(%struct.zval* %tmp_32)

  %assoc = alloca %struct.zval
  %tmp_36 = alloca %struct.zval
  call void @php_array_create(%struct.zval* %tmp_36, i32 3)
  %tmp_37 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_37, i32 1)
  %tmp_38 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_0cc175b9c0f1b6a831c399e269772661, i64 0, i64 0
  call void @php_array_set(%struct.zval* %tmp_36, i8* %tmp_38, %struct.zval* %tmp_37)
  %tmp_39 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_39, i32 2)
  %tmp_40 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_92eb5ffee6ae2fec3ad71c777531578f, i64 0, i64 0
  call void @php_array_set(%struct.zval* %tmp_36, i8* %tmp_40, %struct.zval* %tmp_39)
  %tmp_41 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_41, i32 3)
  %tmp_42 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_4a8a08f09d37b73795649038408b5f33, i64 0, i64 0
  call void @php_array_set(%struct.zval* %tmp_36, i8* %tmp_42, %struct.zval* %tmp_41)
  %tmp_43 = load %struct.zval, %struct.zval* %tmp_36
  store %struct.zval %tmp_43, %struct.zval* %assoc
  %tmp_44 = alloca %struct.zval
  %tmp_45 = load %struct.zval, %struct.zval* %assoc
  store %struct.zval %tmp_45, %struct.zval* %tmp_44
  %tmp_46 = getelementptr inbounds [19 x i8], [19 x i8]* @__str_const_806ece0c2fa260090a035ca8e59ff4bb, i64 0, i64 0
  %tmp_47 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_47, i8* %tmp_46)
  call void @php_echo_zval(%struct.zval* %tmp_47)

  %tmp_48 = alloca %struct.zval
  %tmp_49 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_0cc175b9c0f1b6a831c399e269772661, i64 0, i64 0
  %tmp_50 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_50, i8* %tmp_49)
  call void @php_array_get(%struct.zval* %tmp_48, %struct.zval* %assoc, %struct.zval* %tmp_50)
  %tmp_51 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_52 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_52, i8* %tmp_51)
  %tmp_53 = alloca %struct.zval
  %tmp_54 = call i8* @php_zval_to_string(%struct.zval* %tmp_48)
  %tmp_55 = call i8* @php_zval_to_string(%struct.zval* %tmp_52)
  %tmp_56 = call i8* @php_concat_strings(i8* %tmp_54, i8* %tmp_55)
  call void @php_zval_string(%struct.zval* %tmp_53, i8* %tmp_56)
  call void @php_echo_zval(%struct.zval* %tmp_53)

  %tmp_57 = alloca %struct.zval
  %tmp_58 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_92eb5ffee6ae2fec3ad71c777531578f, i64 0, i64 0
  %tmp_59 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_59, i8* %tmp_58)
  call void @php_array_get(%struct.zval* %tmp_57, %struct.zval* %assoc, %struct.zval* %tmp_59)
  %tmp_60 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_61 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_61, i8* %tmp_60)
  %tmp_62 = alloca %struct.zval
  %tmp_63 = call i8* @php_zval_to_string(%struct.zval* %tmp_57)
  %tmp_64 = call i8* @php_zval_to_string(%struct.zval* %tmp_61)
  %tmp_65 = call i8* @php_concat_strings(i8* %tmp_63, i8* %tmp_64)
  call void @php_zval_string(%struct.zval* %tmp_62, i8* %tmp_65)
  call void @php_echo_zval(%struct.zval* %tmp_62)

  %tmp_66 = alloca %struct.zval
  %tmp_67 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_4a8a08f09d37b73795649038408b5f33, i64 0, i64 0
  %tmp_68 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_68, i8* %tmp_67)
  call void @php_array_get(%struct.zval* %tmp_66, %struct.zval* %assoc, %struct.zval* %tmp_68)
  %tmp_69 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_70 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_70, i8* %tmp_69)
  %tmp_71 = alloca %struct.zval
  %tmp_72 = call i8* @php_zval_to_string(%struct.zval* %tmp_66)
  %tmp_73 = call i8* @php_zval_to_string(%struct.zval* %tmp_70)
  %tmp_74 = call i8* @php_concat_strings(i8* %tmp_72, i8* %tmp_73)
  call void @php_zval_string(%struct.zval* %tmp_71, i8* %tmp_74)
  call void @php_echo_zval(%struct.zval* %tmp_71)

  %mixed = alloca %struct.zval
  %tmp_75 = alloca %struct.zval
  call void @php_array_create(%struct.zval* %tmp_75, i32 3)
  %tmp_76 = getelementptr inbounds [4 x i8], [4 x i8]* @__str_const_d02c4c4cde7ae76252540d116a40f23a, i64 0, i64 0
  %tmp_77 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_77, i8* %tmp_76)
  call void @php_array_append(%struct.zval* %tmp_75, %struct.zval* %tmp_77)
  %tmp_78 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_78, i32 1)
  %tmp_79 = getelementptr inbounds [3 x i8], [3 x i8]* @__str_const_f97c5d29941bfb1b2fdab0874906ab82, i64 0, i64 0
  call void @php_array_set(%struct.zval* %tmp_75, i8* %tmp_79, %struct.zval* %tmp_78)
  %tmp_80 = getelementptr inbounds [3 x i8], [3 x i8]* @__str_const_b8a9f715dbb64fd5c56e7783c6820a61, i64 0, i64 0
  %tmp_81 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_81, i8* %tmp_80)
  call void @php_array_append(%struct.zval* %tmp_75, %struct.zval* %tmp_81)
  %tmp_82 = load %struct.zval, %struct.zval* %tmp_75
  store %struct.zval %tmp_82, %struct.zval* %mixed
  %tmp_83 = alloca %struct.zval
  %tmp_84 = load %struct.zval, %struct.zval* %mixed
  store %struct.zval %tmp_84, %struct.zval* %tmp_83
  %tmp_85 = getelementptr inbounds [13 x i8], [13 x i8]* @__str_const_8c1013e9e5b4e88ce3dbdb44d0151dc4, i64 0, i64 0
  %tmp_86 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_86, i8* %tmp_85)
  call void @php_echo_zval(%struct.zval* %tmp_86)

  %tmp_87 = alloca %struct.zval
  %tmp_88 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_88, i32 0)
  call void @php_array_get(%struct.zval* %tmp_87, %struct.zval* %mixed, %struct.zval* %tmp_88)
  %tmp_89 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_90 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_90, i8* %tmp_89)
  %tmp_91 = alloca %struct.zval
  %tmp_92 = call i8* @php_zval_to_string(%struct.zval* %tmp_87)
  %tmp_93 = call i8* @php_zval_to_string(%struct.zval* %tmp_90)
  %tmp_94 = call i8* @php_concat_strings(i8* %tmp_92, i8* %tmp_93)
  call void @php_zval_string(%struct.zval* %tmp_91, i8* %tmp_94)
  call void @php_echo_zval(%struct.zval* %tmp_91)

  %tmp_95 = alloca %struct.zval
  %tmp_96 = getelementptr inbounds [3 x i8], [3 x i8]* @__str_const_f97c5d29941bfb1b2fdab0874906ab82, i64 0, i64 0
  %tmp_97 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_97, i8* %tmp_96)
  call void @php_array_get(%struct.zval* %tmp_95, %struct.zval* %mixed, %struct.zval* %tmp_97)
  %tmp_98 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_99 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_99, i8* %tmp_98)
  %tmp_100 = alloca %struct.zval
  %tmp_101 = call i8* @php_zval_to_string(%struct.zval* %tmp_95)
  %tmp_102 = call i8* @php_zval_to_string(%struct.zval* %tmp_99)
  %tmp_103 = call i8* @php_concat_strings(i8* %tmp_101, i8* %tmp_102)
  call void @php_zval_string(%struct.zval* %tmp_100, i8* %tmp_103)
  call void @php_echo_zval(%struct.zval* %tmp_100)

  %tmp_104 = alloca %struct.zval
  %tmp_105 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_105, i32 2)
  call void @php_array_get(%struct.zval* %tmp_104, %struct.zval* %mixed, %struct.zval* %tmp_105)
  %tmp_106 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_107 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_107, i8* %tmp_106)
  %tmp_108 = alloca %struct.zval
  %tmp_109 = call i8* @php_zval_to_string(%struct.zval* %tmp_104)
  %tmp_110 = call i8* @php_zval_to_string(%struct.zval* %tmp_107)
  %tmp_111 = call i8* @php_concat_strings(i8* %tmp_109, i8* %tmp_110)
  call void @php_zval_string(%struct.zval* %tmp_108, i8* %tmp_111)
  call void @php_echo_zval(%struct.zval* %tmp_108)

  %tmp_112 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_112, i32 42)
  %tmp_113 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_113, i32 1)
  %tmp_114 = call i32 @php_zval_to_int(%struct.zval* %tmp_113)
  call void @php_array_set_by_index(%struct.zval* %nums, i32 %tmp_114, %struct.zval* %tmp_112)

  %tmp_115 = getelementptr inbounds [23 x i8], [23 x i8]* @__str_const_6a97c1c166bf06f79b96fd214b14b47a, i64 0, i64 0
  %tmp_116 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_116, i8* %tmp_115)
  call void @php_echo_zval(%struct.zval* %tmp_116)

  %tmp_117 = alloca %struct.zval
  %tmp_118 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_118, i32 0)
  call void @php_array_get(%struct.zval* %tmp_117, %struct.zval* %nums, %struct.zval* %tmp_118)
  %tmp_119 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_120 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_120, i8* %tmp_119)
  %tmp_121 = alloca %struct.zval
  %tmp_122 = call i8* @php_zval_to_string(%struct.zval* %tmp_117)
  %tmp_123 = call i8* @php_zval_to_string(%struct.zval* %tmp_120)
  %tmp_124 = call i8* @php_concat_strings(i8* %tmp_122, i8* %tmp_123)
  call void @php_zval_string(%struct.zval* %tmp_121, i8* %tmp_124)
  call void @php_echo_zval(%struct.zval* %tmp_121)

  %tmp_125 = alloca %struct.zval
  %tmp_126 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_126, i32 1)
  call void @php_array_get(%struct.zval* %tmp_125, %struct.zval* %nums, %struct.zval* %tmp_126)
  %tmp_127 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_128 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_128, i8* %tmp_127)
  %tmp_129 = alloca %struct.zval
  %tmp_130 = call i8* @php_zval_to_string(%struct.zval* %tmp_125)
  %tmp_131 = call i8* @php_zval_to_string(%struct.zval* %tmp_128)
  %tmp_132 = call i8* @php_concat_strings(i8* %tmp_130, i8* %tmp_131)
  call void @php_zval_string(%struct.zval* %tmp_129, i8* %tmp_132)
  call void @php_echo_zval(%struct.zval* %tmp_129)

  %tmp_133 = alloca %struct.zval
  %tmp_134 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_134, i32 2)
  call void @php_array_get(%struct.zval* %tmp_133, %struct.zval* %nums, %struct.zval* %tmp_134)
  %tmp_135 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_136 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_136, i8* %tmp_135)
  %tmp_137 = alloca %struct.zval
  %tmp_138 = call i8* @php_zval_to_string(%struct.zval* %tmp_133)
  %tmp_139 = call i8* @php_zval_to_string(%struct.zval* %tmp_136)
  %tmp_140 = call i8* @php_concat_strings(i8* %tmp_138, i8* %tmp_139)
  call void @php_zval_string(%struct.zval* %tmp_137, i8* %tmp_140)
  call void @php_echo_zval(%struct.zval* %tmp_137)

  %tmp_141 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_141, i32 99)
  %tmp_142 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_92eb5ffee6ae2fec3ad71c777531578f, i64 0, i64 0
  call void @php_array_set(%struct.zval* %assoc, i8* %tmp_142, %struct.zval* %tmp_141)

  %tmp_143 = getelementptr inbounds [27 x i8], [27 x i8]* @__str_const_ac0adc6ee2133eaf8c58c227a26b3ae4, i64 0, i64 0
  %tmp_144 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_144, i8* %tmp_143)
  call void @php_echo_zval(%struct.zval* %tmp_144)

  %tmp_145 = alloca %struct.zval
  %tmp_146 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_0cc175b9c0f1b6a831c399e269772661, i64 0, i64 0
  %tmp_147 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_147, i8* %tmp_146)
  call void @php_array_get(%struct.zval* %tmp_145, %struct.zval* %assoc, %struct.zval* %tmp_147)
  %tmp_148 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_149 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_149, i8* %tmp_148)
  %tmp_150 = alloca %struct.zval
  %tmp_151 = call i8* @php_zval_to_string(%struct.zval* %tmp_145)
  %tmp_152 = call i8* @php_zval_to_string(%struct.zval* %tmp_149)
  %tmp_153 = call i8* @php_concat_strings(i8* %tmp_151, i8* %tmp_152)
  call void @php_zval_string(%struct.zval* %tmp_150, i8* %tmp_153)
  call void @php_echo_zval(%struct.zval* %tmp_150)

  %tmp_154 = alloca %struct.zval
  %tmp_155 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_92eb5ffee6ae2fec3ad71c777531578f, i64 0, i64 0
  %tmp_156 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_156, i8* %tmp_155)
  call void @php_array_get(%struct.zval* %tmp_154, %struct.zval* %assoc, %struct.zval* %tmp_156)
  %tmp_157 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_158 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_158, i8* %tmp_157)
  %tmp_159 = alloca %struct.zval
  %tmp_160 = call i8* @php_zval_to_string(%struct.zval* %tmp_154)
  %tmp_161 = call i8* @php_zval_to_string(%struct.zval* %tmp_158)
  %tmp_162 = call i8* @php_concat_strings(i8* %tmp_160, i8* %tmp_161)
  call void @php_zval_string(%struct.zval* %tmp_159, i8* %tmp_162)
  call void @php_echo_zval(%struct.zval* %tmp_159)

  %tmp_163 = alloca %struct.zval
  %tmp_164 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_4a8a08f09d37b73795649038408b5f33, i64 0, i64 0
  %tmp_165 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_165, i8* %tmp_164)
  call void @php_array_get(%struct.zval* %tmp_163, %struct.zval* %assoc, %struct.zval* %tmp_165)
  %tmp_166 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_167 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_167, i8* %tmp_166)
  %tmp_168 = alloca %struct.zval
  %tmp_169 = call i8* @php_zval_to_string(%struct.zval* %tmp_163)
  %tmp_170 = call i8* @php_zval_to_string(%struct.zval* %tmp_167)
  %tmp_171 = call i8* @php_concat_strings(i8* %tmp_169, i8* %tmp_170)
  call void @php_zval_string(%struct.zval* %tmp_168, i8* %tmp_171)
  call void @php_echo_zval(%struct.zval* %tmp_168)

  %tmp_172 = getelementptr inbounds [25 x i8], [25 x i8]* @__str_const_93a2c6f9f4ef533db1f4cbd34d16f8cd, i64 0, i64 0
  %tmp_173 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_173, i8* %tmp_172)
  call void @php_echo_zval(%struct.zval* %tmp_173)

  %foreach_idx_1 = alloca i32
  store i32 0, i32* %foreach_idx_1
  %tmp_174 = call i32 @php_array_size(%struct.zval* %nums)
  %num = alloca %struct.zval
  br label %foreach_header_1
foreach_header_1:
  %tmp_175 = load i32, i32* %foreach_idx_1
  %tmp_176 = icmp slt i32 %tmp_175, %tmp_174
  br i1 %tmp_176, label %foreach_body_1, label %foreach_after_1
foreach_body_1:
  %tmp_177 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_177, i32 %tmp_175)
  %tmp_178 = alloca %struct.zval
  call void @php_array_get(%struct.zval* %tmp_178, %struct.zval* %nums, %struct.zval* %tmp_177)
  %tmp_179 = load %struct.zval, %struct.zval* %tmp_178
  store %struct.zval %tmp_179, %struct.zval* %num
  %tmp_180 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_181 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_181, i8* %tmp_180)
  %tmp_182 = alloca %struct.zval
  %tmp_183 = call i8* @php_zval_to_string(%struct.zval* %num)
  %tmp_184 = call i8* @php_zval_to_string(%struct.zval* %tmp_181)
  %tmp_185 = call i8* @php_concat_strings(i8* %tmp_183, i8* %tmp_184)
  call void @php_zval_string(%struct.zval* %tmp_182, i8* %tmp_185)
  call void @php_echo_zval(%struct.zval* %tmp_182)

  %tmp_186 = add i32 %tmp_175, 1
  store i32 %tmp_186, i32* %foreach_idx_1
  br label %foreach_header_1
foreach_after_1:

  %tmp_187 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_188 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_188, i8* %tmp_187)
  call void @php_echo_zval(%struct.zval* %tmp_188)

  %tmp_189 = getelementptr inbounds [29 x i8], [29 x i8]* @__str_const_fea32c352d2025a7adbd13eb20933c18, i64 0, i64 0
  %tmp_190 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_190, i8* %tmp_189)
  call void @php_echo_zval(%struct.zval* %tmp_190)

  %foreach_idx_2 = alloca i32
  store i32 0, i32* %foreach_idx_2
  %tmp_191 = call i32 @php_array_size(%struct.zval* %assoc)
  %val = alloca %struct.zval
  %key = alloca %struct.zval
  br label %foreach_header_2
foreach_header_2:
  %tmp_192 = load i32, i32* %foreach_idx_2
  %tmp_193 = icmp slt i32 %tmp_192, %tmp_191
  br i1 %tmp_193, label %foreach_body_2, label %foreach_after_2
foreach_body_2:
  %tmp_194 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_194, i32 %tmp_192)
  %tmp_195 = alloca %struct.zval
  call void @php_array_get(%struct.zval* %tmp_195, %struct.zval* %assoc, %struct.zval* %tmp_194)
  %tmp_196 = load %struct.zval, %struct.zval* %tmp_195
  store %struct.zval %tmp_196, %struct.zval* %val
  %tmp_197 = call i8* @php_array_get_key(%struct.zval* %assoc, i32 %tmp_192)
  %tmp_198 = icmp eq i8* %tmp_197, null
  br i1 %tmp_198, label %foreach_key_null_2, label %foreach_key_str_2
foreach_key_null_2:
  %tmp_199 = call i8* @php_itoa(i32 %tmp_192)
  call void @php_zval_string(%struct.zval* %key, i8* %tmp_199)
  br label %foreach_key_done_2
foreach_key_str_2:
  call void @php_zval_string(%struct.zval* %key, i8* %tmp_197)
  br label %foreach_key_done_2
foreach_key_done_2:
  %tmp_200 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_43ec3e5dee6e706af7766fffea512721, i64 0, i64 0
  %tmp_201 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_201, i8* %tmp_200)
  %tmp_202 = alloca %struct.zval
  %tmp_203 = call i8* @php_zval_to_string(%struct.zval* %key)
  %tmp_204 = call i8* @php_zval_to_string(%struct.zval* %tmp_201)
  %tmp_205 = call i8* @php_concat_strings(i8* %tmp_203, i8* %tmp_204)
  call void @php_zval_string(%struct.zval* %tmp_202, i8* %tmp_205)
  %tmp_206 = alloca %struct.zval
  %tmp_207 = call i8* @php_zval_to_string(%struct.zval* %tmp_202)
  %tmp_208 = call i8* @php_zval_to_string(%struct.zval* %val)
  %tmp_209 = call i8* @php_concat_strings(i8* %tmp_207, i8* %tmp_208)
  call void @php_zval_string(%struct.zval* %tmp_206, i8* %tmp_209)
  %tmp_210 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_211 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_211, i8* %tmp_210)
  %tmp_212 = alloca %struct.zval
  %tmp_213 = call i8* @php_zval_to_string(%struct.zval* %tmp_206)
  %tmp_214 = call i8* @php_zval_to_string(%struct.zval* %tmp_211)
  %tmp_215 = call i8* @php_concat_strings(i8* %tmp_213, i8* %tmp_214)
  call void @php_zval_string(%struct.zval* %tmp_212, i8* %tmp_215)
  call void @php_echo_zval(%struct.zval* %tmp_212)

  %tmp_216 = add i32 %tmp_192, 1
  store i32 %tmp_216, i32* %foreach_idx_2
  br label %foreach_header_2
foreach_after_2:

  %tmp_217 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_218 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_218, i8* %tmp_217)
  call void @php_echo_zval(%struct.zval* %tmp_218)

  %empty = alloca %struct.zval
  %tmp_219 = alloca %struct.zval
  call void @php_array_create(%struct.zval* %tmp_219, i32 0)
  %tmp_220 = load %struct.zval, %struct.zval* %tmp_219
  store %struct.zval %tmp_220, %struct.zval* %empty
  %tmp_221 = alloca %struct.zval
  %tmp_222 = load %struct.zval, %struct.zval* %empty
  store %struct.zval %tmp_222, %struct.zval* %tmp_221
  %tmp_223 = getelementptr inbounds [20 x i8], [20 x i8]* @__str_const_d74b9ae2a12865e2a84d0fab2a60c6d0, i64 0, i64 0
  %tmp_224 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_224, i8* %tmp_223)
  %tmp_225 = call i32 @php_array_size(%struct.zval* %empty)
  %tmp_226 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_226, i32 %tmp_225)
  %tmp_227 = alloca %struct.zval
  %tmp_228 = call i8* @php_zval_to_string(%struct.zval* %tmp_224)
  %tmp_229 = call i8* @php_zval_to_string(%struct.zval* %tmp_226)
  %tmp_230 = call i8* @php_concat_strings(i8* %tmp_228, i8* %tmp_229)
  call void @php_zval_string(%struct.zval* %tmp_227, i8* %tmp_230)
  %tmp_231 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_232 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_232, i8* %tmp_231)
  %tmp_233 = alloca %struct.zval
  %tmp_234 = call i8* @php_zval_to_string(%struct.zval* %tmp_227)
  %tmp_235 = call i8* @php_zval_to_string(%struct.zval* %tmp_232)
  %tmp_236 = call i8* @php_concat_strings(i8* %tmp_234, i8* %tmp_235)
  call void @php_zval_string(%struct.zval* %tmp_233, i8* %tmp_236)
  call void @php_echo_zval(%struct.zval* %tmp_233)

  %single = alloca %struct.zval
  %tmp_237 = alloca %struct.zval
  call void @php_array_create(%struct.zval* %tmp_237, i32 1)
  %tmp_238 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_238, i32 42)
  call void @php_array_append(%struct.zval* %tmp_237, %struct.zval* %tmp_238)
  %tmp_239 = load %struct.zval, %struct.zval* %tmp_237
  store %struct.zval %tmp_239, %struct.zval* %single
  %tmp_240 = alloca %struct.zval
  %tmp_241 = load %struct.zval, %struct.zval* %single
  store %struct.zval %tmp_241, %struct.zval* %tmp_240
  %tmp_242 = getelementptr inbounds [22 x i8], [22 x i8]* @__str_const_91c806590758cf507461943dd9bf1485, i64 0, i64 0
  %tmp_243 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_243, i8* %tmp_242)
  %tmp_244 = alloca %struct.zval
  %tmp_245 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_245, i32 0)
  call void @php_array_get(%struct.zval* %tmp_244, %struct.zval* %single, %struct.zval* %tmp_245)
  %tmp_246 = alloca %struct.zval
  %tmp_247 = call i8* @php_zval_to_string(%struct.zval* %tmp_243)
  %tmp_248 = call i8* @php_zval_to_string(%struct.zval* %tmp_244)
  %tmp_249 = call i8* @php_concat_strings(i8* %tmp_247, i8* %tmp_248)
  call void @php_zval_string(%struct.zval* %tmp_246, i8* %tmp_249)
  %tmp_250 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_251 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_251, i8* %tmp_250)
  %tmp_252 = alloca %struct.zval
  %tmp_253 = call i8* @php_zval_to_string(%struct.zval* %tmp_246)
  %tmp_254 = call i8* @php_zval_to_string(%struct.zval* %tmp_251)
  %tmp_255 = call i8* @php_concat_strings(i8* %tmp_253, i8* %tmp_254)
  call void @php_zval_string(%struct.zval* %tmp_252, i8* %tmp_255)
  call void @php_echo_zval(%struct.zval* %tmp_252)

  %nested = alloca %struct.zval
  %tmp_256 = alloca %struct.zval
  call void @php_array_create(%struct.zval* %tmp_256, i32 2)
  %tmp_257 = alloca %struct.zval
  call void @php_array_create(%struct.zval* %tmp_257, i32 3)
  %tmp_258 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_258, i32 1)
  call void @php_array_append(%struct.zval* %tmp_257, %struct.zval* %tmp_258)
  %tmp_259 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_259, i32 2)
  call void @php_array_append(%struct.zval* %tmp_257, %struct.zval* %tmp_259)
  %tmp_260 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_260, i32 3)
  call void @php_array_append(%struct.zval* %tmp_257, %struct.zval* %tmp_260)
  %tmp_261 = getelementptr inbounds [7 x i8], [7 x i8]* @__str_const_ee5c35ce3d081da90622a10d2272ed5e, i64 0, i64 0
  call void @php_array_set(%struct.zval* %tmp_256, i8* %tmp_261, %struct.zval* %tmp_257)
  %tmp_262 = alloca %struct.zval
  call void @php_array_create(%struct.zval* %tmp_262, i32 3)
  %tmp_263 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_0cc175b9c0f1b6a831c399e269772661, i64 0, i64 0
  %tmp_264 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_264, i8* %tmp_263)
  call void @php_array_append(%struct.zval* %tmp_262, %struct.zval* %tmp_264)
  %tmp_265 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_92eb5ffee6ae2fec3ad71c777531578f, i64 0, i64 0
  %tmp_266 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_266, i8* %tmp_265)
  call void @php_array_append(%struct.zval* %tmp_262, %struct.zval* %tmp_266)
  %tmp_267 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_4a8a08f09d37b73795649038408b5f33, i64 0, i64 0
  %tmp_268 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_268, i8* %tmp_267)
  call void @php_array_append(%struct.zval* %tmp_262, %struct.zval* %tmp_268)
  %tmp_269 = getelementptr inbounds [7 x i8], [7 x i8]* @__str_const_9a31962b953d38f5702495fdf5b44ad0, i64 0, i64 0
  call void @php_array_set(%struct.zval* %tmp_256, i8* %tmp_269, %struct.zval* %tmp_262)
  %tmp_270 = load %struct.zval, %struct.zval* %tmp_256
  store %struct.zval %tmp_270, %struct.zval* %nested
  %tmp_271 = alloca %struct.zval
  %tmp_272 = load %struct.zval, %struct.zval* %nested
  store %struct.zval %tmp_272, %struct.zval* %tmp_271
  %tmp_273 = getelementptr inbounds [14 x i8], [14 x i8]* @__str_const_03aaab3ff080774fc465846676e662d3, i64 0, i64 0
  %tmp_274 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_274, i8* %tmp_273)
  call void @php_echo_zval(%struct.zval* %tmp_274)

  %tmp_275 = alloca %struct.zval
  %tmp_276 = getelementptr inbounds [7 x i8], [7 x i8]* @__str_const_ee5c35ce3d081da90622a10d2272ed5e, i64 0, i64 0
  %tmp_277 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_277, i8* %tmp_276)
  call void @php_array_get(%struct.zval* %tmp_275, %struct.zval* %nested, %struct.zval* %tmp_277)
  %tmp_278 = alloca %struct.zval
  %tmp_279 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_279, i32 0)
  call void @php_array_get(%struct.zval* %tmp_278, %struct.zval* %tmp_275, %struct.zval* %tmp_279)
  %tmp_280 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_281 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_281, i8* %tmp_280)
  %tmp_282 = alloca %struct.zval
  %tmp_283 = call i8* @php_zval_to_string(%struct.zval* %tmp_278)
  %tmp_284 = call i8* @php_zval_to_string(%struct.zval* %tmp_281)
  %tmp_285 = call i8* @php_concat_strings(i8* %tmp_283, i8* %tmp_284)
  call void @php_zval_string(%struct.zval* %tmp_282, i8* %tmp_285)
  call void @php_echo_zval(%struct.zval* %tmp_282)

  %tmp_286 = alloca %struct.zval
  %tmp_287 = getelementptr inbounds [7 x i8], [7 x i8]* @__str_const_9a31962b953d38f5702495fdf5b44ad0, i64 0, i64 0
  %tmp_288 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_288, i8* %tmp_287)
  call void @php_array_get(%struct.zval* %tmp_286, %struct.zval* %nested, %struct.zval* %tmp_288)
  %tmp_289 = alloca %struct.zval
  %tmp_290 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_290, i32 2)
  call void @php_array_get(%struct.zval* %tmp_289, %struct.zval* %tmp_286, %struct.zval* %tmp_290)
  %tmp_291 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_292 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_292, i8* %tmp_291)
  %tmp_293 = alloca %struct.zval
  %tmp_294 = call i8* @php_zval_to_string(%struct.zval* %tmp_289)
  %tmp_295 = call i8* @php_zval_to_string(%struct.zval* %tmp_292)
  %tmp_296 = call i8* @php_concat_strings(i8* %tmp_294, i8* %tmp_295)
  call void @php_zval_string(%struct.zval* %tmp_293, i8* %tmp_296)
  call void @php_echo_zval(%struct.zval* %tmp_293)

  ret i32 0
}
