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
@__str_const_5c33b020ee12c4b052015a39d09b3434 = private unnamed_addr constant [24 x i8] c"=== For Loop Tests ===
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
@__str_const_02d44d61144be3a8d725b9feb58385ea = private unnamed_addr constant [9 x i8] c"Step 2: \00"

; Global string constant
@__str_const_4a76e49d9f66c974187767912a5a71da = private unnamed_addr constant [16 x i8] c"Negative step: \00"

; Global string constant
@__str_const_e8e504299586202bb03508ca9e01a9ac = private unnamed_addr constant [18 x i8] c"Empty loop body: \00"

; Global string constant
@__str_const_678e5e019a79526d0fcca5e29f6e5f78 = private unnamed_addr constant [6 x i8] c"done
\00"

; Global string constant
@__str_const_cf1a554b87d56785c533b5a4acc9993f = private unnamed_addr constant [16 x i8] c"Multiple vars: \00"

; Global string constant
@__str_const_f70c69d0df098c41957ab296d9c91f87 = private unnamed_addr constant [3 x i8] c"i=\00"

; Global string constant
@__str_const_e02537aa69a4c4094c57b8e8c94d4c3e = private unnamed_addr constant [4 x i8] c",j=\00"

define i32 @main() {
entry:
  %tmp_1 = getelementptr inbounds [23 x i8], [23 x i8]* @__str_const_5c33b020ee12c4b052015a39d09b3434, i64 0, i64 0
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
  br label %loop_header_1
loop_header_1:
  %tmp_7 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_7, i32 5)
  %tmp_8 = alloca %struct.zval
  %tmp_9 = call i32 @php_zval_to_int(%struct.zval* %i)
  %tmp_10 = call i32 @php_zval_to_int(%struct.zval* %tmp_7)
  %tmp_11 = icmp slt i32 %tmp_9, %tmp_10
  %tmp_12 = zext i1 %tmp_11 to i32
  call void @php_zval_bool(%struct.zval* %tmp_8, i32 %tmp_12)
  %tmp_13 = call i32 @php_zval_to_int(%struct.zval* %tmp_8)
  %tmp_14 = icmp eq i32 %tmp_13, 0
  br i1 %tmp_14, label %loop_after_1, label %loop_body_1
loop_body_1:
  %tmp_15 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_16 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_16, i8* %tmp_15)
  %tmp_17 = alloca %struct.zval
  %tmp_18 = call i8* @php_zval_to_string(%struct.zval* %i)
  %tmp_19 = call i8* @php_zval_to_string(%struct.zval* %tmp_16)
  %tmp_20 = call i8* @php_concat_strings(i8* %tmp_18, i8* %tmp_19)
  call void @php_zval_string(%struct.zval* %tmp_17, i8* %tmp_20)
  call void @php_echo_zval(%struct.zval* %tmp_17)

  %tmp_21 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_21, i32 1)
  %tmp_22 = call i32 @php_zval_to_int(%struct.zval* %i)
  %tmp_23 = call i32 @php_zval_to_int(%struct.zval* %tmp_21)
  %tmp_24 = add i32 %tmp_22, %tmp_23
  call void @php_zval_int(%struct.zval* %i, i32 %tmp_24)
  %tmp_25 = alloca %struct.zval
  %tmp_26 = load %struct.zval, %struct.zval* %i
  store %struct.zval %tmp_26, %struct.zval* %tmp_25
  br label %loop_header_1
loop_after_1:

  %tmp_27 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_28 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_28, i8* %tmp_27)
  call void @php_echo_zval(%struct.zval* %tmp_28)

  %tmp_29 = getelementptr inbounds [17 x i8], [17 x i8]* @__str_const_d6f285cf0457cc09a7b732f30c5e864a, i64 0, i64 0
  %tmp_30 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_30, i8* %tmp_29)
  call void @php_echo_zval(%struct.zval* %tmp_30)

  %j = alloca %struct.zval
  %tmp_31 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_31, i32 5)
  %tmp_32 = load %struct.zval, %struct.zval* %tmp_31
  store %struct.zval %tmp_32, %struct.zval* %j
  br label %loop_header_2
