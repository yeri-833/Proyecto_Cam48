<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    tipo_usuario: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registro Institucional — FamilyLock CAM #48" />

        <div class="mb-6 text-center">
            <h2 class="text-xl font-bold tracking-tight text-slate-800 dark:text-slate-100">
                Nuevo Usuario Institucional
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Panel Administrativo — Alta de cuentas de acceso escolar
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="name" value="Nombre Completo *" class="text-xs uppercase tracking-wider font-semibold" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full rounded-2xl border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-950/70 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    v-model="form.name"
                    placeholder="Ej. Profa. Ana Martínez"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-1.5 text-xs" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Correo Electrónico Institucional *" class="text-xs uppercase tracking-wider font-semibold" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full rounded-2xl border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-950/70 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    v-model="form.email"
                    placeholder="ejemplo@cam48.edu.mx"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-1.5 text-xs" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="tipo_usuario" value="Rol Institucional *" class="text-xs uppercase tracking-wider font-semibold" />

                <select
                    id="tipo_usuario"
                    v-model="form.tipo_usuario"
                    required
                    class="mt-1 block w-full rounded-2xl border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-950/70 text-sm text-slate-800 dark:text-slate-200 py-2.5 px-3.5 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors cursor-pointer"
                >
                    <option value="" disabled>Seleccione el rol correspondiente...</option>
                    <option value="Secretaria">Secretaria (Gestión escolar)</option>
                    <option value="Operador">Operador (Exclusivo terminal Kiosco / Escáner QR)</option>
                    <option value="Director">Director (Control institucional completo)</option>
                </select>

                <InputError class="mt-1.5 text-xs" :message="form.errors.tipo_usuario" />
            </div>

            <div>
                <InputLabel for="password" value="Contraseña *" class="text-xs uppercase tracking-wider font-semibold" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full rounded-2xl border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-950/70 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    v-model="form.password"
                    placeholder="Mínimo 8 caracteres"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-1.5 text-xs" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar Contraseña *"
                    class="text-xs uppercase tracking-wider font-semibold"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full rounded-2xl border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-950/70 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    v-model="form.password_confirmation"
                    placeholder="Repita la contraseña"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-1.5 text-xs"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="pt-2 flex items-center justify-between">
                <Link
                    :href="route('dashboard')"
                    class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition-colors focus:outline-none focus:underline"
                >
                    ← Volver al Dashboard
                </Link>

                <PrimaryButton
                    class="rounded-xl px-5 py-2.5 shadow-md shadow-primary/20 hover:shadow-primary/30 active:scale-[0.98] transition-all"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Registrando...' : 'Registrar Usuario' }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
