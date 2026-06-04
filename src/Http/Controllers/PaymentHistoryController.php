<?php

namespace Payfastlaravelpackage\PayFastLaravelPackage\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Payfastlaravelpackage\PayFastLaravelPackage\Models\PaymentTransaction;

/**
 * Read-only history list. Auth users see their own; users with the admin role
 * (configurable) see everything.
 */
class PaymentHistoryController
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $adminRole = (string) config('payfast-laravel-package.admin_role', 'admin');
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole($adminRole);

        $query = PaymentTransaction::query()->orderByDesc('created_at');
        if (! $isAdmin) {
            $query->where('user_id', $user?->getAuthIdentifier());
        }

        return view('payfast-laravel-package::history.index', [
            'payments' => $query->paginate(20),
            'is_admin_view' => $isAdmin,
            'branding' => config('payfast-laravel-package.branding'),
        ]);
    }
}
