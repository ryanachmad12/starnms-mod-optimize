<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        User::create($data);
        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (blank($data['password'] ?? null)) unset($data['password']);
        abort_if($user->isAdministrator() && ($data['role'] !== 'administrator' || ! $data['is_active']) && User::where('role', 'administrator')->where('is_active', true)->whereKeyNot($user)->doesntExist(), 422, 'Administrator aktif terakhir tidak dapat dinonaktifkan atau diturunkan perannya.');
        $user->update($data);
        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'Akun yang sedang digunakan tidak dapat dihapus.');
        abort_if($user->isAdministrator() && User::where('role', 'administrator')->count() <= 1, 422, 'Administrator terakhir tidak dapat dihapus.');
        $user->delete();
        return back()->with('success', 'User berhasil dihapus.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'alpha_dash', 'max:60', Rule::unique('users')->ignore($user)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(['administrator', 'user'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
