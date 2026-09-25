<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    public function update(string $locale): RedirectResponse
    {
        if (in_array($locale, ['es', 'en'])) {
            session(['locale' => $locale]);
            App::setLocale($locale);
        }

        return back();
    }
}
