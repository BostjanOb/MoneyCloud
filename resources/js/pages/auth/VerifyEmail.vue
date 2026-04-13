<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Potrditev e-pošte',
        description:
            'Potrdite svoj e-poštni naslov s klikom na povezavo, ki smo vam jo pravkar poslali.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Potrditev e-pošte" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        Novo potrditveno povezavo smo poslali na e-poštni naslov, ki ste ga
        vnesli ob registraciji.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Ponovno pošlji potrditveni e-mail
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Odjava
        </TextLink>
    </Form>
</template>
