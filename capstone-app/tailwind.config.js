import forms from '@tailwindcss/forms';

/** Warna mengacu ke token di resources/css/tokens.css — tidak ada nilai warna mentah di sini. */
const token = (name) => `var(--color-${name})`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/View/**/*.php',
    ],
    theme: {
        screens: { xs: '480px', sm: '768px', md: '1024px', lg: '1280px' },
        extend: {
            colors: {
                paper: token('paper'),
                surface: token('surface'),
                sunken: token('sunken'),
                ink: token('ink'),
                muted: token('muted'),
                faint: token('faint'),
                rule: token('rule'),
                'rule-strong': token('rule-strong'),
                brand: { DEFAULT: token('brand'), deep: token('brand-deep'), tint: token('brand-tint'), wash: token('brand-wash') },
                accent: { DEFAULT: token('accent'), tint: token('accent-tint') },
                ok: { DEFAULT: token('success'), ink: token('success-ink'), tint: token('success-tint'), line: token('success-line') },
                warn: { DEFAULT: token('warning'), ink: token('warning-ink'), tint: token('warning-tint'), line: token('warning-line') },
                bad: { DEFAULT: token('danger'), ink: token('danger-ink'), tint: token('danger-tint'), deep: token('danger-deep'), line: token('danger-line') },
                scrim: token('scrim'),
            },
            fontFamily: {
                sans: ['var(--font-sans)'],
                mono: ['var(--font-mono)'],
            },
            fontSize: {
                caption: ['0.75rem', { lineHeight: '1.4' }],
                body: ['1rem', { lineHeight: '1.5' }],
                h2: ['1.375rem', { lineHeight: '1.3', fontWeight: '600' }],
                h1: ['1.75rem', { lineHeight: '1.2', fontWeight: '700' }],
            },
            borderRadius: { DEFAULT: 'var(--radius)', lg: 'var(--radius-lg)' },
            transitionTimingFunction: { out: 'var(--ease-out)' },
        },
    },
    plugins: [forms],
};
