<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->mustChangePassword()) {
            return $next($request);
        }

        $panel = Filament::getCurrentPanel();
        $panelPath = trim($panel->getPath(), '/');
        $path = trim($request->path(), '/');

        $allowedPaths = [
            "{$panelPath}/profile",
            "{$panelPath}/logout",
            'livewire/update',
            'livewire/upload-file',
        ];

        foreach ($allowedPaths as $allowedPath) {
            if ($path === $allowedPath || str_starts_with($path, $allowedPath.'/')) {
                return $next($request);
            }
        }

        return redirect()->to($panel->getProfileUrl());
    }
}
