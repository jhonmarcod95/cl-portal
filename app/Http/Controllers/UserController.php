<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Plant;
use App\Models\PlantUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use jeremykenedy\LaravelRoles\Models\Role;

class UserController extends Controller
{
    public function index(Request $request) {
        $search = trim((string) $request->input('search', ''));
        $users = User::orderBy('id', 'desc')
        ->when($search, function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->paginate(10)
        ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create() {
        return Inertia::render('Users/Create', [
            'roles' => Role::orderBy('name')->get(['id', 'name', 'slug']),
            'companies' => Company::whereNotNull('description')->orderBy('description')->get(['id', 'description']),
            'plants' => Plant::get(['id','plant_code', 'name']),
        ]);
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'integer', Rule::exists('roles', 'id')],
            'companies' => ['required', 'array', 'min:1'],
            'companies.*' => ['required', 'integer', 'distinct', Rule::exists('companies', 'id')],
            'plants' => ['nullable', 'array'],
            'plants.*' => ['required', 'string', 'distinct', Rule::exists('plants', 'plant_code')],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $user->attachRole((int) $validated['role']);
            $user->company()->sync($validated['companies']);
            DB::table('plant_users')->insert(
                collect($validated['plants'] ?? [])
                    ->map(fn (string $plantCode) => [
                        'user_id' => $user->id,
                        'plant_code' => $plantCode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->all()
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User created successfully.',
        ]);

        return redirect()->route('users.index');
    }

    public function edit(User $user) {
        return Inertia::render('Users/Edit', [
            'user' => $user->load(['roles', 'company', 'plants']),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'slug']),
            'companies' => Company::whereNotNull('description')->orderBy('description')->get(['id', 'description']),
            'plants' => Plant::get(['id','plant_code', 'name']),
        ]);
    }

    public function update(Request $request, User $user) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'integer', Rule::exists('roles', 'id')],
            'companies' => ['required', 'array', 'min:1'],
            'companies.*' => ['required', 'integer', 'distinct', Rule::exists('companies', 'id')],
            'plants' => ['nullable', 'array'],
            'plants.*' => ['required', 'string', 'distinct', Rule::exists('plants', 'plant_code')],
        ]);

        DB::transaction(function () use ($validated, $user) {
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if (! empty($validated['password'] ?? null)) {
                $user->password = $validated['password'];
            }

            $user->save();
            $user->syncRoles([(int) $validated['role']]);
            $user->company()->sync($validated['companies']);

            $selectedPlantCodes = $validated['plants'] ?? [];
            $plantAssignments = PlantUser::where('user_id', $user->id);
            if ($selectedPlantCodes !== []) {
                $plantAssignments->whereNotIn('plant_code', $selectedPlantCodes);
            }
            $plantAssignments->delete();

            foreach ($selectedPlantCodes as $plantCode) {
                $assignment = PlantUser::withTrashed()
                    ->where('user_id', $user->id)
                    ->where('plant_code', $plantCode)
                    ->first();

                if ($assignment) {
                    if ($assignment->trashed()) {
                        $assignment->restore();
                    }

                    continue;
                }

                DB::table('plant_users')->insert([
                    'user_id' => $user->id,
                    'plant_code' => $plantCode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User updated successfully.',
        ]);

        return redirect()->route('users.index');
    }
}
