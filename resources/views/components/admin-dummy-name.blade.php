@props(['nickname'])
<span translate="no" @class(['admin-dummy-nickname' => \App\Support\AdminDummyNickname::matches(nickname: $nickname)])>{{ $nickname }}</span>
