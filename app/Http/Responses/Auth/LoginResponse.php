<?php

namespace App\Http\Responses\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $user = $request->user();

        if ($user instanceof User && $user->mustChangePassword()) {
            return redirect()->to(Filament::getProfileUrl());
        }

        $homeUrl = Filament::getHomeUrl() ?? Filament::getUrl();

        return redirect()->intended($homeUrl);
    }
}
