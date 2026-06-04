<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { MessageCircle, RefreshCw, Sparkles, TriangleAlert } from '@lucide/vue';
import { computed, watch } from 'vue';
import { index as advisorChatIndex } from '@/actions/App/Http/Controllers/FinancialAdvisorChatController';
import {
    generate as advisorGenerate,
    index as advisorIndex,
} from '@/actions/App/Http/Controllers/FinancialAdvisorController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';

type Severity = 'nizka' | 'srednja' | 'visoka';

type Risk = {
    naslov: string;
    opis: string;
    resnost: Severity;
};

type Recommendation = {
    naslov: string;
    obrazlozitev: string;
    kategorija: string;
    prioriteta: Severity;
    ocenjen_vpliv: string;
};

type TaxTip = {
    naslov: string;
    opis: string;
};

type Report = {
    povzetek: string;
    ocena_neto_premozenja: string;
    mocne_tocke: string[];
    tveganja: Risk[];
    priporocila: Recommendation[];
    davcni_nasveti: TaxTip[];
    naslednji_koraki: string[];
};

type ReportPayload = {
    generated_at: string;
    report: Report;
};

const props = defineProps<{
    report: ReportPayload | null;
    isGenerating: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Svetovalec',
                href: advisorIndex.url(),
            },
        ],
    },
});

const SEVERITY_ORDER: Record<Severity, number> = {
    visoka: 0,
    srednja: 1,
    nizka: 2,
};

const SEVERITY_VARIANT: Record<
    Severity,
    'destructive' | 'default' | 'secondary'
> = {
    visoka: 'destructive',
    srednja: 'default',
    nizka: 'secondary',
};

const SEVERITY_LABEL: Record<Severity, string> = {
    visoka: 'Visoka',
    srednja: 'Srednja',
    nizka: 'Nizka',
};

const CATEGORY_LABEL: Record<string, string> = {
    varcevanje: 'Varčevanje',
    nalozbe: 'Naložbe',
    davki: 'Davki',
    razporeditev: 'Razporeditev',
    prejemki: 'Prejemki',
    obveznice: 'Obveznice',
};

const dateFormatter = new Intl.DateTimeFormat('sl-SI', {
    dateStyle: 'long',
    timeStyle: 'short',
});

const generatedAtLabel = computed(() =>
    props.report
        ? dateFormatter.format(new Date(props.report.generated_at))
        : null,
);

const sortedRecommendations = computed<Recommendation[]>(() =>
    [...(props.report?.report.priporocila ?? [])].sort(
        (a, b) => SEVERITY_ORDER[a.prioriteta] - SEVERITY_ORDER[b.prioriteta],
    ),
);

const { start, stop } = usePoll(
    5000,
    { only: ['report', 'isGenerating'] },
    { autoStart: false },
);

watch(
    () => props.isGenerating,
    (generating) => (generating ? start() : stop()),
    { immediate: true },
);

function generateReport(): void {
    if (props.isGenerating) {
        return;
    }

    router.post(advisorGenerate.url(), {}, { preserveScroll: true });
}

function categoryLabel(key: string): string {
    return CATEGORY_LABEL[key] ?? key;
}
</script>

