<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Meu Perfil" subtitle="Gerencie suas informações de conta e segurança." />
    </x-slot>

    <div class="space-y-4">
        <x-ui.card>
            <div class="max-w-xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="max-w-xl">
                <livewire:profile.update-password-form />
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="max-w-xl">
                <livewire:profile.delete-user-form />
            </div>
        </x-ui.card>
    </div>
</x-app-layout>
