<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDummyNicknameTest extends TestCase
{
    public function test_only_an_admin_sees_dummy_nicknames_highlighted(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('This test requires pdo_sqlite.');
        }

        DB::statement('CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, nickname TEXT, is_dummy INTEGER)');
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'dummy001', 'nickname' => 'DummyFan', 'is_dummy' => 1],
            ['idx' => 2, 'member_code' => 'member002', 'nickname' => 'RegularFan', 'is_dummy' => 0],
        ]);

        $template = '<x-member-link member-code="dummy001">DummyFan</x-member-link> <x-member-link member-code="member002">RegularFan</x-member-link> <x-admin-dummy-name nickname="DummyFan" />';
        $this->assertSame(0, substr_count(Blade::render($template), 'admin-dummy-nickname'));

        request()->attributes->set('currentMember', (object) ['member_level' => 1, 'is_dummy' => 0]);
        $this->assertSame(0, substr_count(Blade::render($template), 'admin-dummy-nickname'));

        request()->attributes->set('currentMember', (object) ['member_level' => 10, 'is_dummy' => 0]);
        $this->assertSame(2, substr_count(Blade::render($template), 'admin-dummy-nickname'));
    }
}
