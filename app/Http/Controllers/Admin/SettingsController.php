<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    private const SECTIONS = ['general', 'seo', 'management', 'custom-code'];

    public function edit(string $section = 'general')
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        return view('admin.settings.edit', ['section' => $section, 'settings' => SiteSettings::all()]);
    }

    public function update(Request $request, string $section)
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        $rules = match ($section) {
            'general' => ['site_title'=>['nullable','string','max:100'],'site_description'=>['nullable','string','max:500']],
            'seo' => ['site_title'=>['nullable','string','max:100'],'site_description'=>['nullable','string','max:500'],'site_keywords'=>['nullable','string','max:500'],'social_title'=>['nullable','string','max:100'],'social_description'=>['nullable','string','max:500'],'social_image'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],'favicon'=>['nullable','file','mimes:png,ico','max:1024'],'remove_social_image'=>['nullable','boolean'],'remove_favicon'=>['nullable','boolean']],
            'management' => [
                'fan_post_view_boost_enabled' => ['required', 'boolean'],
                'dummy_auto_vote_enabled' => ['required', 'boolean'],
                'dummy_auto_vote_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
                'dummy_auto_vote_min_hearts' => ['required', 'integer', 'min:1', 'max:100000'],
                'dummy_auto_vote_max_hearts' => ['required', 'integer', 'gte:dummy_auto_vote_min_hearts', 'max:100000'],
            ],
            default => ['head_code'=>['nullable','string','max:100000'],'custom_css'=>['nullable','string','max:100000'],'body_end_code'=>['nullable','string','max:100000']],
        };
        $data = $request->validate($rules);
        $admin = $request->attributes->get('currentMember');
        $values = collect($data)->except(['social_image','favicon','remove_social_image','remove_favicon'])->map(fn($v)=>(string)($v ?? ''))->all();
        foreach (['social_image','favicon'] as $field) {
            if ($request->boolean('remove_'.$field)) $values[$field.'_path']='';
            if ($request->hasFile($field)) $values[$field.'_path']='storage/'.$request->file($field)->store('site-settings','public');
        }
        DB::transaction(function () use ($values,$admin): void {
            foreach ($values as $key=>$value) DB::table('site_settings')->updateOrInsert(['setting_key'=>$key],['setting_value'=>$value,'updated_by_member_idx'=>$admin->idx,'updated_at'=>now()]);
        });
        SiteSettings::forget();
        $action = $section === 'seo' ? 'seo_settings_changed' : ($section === 'custom-code' ? 'custom_frontend_code_changed' : 'site_settings_changed');
        AdminActivityLogger::record($admin->idx,$action,'site_settings',0,null,$section === 'custom-code' ? '추가 코드 설정 변경' : implode(', ',array_keys($values)));
        return back()->with('admin_success','설정을 저장했습니다.');
    }
}
