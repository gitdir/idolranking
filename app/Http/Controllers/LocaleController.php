<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->string('locale')->toString();
        abort_unless(in_array($locale, ['ko', 'en'], true), 404);

        $request->session()->put('locale', $locale);
        $request->session()->reflash();

        $returnTo = $request->input('return_to');
        if (is_string($returnTo) && str_starts_with($returnTo, '/')
            && ! str_starts_with($returnTo, '//') && ! str_contains($returnTo, '\\')) {
            return redirect()->to($returnTo);
        }

        return redirect()->route('home');
    }
}
