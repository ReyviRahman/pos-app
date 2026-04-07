<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

new class extends Component
{
    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $editUserId = null;

    public string $createName = '';

    public string $createEmail = '';

    public string $createPassword = '';

    public string $createPasswordConfirmation = '';

    public string $editName = '';

    public string $editEmail = '';

    public string $editPassword = '';

    public string $editPasswordConfirmation = '';

    public ?int $deleteUserId = null;

    public string $deleteUserName = '';

    public function openCreateModal()
    {
        $this->resetValidation();
        $this->reset(['createName', 'createEmail', 'createPassword', 'createPasswordConfirmation']);
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
    }

    public function createKasir()
    {
        $this->validate([
            'createName' => 'required|string|max:255',
            'createEmail' => 'required|email|max:255|unique:users,email',
            'createPassword' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name' => $this->createName,
            'email' => $this->createEmail,
            'password' => Hash::make($this->createPassword),
            'role' => 'kasir',
        ]);

        $this->closeCreateModal();
        session()->flash('success', 'Kasir berhasil ditambahkan!');
    }

    public function openEditModal($userId)
    {
        $user = User::findOrFail($userId);
        $this->editUserId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->resetValidation();
        $this->reset(['editPassword', 'editPasswordConfirmation']);
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editUserId = null;
    }

    public function updateKasir()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editEmail' => 'required|email|max:255|unique:users,email,'.$this->editUserId,
            'editPassword' => 'nullable|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($this->editUserId);
        $user->name = $this->editName;
        $user->email = $this->editEmail;

        if ($this->editPassword !== '') {
            $user->password = Hash::make($this->editPassword);
        }

        $user->save();

        $this->closeEditModal();
        session()->flash('success', 'Kasir berhasil diperbarui!');
    }

    public function confirmDelete($userId)
    {
        $user = User::findOrFail($userId);
        $this->deleteUserId = $user->id;
        $this->deleteUserName = $user->name;
        $this->showDeleteConfirm = true;
    }

    public function closeDeleteConfirm()
    {
        $this->showDeleteConfirm = false;
        $this->deleteUserId = null;
    }

    public function deleteKasir()
    {
        $user = User::findOrFail($this->deleteUserId);
        $user->delete();

        $this->closeDeleteConfirm();
        session()->flash('success', 'Kasir berhasil dihapus!');
    }

    public function with(): array
    {
        return [
            'kasirs' => User::where('role', 'kasir')->latest()->get(),
        ];
    }
};
?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manajemen Kasir') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if (session()->has('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                <div class="p-6 text-gray-900 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-700">Daftar Kasir</h3>
                    <button wire:click="openCreateModal" class="bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md px-4 py-2 transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Tambah Kasir
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-center w-16">No</th>
                                <th class="py-3 px-6">Nama</th>
                                <th class="py-3 px-6">Email</th>
                                <th class="py-3 px-6 text-center">Role</th>
                                <th class="py-3 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            @forelse ($kasirs as $index => $kasir)
                                <tr class="border-b border-gray-200 hover:bg-gray-50 transition">
                                    <td class="py-3 px-6 text-center font-medium">{{ $index + 1 }}</td>
                                    <td class="py-3 px-6 font-semibold text-gray-800">
                                        {{ $kasir->name }}
                                    </td>
                                    <td class="py-3 px-6">
                                        {{ $kasir->email }}
                                    </td>
                                    <td class="py-3 px-6 text-center">
                                        <span class="bg-blue-100 text-blue-700 py-1 px-3 rounded-full text-xs font-medium">
                                            {{ ucfirst($kasir->role) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-6 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button wire:click="openEditModal({{ $kasir->id }})"
                                                    class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-medium px-3 py-1 rounded-md transition">
                                                Edit
                                            </button>
                                            <button wire:click="confirmDelete({{ $kasir->id }})"
                                                    class="bg-red-500 hover:bg-red-600 text-white text-xs font-medium px-3 py-1 rounded-md transition">
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-gray-400">
                                        Belum ada data kasir yang ditambahkan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>

    {{-- Modal Tambah Kasir --}}
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 transition-opacity">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">Tambah Kasir Baru</h3>
                    <button wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="createName" placeholder="Nama lengkap kasir" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('createName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input type="email" wire:model="createEmail" placeholder="email@contoh.com" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('createEmail') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="createPassword" placeholder="Minimal 8 karakter" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('createPassword') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="createPasswordConfirmation" placeholder="Ulangi password" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('createPasswordConfirmation') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                    <button type="button" wire:click="closeCreateModal" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md font-medium transition">Batal</button>
                    <button type="button" wire:click="createKasir" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Edit Kasir --}}
    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 transition-opacity">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">Edit Kasir</h3>
                    <button wire:click="closeEditModal" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="editName" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('editName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input type="email" wire:model="editEmail" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('editEmail') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru <span class="text-gray-400">(opsional)</span></label>
                        <input type="password" wire:model="editPassword" placeholder="Kosongkan jika tidak diubah" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('editPassword') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password <span class="text-gray-400">(opsional)</span></label>
                        <input type="password" wire:model="editPasswordConfirmation" placeholder="Ulangi password baru" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        @error('editPasswordConfirmation') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                    <button type="button" wire:click="closeEditModal" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md font-medium transition">Batal</button>
                    <button type="button" wire:click="updateKasir" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition shadow-sm">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    @if($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 transition-opacity">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-sm mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">Konfirmasi Hapus</h3>
                </div>

                <div class="px-6 py-6">
                    <p class="text-gray-600 text-sm">
                        Apakah Anda yakin ingin menghapus kasir <strong class="text-gray-800">{{ $deleteUserName }}</strong>?
                    </p>
                    <p class="text-xs text-red-500 mt-2">
                        Peringatan: Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                    <button type="button" wire:click="closeDeleteConfirm" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md font-medium transition">Batal</button>
                    <button type="button" wire:click="deleteKasir" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium transition shadow-sm">Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