<template>
    <Head title="Finančni svetovalec" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                title="Finančni svetovalec"
                description="AI analiza in priporočila za premoženje gospodinjstva"
            />
            <div class="flex flex-col items-start gap-1 sm:items-end">
                <div class="flex gap-2">
                    <Button as-child variant="outline" size="sm">
                        <Link :href="advisorChatIndex.url()">
                            <MessageCircle class="size-4" />
                            Klepet
                        </Link>
                    </Button>
                    <Button
                        size="sm"
                        :disabled="isGenerating"
                        @click="generateReport"
                    >
                        <Spinner v-if="isGenerating" class="size-4" />
                        <RefreshCw v-else class="size-4" />
                        {{
                            isGenerating ? 'Analiza poteka …' : 'Osveži analizo'
                        }}
                    </Button>
                </div>
                <span
                    v-if="generatedAtLabel"
                    class="text-xs text-muted-foreground"
                >
                    Posodobljeno: {{ generatedAtLabel }}
                </span>
            </div>
        </div>

        <!-- Generating state (no prior report) -->
        <Card v-if="isGenerating && !report">
            <CardContent
                class="flex flex-col items-center gap-4 py-12 text-center"
            >
                <Spinner class="size-8 text-primary" />
                <div class="space-y-1">
                    <p class="font-medium">Pripravljam finančno analizo …</p>
                    <p class="text-sm text-muted-foreground">
                        To lahko traja nekaj trenutkov. Stran se bo samodejno
                        osvežila.
                    </p>
                </div>
                <div class="w-full max-w-md space-y-2 pt-4">
                    <Skeleton class="h-4 w-3/4" />
                    <Skeleton class="h-4 w-full" />
                    <Skeleton class="h-4 w-2/3" />
                </div>
            </CardContent>
        </Card>

        <!-- Empty state -->
        <Card v-else-if="!report">
            <CardContent
                class="flex flex-col items-center gap-4 py-12 text-center"
            >
                <div
                    class="flex size-12 items-center justify-center rounded-full bg-primary/10"
                >
                    <Sparkles class="size-6 text-primary" />
                </div>
                <div class="space-y-1">
                    <p class="font-medium">Analiza še ni pripravljena</p>
                    <p class="max-w-md text-sm text-muted-foreground">
                        Ustvari prvo AI analizo svojega premoženja, prejemkov in
                        naložb s konkretnimi priporočili.
                    </p>
                </div>
                <Button :disabled="isGenerating" @click="generateReport">
                    <Sparkles class="size-4" />
                    Ustvari analizo
                </Button>
            </CardContent>
        </Card>

        <!-- Report -->
        <template v-else>
            <Card>
                <CardHeader>
                    <CardTitle>Povzetek</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm leading-relaxed">
                        {{ report.report.povzetek }}
                    </p>
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {{ report.report.ocena_neto_premozenja }}
                    </p>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card v-if="report.report.mocne_tocke.length">
                    <CardHeader>
                        <CardTitle>Močne točke</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li
                                v-for="(point, index) in report.report
                                    .mocne_tocke"
                                :key="index"
                                class="flex gap-2 text-sm"
                            >
                                <span
                                    class="mt-1 size-1.5 shrink-0 rounded-full bg-emerald-500"
                                />
                                <span>{{ point }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card v-if="report.report.tveganja.length">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <TriangleAlert class="size-4 text-amber-500" />
                            Tveganja
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="(risk, index) in report.report.tveganja"
                            :key="index"
                            class="space-y-1"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="text-sm font-medium">{{
                                    risk.naslov
                                }}</span>
                                <Badge
                                    :variant="SEVERITY_VARIANT[risk.resnost]"
                                >
                                    {{ SEVERITY_LABEL[risk.resnost] }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ risk.opis }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="sortedRecommendations.length">
                <CardHeader>
                    <CardTitle>Priporočila</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="(recommendation, index) in sortedRecommendations"
                        :key="index"
                        class="rounded-lg border p-4"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{
                                recommendation.naslov
                            }}</span>
                            <Badge
                                :variant="
                                    SEVERITY_VARIANT[recommendation.prioriteta]
                                "
                            >
                                {{ SEVERITY_LABEL[recommendation.prioriteta] }}
                            </Badge>
                            <Badge variant="outline">
                                {{ categoryLabel(recommendation.kategorija) }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ recommendation.obrazlozitev }}
                        </p>
                        <p class="mt-2 text-sm">
                            <span class="font-medium">Ocenjen vpliv:</span>
                            {{ recommendation.ocenjen_vpliv }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card v-if="report.report.davcni_nasveti.length">
                    <CardHeader>
                        <CardTitle>Davčni nasveti</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="(tip, index) in report.report.davcni_nasveti"
                            :key="index"
                            class="space-y-1"
                        >
                            <p class="text-sm font-medium">{{ tip.naslov }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ tip.opis }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="report.report.naslednji_koraki.length">
                    <CardHeader>
                        <CardTitle>Naslednji koraki</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol class="space-y-2">
                            <li
                                v-for="(step, index) in report.report
                                    .naslednji_koraki"
                                :key="index"
                                class="flex gap-3 text-sm"
                            >
                                <span
                                    class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-medium text-primary"
                                >
                                    {{ index + 1 }}
                                </span>
                                <span>{{ step }}</span>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </template>

        <p class="text-xs text-muted-foreground">
            Informativni nasveti za osebno rabo in ne licencirano finančno
            svetovanje.
        </p>
    </div>
</template>
