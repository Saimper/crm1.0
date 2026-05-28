<x-app-layout>
    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('dashboard.title') }}</h1>
                <div class="page-subtitle">{{ __('dashboard.subtitle') }}</div>
            </div>
        </div>

        <livewire:tenancy.selector-proyecto />
    </div>
</x-app-layout>