loop_header_2:
  %tmp_33 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_33, i32 0)
  %tmp_34 = alloca %struct.zval
  %tmp_35 = call i32 @php_zval_to_int(%struct.zval* %j)
  %tmp_36 = call i32 @php_zval_to_int(%struct.zval* %tmp_33)
  %tmp_37 = icmp sgt i32 %tmp_35, %tmp_36
  %tmp_38 = zext i1 %tmp_37 to i32
  call void @php_zval_bool(%struct.zval* %tmp_34, i32 %tmp_38)
  %tmp_39 = call i32 @php_zval_to_int(%struct.zval* %tmp_34)
  %tmp_40 = icmp eq i32 %tmp_39, 0
  br i1 %tmp_40, label %loop_after_2, label %loop_body_2
loop_body_2:
  %tmp_41 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_42 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_42, i8* %tmp_41)
  %tmp_43 = alloca %struct.zval
  %tmp_44 = call i8* @php_zval_to_string(%struct.zval* %j)
  %tmp_45 = call i8* @php_zval_to_string(%struct.zval* %tmp_42)
  %tmp_46 = call i8* @php_concat_strings(i8* %tmp_44, i8* %tmp_45)
  call void @php_zval_string(%struct.zval* %tmp_43, i8* %tmp_46)
  call void @php_echo_zval(%struct.zval* %tmp_43)

  %tmp_47 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_47, i32 1)
  %tmp_48 = call i32 @php_zval_to_int(%struct.zval* %j)
  %tmp_49 = call i32 @php_zval_to_int(%struct.zval* %tmp_47)
  %tmp_50 = sub i32 %tmp_48, %tmp_49
  call void @php_zval_int(%struct.zval* %j, i32 %tmp_50)
  %tmp_51 = alloca %struct.zval
  %tmp_52 = load %struct.zval, %struct.zval* %j
  store %struct.zval %tmp_52, %struct.zval* %tmp_51
  br label %loop_header_2
loop_after_2:

  %tmp_53 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_54 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_54, i8* %tmp_53)
  call void @php_echo_zval(%struct.zval* %tmp_54)

  %tmp_55 = getelementptr inbounds [8 x i8], [8 x i8]* @__str_const_02d44d61144be3a8d725b9feb58385ea, i64 0, i64 0
  %tmp_56 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_56, i8* %tmp_55)
  call void @php_echo_zval(%struct.zval* %tmp_56)

  %k = alloca %struct.zval
  %tmp_57 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_57, i32 0)
  %tmp_58 = load %struct.zval, %struct.zval* %tmp_57
  store %struct.zval %tmp_58, %struct.zval* %k
  br label %loop_header_3
loop_header_3:
  %tmp_59 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_59, i32 10)
  %tmp_60 = alloca %struct.zval
  %tmp_61 = call i32 @php_zval_to_int(%struct.zval* %k)
  %tmp_62 = call i32 @php_zval_to_int(%struct.zval* %tmp_59)
  %tmp_63 = icmp sle i32 %tmp_61, %tmp_62
  %tmp_64 = zext i1 %tmp_63 to i32
  call void @php_zval_bool(%struct.zval* %tmp_60, i32 %tmp_64)
  %tmp_65 = call i32 @php_zval_to_int(%struct.zval* %tmp_60)
  %tmp_66 = icmp eq i32 %tmp_65, 0
  br i1 %tmp_66, label %loop_after_3, label %loop_body_3
