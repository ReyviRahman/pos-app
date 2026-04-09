<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Branch & Account Selection with Alpine.js -->
        <div x-data="{
                selectedBranch: '',
                users: {{ Js::from($users) }},
                get filteredUsers() {
                    if (!this.selectedBranch) return [];
                    return this.users.filter(u => u.branch_id == this.selectedBranch);
                }
            }">

            <!-- Select Branch -->
            <div class="mb-4">
                <x-input-label for="branch_id" value="Pilih Cabang Tempat Anda Bertugas" />
                <select id="branch_id" name="branch_id" x-model="selectedBranch" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-gray-700" required autofocus>
                    <option value="" disabled selected>-- Pilih Cabang --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Account Selection (Replaces Email Input) -->
            <div x-show="selectedBranch" x-cloak>
                <x-input-label for="email" value="Pilih Akun Karyawan" />
                <select id="email" name="email" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-gray-700" required>
                    <option value="" disabled selected>-- Pilih Akun --</option>
                    <template x-for="user in filteredUsers" :key="user.id">
                        <option :value="user.email" x-text="`${user.name} (${user.role.charAt(0).toUpperCase() + user.role.slice(1)})`" :selected="user.email == '{{ old('email') }}'"></option>
                    </template>
                </select>
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            
            <p x-show="selectedBranch && filteredUsers.length === 0" x-cloak class="mt-2 text-sm text-red-600">
                Tidak ada karyawan yang terdaftar di cabang ini.
            </p>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
