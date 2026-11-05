<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminProfessorController extends Controller
{
    public function index(Request $request): Response
    {
        $professors = User::query()
            ->where('role', User::ROLE_PROFESSOR)
            ->withCount('printCodes')
            ->latest()
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'print_codes_count' => (int) $user->print_codes_count,
                'created_at' => optional($user->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/Professors/Index', [
            'professors' => $professors,
            'email' => $request->user()->email,
            'role' => $request->user()->role,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:120'],
        ]);

        User::query()->create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_PROFESSOR,
            'is_active' => true,
        ]);

        return back()->with('success', 'تم إنشاء حساب الأستاذ.');
    }

    public function update(Request $request, User $professor): RedirectResponse
    {
        abort_unless($professor->role === User::ROLE_PROFESSOR, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($professor->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        $professor->name = trim($data['name']);
        $professor->email = strtolower(trim($data['email']));
        $professor->is_active = (bool) $data['is_active'];

        if (! empty($data['password'])) {
            $professor->password = Hash::make($data['password']);
        }

        $professor->save();

        return back()->with('success', 'تم تحديث حساب الأستاذ.');
    }
}