loop_body_3:
  %tmp_67 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_68 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_68, i8* %tmp_67)
  %tmp_69 = alloca %struct.zval
  %tmp_70 = call i8* @php_zval_to_string(%struct.zval* %k)
  %tmp_71 = call i8* @php_zval_to_string(%struct.zval* %tmp_68)
  %tmp_72 = call i8* @php_concat_strings(i8* %tmp_70, i8* %tmp_71)
  call void @php_zval_string(%struct.zval* %tmp_69, i8* %tmp_72)
  call void @php_echo_zval(%struct.zval* %tmp_69)

  %tmp_73 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_73, i32 2)
  %tmp_74 = call i32 @php_zval_to_int(%struct.zval* %k)
  %tmp_75 = call i32 @php_zval_to_int(%struct.zval* %tmp_73)
  %tmp_76 = add i32 %tmp_74, %tmp_75
  call void @php_zval_int(%struct.zval* %k, i32 %tmp_76)
  %tmp_77 = alloca %struct.zval
  %tmp_78 = load %struct.zval, %struct.zval* %k
  store %struct.zval %tmp_78, %struct.zval* %tmp_77
  br label %loop_header_3
loop_after_3:

  %tmp_79 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_80 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_80, i8* %tmp_79)
  call void @php_echo_zval(%struct.zval* %tmp_80)

  %tmp_81 = getelementptr inbounds [15 x i8], [15 x i8]* @__str_const_4a76e49d9f66c974187767912a5a71da, i64 0, i64 0
  %tmp_82 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_82, i8* %tmp_81)
  call void @php_echo_zval(%struct.zval* %tmp_82)

  %l = alloca %struct.zval
  %tmp_83 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_83, i32 10)
  %tmp_84 = load %struct.zval, %struct.zval* %tmp_83
  store %struct.zval %tmp_84, %struct.zval* %l
  br label %loop_header_4
loop_header_4:
  %tmp_85 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_85, i32 0)
  %tmp_86 = alloca %struct.zval
  %tmp_87 = call i32 @php_zval_to_int(%struct.zval* %l)
  %tmp_88 = call i32 @php_zval_to_int(%struct.zval* %tmp_85)
  %tmp_89 = icmp sgt i32 %tmp_87, %tmp_88
  %tmp_90 = zext i1 %tmp_89 to i32
  call void @php_zval_bool(%struct.zval* %tmp_86, i32 %tmp_90)
  %tmp_91 = call i32 @php_zval_to_int(%struct.zval* %tmp_86)
  %tmp_92 = icmp eq i32 %tmp_91, 0
  br i1 %tmp_92, label %loop_after_4, label %loop_body_4
loop_body_4:
  %tmp_93 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_94 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_94, i8* %tmp_93)
  %tmp_95 = alloca %struct.zval
  %tmp_96 = call i8* @php_zval_to_string(%struct.zval* %l)
  %tmp_97 = call i8* @php_zval_to_string(%struct.zval* %tmp_94)
  %tmp_98 = call i8* @php_concat_strings(i8* %tmp_96, i8* %tmp_97)
  call void @php_zval_string(%struct.zval* %tmp_95, i8* %tmp_98)
  call void @php_echo_zval(%struct.zval* %tmp_95)

  %tmp_99 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_99, i32 3)
  %tmp_100 = call i32 @php_zval_to_int(%struct.zval* %l)
  %tmp_101 = call i32 @php_zval_to_int(%struct.zval* %tmp_99)
  %tmp_102 = sub i32 %tmp_100, %tmp_101
  call void @php_zval_int(%struct.zval* %l, i32 %tmp_102)
  %tmp_103 = alloca %struct.zval
  %tmp_104 = load %struct.zval, %struct.zval* %l
  store %struct.zval %tmp_104, %struct.zval* %tmp_103
  br label %loop_header_4
