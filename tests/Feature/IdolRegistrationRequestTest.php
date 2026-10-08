<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdolRegistrationRequestTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }
        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, name TEXT, type TEXT, gender TEXT, status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE heart_reward_settings (action_code TEXT PRIMARY KEY, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)");
        DB::statement("CREATE TABLE idol_registration_requests (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, actor_member_idx INTEGER, type TEXT, gender TEXT, name TEXT, english_name TEXT, group_idx INTEGER, birth_date TEXT, nationality TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, agency TEXT, debut_date TEXT, position TEXT, sns TEXT, image_path TEXT, content TEXT, reference_url TEXT, note TEXT, request_key TEXT, status TEXT, created_at TEXT, updated_at TEXT)");
        DB::table('members')->insert(['idx' => 7, 'member_code' => 'MemberCode07', 'email' => 'fan@example.test', 'nickname' => 'fan', 'country_code' => 'KR']);
        DB::table('idols')->insert(['idx' => 5, 'name' => 'Demo Group', 'type' => 'group', 'gender' => 'female', 'status' => 'active']);
        DB::table('heart_reward_settings')->insert(['action_code' => 'idol_registration_approved', 'heart_amount' => 4321, 'daily_limit' => 0, 'is_enabled' => 1]);
    }

    public function test_guest_is_sent_to_the_existing_sign_in_flow(): void
    {
        $this->get('/idol-registration/request')->assertRedirect('/')->assertSessionHas('open_sign_in', true);
    }

    public function test_member_can_open_page_and_submit_pending_request(): void
    {
        $this->database();
        Storage::fake('public');

        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')
            ->assertOk()->assertSee('Idol Registration Request')->assertSee('Register Idol')
            ->assertSee('Approval Reward: 4,321 Hearts')->assertSee('Hearts are awarded after your request is approved.');

        $response = $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => str_repeat('a', 64)])->post('/idol-registration/request', [
            'request_key' => str_repeat('a', 64),
            'request_type' => 'girl', 'name' => '테스트', 'english_name' => 'Test Idol',
            'group_idx' => 5, 'birth_date' => '2000-01-02', 'nationality' => 'South Korean',
            'height_cm' => '168.5', 'weight_kg' => '50.5', 'blood_type' => 'A',
            'agency' => 'Demo Agency', 'debut_date' => '2020-02-03', 'position' => 'Vocalist',
            'sns' => '@testidol', 'content' => 'A concise and accurate introduction.',
            'profile_image' => UploadedFile::fake()->image('profile.png', 600, 600),
        ]);

        $response->assertRedirect(route('idol-registration.create'))->assertSessionHas('success');
        $saved = DB::table('idol_registration_requests')->first();
        $this->assertSame(7, $saved->member_idx);
        $this->assertSame(7, $saved->actor_member_idx);
        $this->assertSame('individual', $saved->type);
        $this->assertSame('female', $saved->gender);
        $this->assertSame('pending', $saved->status);
        $this->assertSame(5, $saved->group_idx);
        Storage::disk('public')->assertExists(substr($saved->image_path, 8));
    }

    public function test_disabled_or_zero_approval_reward_is_not_advertised(): void
    {
        $this->database();

        DB::table('heart_reward_settings')->where('action_code', 'idol_registration_approved')->update(['is_enabled' => 0]);
        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')
            ->assertOk()->assertDontSee('Approval Reward:');

        DB::table('heart_reward_settings')->where('action_code', 'idol_registration_approved')->update(['is_enabled' => 1, 'heart_amount' => 0]);
        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')
            ->assertOk()->assertDontSee('Approval Reward:');
    }

    public function test_server_rejects_wrong_group_gender(): void
    {
        $this->database();
        Storage::fake('public');
        $response = $this->from('/idol-registration/request')->withSession(['member_idx' => 7, 'idol_registration_request_key' => str_repeat('b', 64)])->post('/idol-registration/request', [
            'request_key' => str_repeat('b', 64),
            'request_type' => 'boy', 'name' => '테스트', 'english_name' => 'Test Idol',
            'group_idx' => 5, 'content' => 'Introduction',
            'profile_image' => UploadedFile::fake()->image('profile.png', 600, 600),
        ]);
        $response->assertRedirect('/idol-registration/request')->assertSessionHasErrors('group_idx');
        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_uncropped_non_square_original_image_is_accepted(): void
    {
        $this->database();
        Storage::fake('public');

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => str_repeat('c', 64)])->post('/idol-registration/request', [
            'request_key' => str_repeat('c', 64),
            'request_type' => 'boy', 'name' => 'Demo', 'english_name' => 'Demo Idol',
            'content' => 'Introduction',
            'profile_image' => UploadedFile::fake()->image('original.png', 900, 600),
        ])->assertRedirect(route('idol-registration.create'));

        $saved = DB::table('idol_registration_requests')->first();
        Storage::disk('public')->assertExists(substr($saved->image_path, 8));
    }

    public function test_group_request_clears_individual_only_values_and_saves_country(): void
    {
        $this->database();
        Storage::fake('public');

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => str_repeat('d', 64)])->post('/idol-registration/request', [
            'request_key' => str_repeat('d', 64),
            'request_type' => 'girl_group', 'name' => 'Demo', 'english_name' => 'Demo Group',
            'group_idx' => 5, 'birth_date' => '2000-01-02', 'nationality' => 'South Korea',
            'height_cm' => '168.5', 'weight_kg' => '50.5', 'blood_type' => 'A',
            'agency' => 'Demo Agency', 'debut_date' => '2020-02-03', 'position' => 'Vocalist',
            'sns' => '@demogroup', 'content' => 'A concise introduction.',
            'profile_image' => UploadedFile::fake()->image('profile.png', 600, 600),
        ])->assertRedirect(route('idol-registration.create'));

        $saved = DB::table('idol_registration_requests')->first();
        $this->assertSame('group', $saved->type);
        $this->assertSame('South Korea', $saved->nationality);
        foreach (['group_idx', 'birth_date', 'height_cm', 'weight_kg', 'blood_type', 'position'] as $field) {
            $this->assertNull($saved->{$field});
        }
    }

    public function test_repeated_request_key_does_not_create_a_duplicate_request(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('e', 64);
        $payload = [
            'request_key' => $key,
            'request_type' => 'boy',
            'name' => 'Demo',
            'english_name' => 'Demo Idol',
            'content' => 'Introduction',
        ];

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', $payload + [
                'profile_image' => UploadedFile::fake()->image('first.png', 600, 600),
            ])->assertRedirect(route('idol-registration.create'));
        $this->withSession(['member_idx' => 7])
            ->post('/idol-registration/request', $payload + [
                'profile_image' => UploadedFile::fake()->image('second.png', 600, 600),
            ])->assertRedirect(route('idol-registration.create'))->assertSessionHas('success');

        $this->assertSame(1, DB::table('idol_registration_requests')->count());
    }
}
