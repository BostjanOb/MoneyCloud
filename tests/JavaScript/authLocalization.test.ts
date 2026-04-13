import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

type LocalizationCase = {
    file: string;
    includes: string[];
    excludes: string[];
};

const localizationCases: LocalizationCase[] = [
    {
        file: 'resources/js/pages/auth/Login.vue',
        includes: [
            'Prijava v račun',
            'Ste pozabili geslo?',
            'Zapomni si me',
            'Prijava',
        ],
        excludes: ['Log in to your account', 'Forgot password?', 'Remember me'],
    },
    {
        file: 'resources/js/pages/auth/ForgotPassword.vue',
        includes: [
            'Pozabljeno geslo',
            'Pošlji povezavo za ponastavitev gesla',
            'Ali pa se vrnite na',
            'prijavo',
        ],
        excludes: ['Forgot password', 'Email password reset link'],
    },
    {
        file: 'resources/js/pages/auth/TwoFactorChallenge.vue',
        includes: [
            'Dvofaktorska prijava',
            'Obnovitvena koda',
            'Avtentikacijska koda',
            'Nadaljuj',
        ],
        excludes: ['Recovery code', 'Authentication code', 'Continue'],
    },
    {
        file: 'resources/js/pages/settings/Profile.vue',
        includes: [
            'Podatki profila',
            'E-poštni naslov',
            'Vaš e-poštni naslov še ni potrjen.',
            'Shranjeno.',
        ],
        excludes: [
            'Profile information',
            'Email address',
            'Your email address is unverified.',
            'Saved.',
        ],
    },
    {
        file: 'resources/js/components/UserMenuContent.vue',
        includes: ['Nastavitve', 'Odjava'],
        excludes: ['Log out'],
    },
    {
        file: 'resources/js/pages/settings/Security.vue',
        includes: [
            'Dvofaktorska avtentikacija',
            'Trenutno geslo',
            'Nadaljuj nastavitev',
            'Omogoči 2FA',
            'Onemogoči 2FA',
        ],
        excludes: [
            'Two-factor authentication',
            'Current password',
            'Continue setup',
            'Enable 2FA',
            'Disable 2FA',
        ],
    },
    {
        file: 'resources/js/components/TwoFactorSetupModal.vue',
        includes: [
            'Dvofaktorska avtentikacija je omogočena',
            'Potrdite avtentikacijsko kodo',
            'ali pa kodo vnesite ročno',
            'Potrdi',
        ],
        excludes: [
            'Two-factor authentication enabled',
            'Verify authentication code',
            'or, enter the code manually',
        ],
    },
    {
        file: 'resources/js/components/TwoFactorRecoveryCodes.vue',
        includes: [
            'Obnovitvene kode za 2FA',
            'Prikaži',
            'Skrij',
            'Ustvari nove kode',
        ],
        excludes: ['2FA recovery codes', 'Hide', 'Regenerate codes'],
    },
    {
        file: 'resources/js/pages/settings/Appearance.vue',
        includes: ['Nastavitve videza', 'Prilagodite videz svojega računa'],
        excludes: [
            'Appearance settings',
            "Update your account's appearance settings",
        ],
    },
    {
        file: 'resources/js/components/AppearanceTabs.vue',
        includes: ['Svetlo', 'Temno', 'Sistem'],
        excludes: ['Light', 'Dark', 'System'],
    },
    {
        file: 'resources/js/components/DeleteUser.vue',
        includes: [
            'Izbriši račun',
            'Opozorilo',
            'Ali ste prepričani, da želite izbrisati svoj',
            'račun?',
            'Prekliči',
        ],
        excludes: [
            'Delete account',
            'Warning',
            'Are you sure you want to delete your account?',
            'Cancel',
        ],
    },
    {
        file: 'resources/js/components/PasswordInput.vue',
        includes: ['Skrij geslo', 'Prikaži geslo'],
        excludes: ['Hide password', 'Show password'],
    },
];

for (const localizationCase of localizationCases) {
    test(`${localizationCase.file} uses Slovenian copy`, async () => {
        const source = await readFile(
            new URL(`../../${localizationCase.file}`, import.meta.url),
            'utf8',
        );

        for (const expectedString of localizationCase.includes) {
            assert.match(source, new RegExp(escapeForRegExp(expectedString)));
        }

        for (const unexpectedString of localizationCase.excludes) {
            assert.doesNotMatch(
                source,
                new RegExp(escapeForRegExp(unexpectedString)),
            );
        }
    });
}

function escapeForRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
