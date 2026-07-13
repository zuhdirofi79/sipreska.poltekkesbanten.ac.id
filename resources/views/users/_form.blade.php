@php
    $editing = isset($user);
@endphp

<div>
    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Nama Lengkap</label>
    <input id="name" name="name" type="text" value="{{ old('name', $editing ? $user->name : '') }}" required class="sipreska-input">
</div>

<div>
    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $editing ? $user->email : '') }}" required class="sipreska-input">
</div>

<div>
    <label for="role" class="mb-1 block text-sm font-medium text-gray-700">Role</label>
    <select id="role" name="role" required class="sipreska-select">
        @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role', $editing ? $user->role?->value : '') === $role->value)>{{ $role->label() }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="unit_id" class="mb-1 block text-sm font-medium text-gray-700">Unit</label>
    <select id="unit_id" name="unit_id" class="sipreska-select">
        <option value="">-</option>
        @foreach ($units as $unit)
            <option value="{{ $unit->id }}" @selected((string) old('unit_id', $editing ? $user->unit_id : '') === (string) $unit->id)>{{ $unit->name }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="is_active" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
    <select id="is_active" name="is_active" required class="sipreska-select">
        <option value="1" @selected(old('is_active', $editing ? $user->is_active : true) == true)>Aktif</option>
        <option value="0" @selected(old('is_active', $editing ? $user->is_active : true) == false)>Nonaktif</option>
    </select>
</div>