loop_after_4:

  %tmp_105 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_106 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_106, i8* %tmp_105)
  call void @php_echo_zval(%struct.zval* %tmp_106)

  %tmp_107 = getelementptr inbounds [17 x i8], [17 x i8]* @__str_const_e8e504299586202bb03508ca9e01a9ac, i64 0, i64 0
  %tmp_108 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_108, i8* %tmp_107)
  call void @php_echo_zval(%struct.zval* %tmp_108)

  %m = alloca %struct.zval
  %tmp_109 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_109, i32 0)
  %tmp_110 = load %struct.zval, %struct.zval* %tmp_109
  store %struct.zval %tmp_110, %struct.zval* %m
  br label %loop_header_5
loop_header_5:
  %tmp_111 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_111, i32 3)
  %tmp_112 = alloca %struct.zval
  %tmp_113 = call i32 @php_zval_to_int(%struct.zval* %m)
  %tmp_114 = call i32 @php_zval_to_int(%struct.zval* %tmp_111)
  %tmp_115 = icmp slt i32 %tmp_113, %tmp_114
  %tmp_116 = zext i1 %tmp_115 to i32
  call void @php_zval_bool(%struct.zval* %tmp_112, i32 %tmp_116)
  %tmp_117 = call i32 @php_zval_to_int(%struct.zval* %tmp_112)
  %tmp_118 = icmp eq i32 %tmp_117, 0
  br i1 %tmp_118, label %loop_after_5, label %loop_body_5
loop_body_5:
  %tmp_119 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_119, i32 1)
  %tmp_120 = call i32 @php_zval_to_int(%struct.zval* %m)
  %tmp_121 = call i32 @php_zval_to_int(%struct.zval* %tmp_119)
  %tmp_122 = add i32 %tmp_120, %tmp_121
  call void @php_zval_int(%struct.zval* %m, i32 %tmp_122)
  %tmp_123 = alloca %struct.zval
  %tmp_124 = load %struct.zval, %struct.zval* %m
  store %struct.zval %tmp_124, %struct.zval* %tmp_123
  br label %loop_header_5
loop_after_5:

  %tmp_125 = getelementptr inbounds [5 x i8], [5 x i8]* @__str_const_678e5e019a79526d0fcca5e29f6e5f78, i64 0, i64 0
  %tmp_126 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_126, i8* %tmp_125)
  call void @php_echo_zval(%struct.zval* %tmp_126)

  %tmp_127 = getelementptr inbounds [15 x i8], [15 x i8]* @__str_const_cf1a554b87d56785c533b5a4acc9993f, i64 0, i64 0
  %tmp_128 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_128, i8* %tmp_127)
  call void @php_echo_zval(%struct.zval* %tmp_128)

  %n = alloca %struct.zval
  %o = alloca %struct.zval
  %tmp_129 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_129, i32 0)
  %tmp_130 = load %struct.zval, %struct.zval* %tmp_129
  store %struct.zval %tmp_130, %struct.zval* %n
  %tmp_131 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_131, i32 10)
  %tmp_132 = load %struct.zval, %struct.zval* %tmp_131
  store %struct.zval %tmp_132, %struct.zval* %o
  br label %loop_header_6
loop_header_6:
  %tmp_133 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_133, i32 5)
  %tmp_134 = alloca %struct.zval
  %tmp_135 = call i32 @php_zval_to_int(%struct.zval* %n)
  %tmp_136 = call i32 @php_zval_to_int(%struct.zval* %tmp_133)
  %tmp_137 = icmp slt i32 %tmp_135, %tmp_136
  %tmp_138 = zext i1 %tmp_137 to i32
  call void @php_zval_bool(%struct.zval* %tmp_134, i32 %tmp_138)
  %tmp_139 = call i32 @php_zval_to_int(%struct.zval* %tmp_134)
  %tmp_140 = icmp eq i32 %tmp_139, 0
  br i1 %tmp_140, label %loop_after_6, label %loop_body_6
