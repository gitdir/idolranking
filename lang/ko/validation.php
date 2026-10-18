<?php

return [
    'required' => ':attribute 항목은 필수입니다.',
    'email' => '유효한 이메일 주소를 입력해 주세요.',
    'integer' => ':attribute 항목은 정수여야 합니다.',
    'min' => [
        'array' => ':attribute 항목은 최소 :min개여야 합니다.',
        'file' => ':attribute 항목은 최소 :min KB여야 합니다.',
        'numeric' => ':attribute 항목은 :min 이상이어야 합니다.',
        'string' => ':attribute 항목은 최소 :min자여야 합니다.',
    ],
    'max' => [
        'array' => ':attribute 항목은 :max개를 초과할 수 없습니다.',
        'file' => ':attribute 항목은 :max KB를 초과할 수 없습니다.',
        'numeric' => ':attribute 항목은 :max을 초과할 수 없습니다.',
        'string' => ':attribute 항목은 :max자를 초과할 수 없습니다.',
    ],
    'confirmed' => ':attribute 확인 값이 일치하지 않습니다.',
    'unique' => '이미 사용 중인 :attribute입니다.',
    'in' => '선택한 :attribute 값이 올바르지 않습니다.',
    'image' => ':attribute 항목은 이미지 파일이어야 합니다.',
    'mimes' => ':attribute 항목은 :values 형식의 파일이어야 합니다.',
    'attributes' => [
        'email' => '이메일', 'password' => '비밀번호', 'password_confirmation' => '비밀번호 확인',
        'nickname' => '닉네임', 'country_code' => '국가', 'profile_image' => '프로필 이미지',
        'heart_amount' => '하트 수', 'comment' => '댓글', 'title' => '제목',
    ],
];
