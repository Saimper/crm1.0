<x-app-layout>
    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">Perfil</h1>
                <div class="page-subtitle">Tu información personal y seguridad</div>
            </div>
        </div>

        <div style="max-width:680px;display:flex;flex-direction:column;gap:16px;">
            <x-ui.card title="Información de perfil">
                <livewire:profile.update-profile-information-form />
            </x-ui.card>

            <x-ui.card title="Contraseña">
                <livewire:profile.update-password-form />
            </x-ui.card>

            <x-ui.card title="Eliminar cuenta">
                <livewire:profile.delete-user-form />
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
