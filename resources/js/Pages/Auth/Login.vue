<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const tab = ref('admin');
const adminForm = useForm({ email: '', password: '' });
const memberForm = useForm({ code: '', pin: '' });

function loginAdmin() {
    adminForm.post('/login');
}

function joinBand() {
    memberForm.post('/join');
}
</script>

<template>
    <Head title="Sign In" />

    <div style="min-height: 100vh; background: #f8fafc; padding: 40px 16px; font-family: sans-serif;">
        <div style="max-width: 400px; margin: 0 auto; background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px;">
            <h1 style="font-size: 20px; font-weight: 700; margin: 0 0 4px; color: #0f172a;">Sign In</h1>
            <p style="color: #64748b; font-size: 14px; margin: 0 0 20px;">Repertorios</p>

            <div style="display: flex; border-bottom: 1px solid #e2e8f0; margin-bottom: 16px;">
                <button
                    type="button"
                    @click="tab = 'admin'"
                    :style="{ flex: 1, padding: '12px 0', fontSize: '14px', fontWeight: 600, background: 'transparent', border: 'none', cursor: 'pointer', color: tab === 'admin' ? '#4f46e5' : '#64748b', borderBottom: tab === 'admin' ? '2px solid #4f46e5' : '2px solid transparent' }"
                >Admin</button>
                <button
                    type="button"
                    @click="tab = 'member'"
                    :style="{ flex: 1, padding: '12px 0', fontSize: '14px', fontWeight: 600, background: 'transparent', border: 'none', cursor: 'pointer', color: tab === 'member' ? '#4f46e5' : '#64748b', borderBottom: tab === 'member' ? '2px solid #4f46e5' : '2px solid transparent' }"
                >Member</button>
            </div>

            <form v-if="tab === 'admin'" @submit.prevent="loginAdmin">
                <div style="margin-bottom: 12px;">
                    <label for="email" style="display: block; font-size: 12px; font-weight: 500; color: #475569; margin-bottom: 4px;">Email</label>
                    <input
                        id="email"
                        v-model="adminForm.email"
                        type="email"
                        required
                        autocomplete="email"
                        style="width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box;"
                    />
                    <p v-if="adminForm.errors.email" style="color: #dc2626; font-size: 12px; margin: 4px 0 0;">{{ adminForm.errors.email }}</p>
                </div>
                <div style="margin-bottom: 16px;">
                    <label for="password" style="display: block; font-size: 12px; font-weight: 500; color: #475569; margin-bottom: 4px;">Password</label>
                    <input
                        id="password"
                        v-model="adminForm.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        style="width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box;"
                    />
                    <p v-if="adminForm.errors.password" style="color: #dc2626; font-size: 12px; margin: 4px 0 0;">{{ adminForm.errors.password }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="adminForm.processing"
                    style="width: 100%; padding: 10px; background: #4f46e5; color: white; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;"
                >
                    {{ adminForm.processing ? 'Signing in…' : 'Sign In' }}
                </button>
            </form>

            <form v-else @submit.prevent="joinBand">
                <div style="margin-bottom: 12px;">
                    <label for="code" style="display: block; font-size: 12px; font-weight: 500; color: #475569; margin-bottom: 4px;">Band Code</label>
                    <input
                        id="code"
                        v-model="memberForm.code"
                        type="text"
                        required
                        maxlength="6"
                        style="width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 8px; text-transform: uppercase; box-sizing: border-box;"
                    />
                    <p v-if="memberForm.errors.code" style="color: #dc2626; font-size: 12px; margin: 4px 0 0;">{{ memberForm.errors.code }}</p>
                </div>
                <div style="margin-bottom: 16px;">
                    <label for="pin" style="display: block; font-size: 12px; font-weight: 500; color: #475569; margin-bottom: 4px;">PIN</label>
                    <input
                        id="pin"
                        v-model="memberForm.pin"
                        type="password"
                        required
                        minlength="4"
                        maxlength="8"
                        style="width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box;"
                    />
                    <p v-if="memberForm.errors.pin" style="color: #dc2626; font-size: 12px; margin: 4px 0 0;">{{ memberForm.errors.pin }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="memberForm.processing"
                    style="width: 100%; padding: 10px; background: #4f46e5; color: white; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;"
                >
                    {{ memberForm.processing ? 'Joining…' : 'Join Band' }}
                </button>
            </form>
        </div>
    </div>
</template>