loop_body_6:
  %tmp_141 = getelementptr inbounds [2 x i8], [2 x i8]* @__str_const_f70c69d0df098c41957ab296d9c91f87, i64 0, i64 0
  %tmp_142 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_142, i8* %tmp_141)
  %tmp_143 = alloca %struct.zval
  %tmp_144 = call i8* @php_zval_to_string(%struct.zval* %tmp_142)
  %tmp_145 = call i8* @php_zval_to_string(%struct.zval* %n)
  %tmp_146 = call i8* @php_concat_strings(i8* %tmp_144, i8* %tmp_145)
  call void @php_zval_string(%struct.zval* %tmp_143, i8* %tmp_146)
  %tmp_147 = getelementptr inbounds [3 x i8], [3 x i8]* @__str_const_e02537aa69a4c4094c57b8e8c94d4c3e, i64 0, i64 0
  %tmp_148 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_148, i8* %tmp_147)
  %tmp_149 = alloca %struct.zval
  %tmp_150 = call i8* @php_zval_to_string(%struct.zval* %tmp_143)
  %tmp_151 = call i8* @php_zval_to_string(%struct.zval* %tmp_148)
  %tmp_152 = call i8* @php_concat_strings(i8* %tmp_150, i8* %tmp_151)
  call void @php_zval_string(%struct.zval* %tmp_149, i8* %tmp_152)
  %tmp_153 = alloca %struct.zval
  %tmp_154 = call i8* @php_zval_to_string(%struct.zval* %tmp_149)
  %tmp_155 = call i8* @php_zval_to_string(%struct.zval* %o)
  %tmp_156 = call i8* @php_concat_strings(i8* %tmp_154, i8* %tmp_155)
  call void @php_zval_string(%struct.zval* %tmp_153, i8* %tmp_156)
  %tmp_157 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_7215ee9c7d9dc229d2921a40e899ec5f, i64 0, i64 0
  %tmp_158 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_158, i8* %tmp_157)
  %tmp_159 = alloca %struct.zval
  %tmp_160 = call i8* @php_zval_to_string(%struct.zval* %tmp_153)
  %tmp_161 = call i8* @php_zval_to_string(%struct.zval* %tmp_158)
  %tmp_162 = call i8* @php_concat_strings(i8* %tmp_160, i8* %tmp_161)
  call void @php_zval_string(%struct.zval* %tmp_159, i8* %tmp_162)
  call void @php_echo_zval(%struct.zval* %tmp_159)

  %tmp_163 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_163, i32 1)
  %tmp_164 = call i32 @php_zval_to_int(%struct.zval* %n)
  %tmp_165 = call i32 @php_zval_to_int(%struct.zval* %tmp_163)
  %tmp_166 = add i32 %tmp_164, %tmp_165
  call void @php_zval_int(%struct.zval* %n, i32 %tmp_166)
  %tmp_167 = alloca %struct.zval
  %tmp_168 = load %struct.zval, %struct.zval* %n
  store %struct.zval %tmp_168, %struct.zval* %tmp_167
  %tmp_169 = alloca %struct.zval
  call void @php_zval_int(%struct.zval* %tmp_169, i32 2)
  %tmp_170 = call i32 @php_zval_to_int(%struct.zval* %o)
  %tmp_171 = call i32 @php_zval_to_int(%struct.zval* %tmp_169)
  %tmp_172 = sub i32 %tmp_170, %tmp_171
  call void @php_zval_int(%struct.zval* %o, i32 %tmp_172)
  %tmp_173 = alloca %struct.zval
  %tmp_174 = load %struct.zval, %struct.zval* %o
  store %struct.zval %tmp_174, %struct.zval* %tmp_173
  br label %loop_header_6
loop_after_6:

  %tmp_175 = getelementptr inbounds [1 x i8], [1 x i8]* @__str_const_68b329da9893e34099c7d8ad5cb9c940, i64 0, i64 0
  %tmp_176 = alloca %struct.zval
  call void @php_zval_string(%struct.zval* %tmp_176, i8* %tmp_175)
  call void @php_echo_zval(%struct.zval* %tmp_176)

  ret i32 0
}
