<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Prijava v račun',
        description: 'Za prijavo vnesite e-poštni naslov in geslo',
    },
});

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    csrfToken: string;
}>();

const localizedStatus = computed(() => {
    if (!props.status) {
        return undefined;
    }

    return (
        {
            'Your password has been reset.':
                'Vaše geslo je bilo uspešno ponastavljeno.',
        }[props.status] ?? props.status
    );
});
</script>

<template>
    <Head title="Prijava" />

    <div
        v-if="localizedStatus"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ localizedStatus }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <input type="hidden" name="_token" :value="csrfToken" />

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">E-poštni naslov</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Geslo</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Ste pozabili geslo?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Geslo"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Zapomni si me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Prijava
            </Button>
        </div>
    </Form>
</template>
