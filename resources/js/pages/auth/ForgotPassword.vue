<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Pozabljeno geslo',
        description:
            'Vnesite e-poštni naslov za prejem povezave za ponastavitev gesla',
    },
});

const props = defineProps<{
    status?: string;
}>();

const localizedStatus = computed(() => {
    if (!props.status) {
        return undefined;
    }

    return (
        {
            'We have emailed your password reset link.':
                'Povezavo za ponastavitev gesla smo poslali na vaš e-poštni naslov.',
        }[props.status] ?? props.status
    );
});
</script>

<template>
    <Head title="Pozabljeno geslo" />

    <div
        v-if="localizedStatus"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ localizedStatus }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">E-poštni naslov</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Pošlji povezavo za ponastavitev gesla
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>Ali pa se vrnite na</span>
            <TextLink :href="login()">prijavo</TextLink>
        </div>
    </div>
</template>
