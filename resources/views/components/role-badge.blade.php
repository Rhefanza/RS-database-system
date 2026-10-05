@if (auth()->check() && in_array(auth()->user()->role, ['PETUGAS', 'ADMIN'], true))
    <aside class="role-badge" aria-label="Role akun saat ini">
        <strong>{{ auth()->user()->role === 'PETUGAS' ? 'petugas' : 'admin' }}</strong>
    </aside>
@endif
