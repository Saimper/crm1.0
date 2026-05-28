<x-app-layout>
    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('profile.title') }}</h1>
                <div class="page-subtitle">{{ __('profile.subtitle') }}</div>
            </div>
        </div>

        <div style="max-width:680px;display:flex;flex-direction:column;gap:16px;">
            <x-ui.card :title="__('profile.section_info')">
                <livewire:profile.update-profile-information-form />
            </x-ui.card>

            <x-ui.card :title="__('profile.section_password')">
                <livewire:profile.update-password-form />
            </x-ui.card>

            <x-ui.card :title="__('profile.section_delete')">
                <livewire:profile.delete-user-form />
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
