<template>
    <!--
        The logo ships as static SVG files rather than inline markup. The light
        and dark variants are swapped by the `dark` class on <html>, which
        useAppearance keeps in sync, so the swap needs no JavaScript here.
        The wrappers pick the sidebar state, the images pick the theme, so no
        two utilities on one element ever fight over `display`.
    -->
    <div class="flex items-center">
        <span class="flex group-data-[collapsible=icon]:hidden">
            <img
                src="/logo-light.svg"
                alt="MoneyCloud — osebne finance"
                width="143"
                height="40"
                class="h-10 w-auto dark:hidden"
            />
            <img
                src="/logo-dark.svg"
                alt="MoneyCloud — osebne finance"
                width="143"
                height="40"
                class="hidden h-10 w-auto dark:block"
            />
        </span>

        <!-- Collapsed sidebar: the medallion on its own. -->
        <span class="hidden group-data-[collapsible=icon]:flex">
            <img
                src="/mark-light.svg"
                alt="MoneyCloud"
                width="64"
                height="64"
                class="size-8 shrink-0 dark:hidden"
            />
            <img
                src="/mark-dark.svg"
                alt="MoneyCloud"
                width="64"
                height="64"
                class="hidden size-8 shrink-0 dark:block"
            />
        </span>
    </div>
</template>
