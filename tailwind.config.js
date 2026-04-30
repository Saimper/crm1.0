// Tokens extraídos de "Núcleo CRM (standalone).html" — fuente de verdad visual (F29).
// Paleta neutral cool-gray + primary blue 600 + semánticos puros.
// Tipografía: IBM Plex Sans (UI) + IBM Plex Mono (números/IDs/timestamps).
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', 'Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            fontSize: {
                'xs':  ['11px', { lineHeight: '1.4' }],
                'sm':  ['12px', { lineHeight: '1.45' }],
                'base':['13px', { lineHeight: '1.45' }],
                'md':  ['14px', { lineHeight: '1.45' }],
                'lg':  ['15px', { lineHeight: '1.4' }],
                'xl':  ['16px', { lineHeight: '1.4' }],
                '2xl': ['18px', { lineHeight: '1.35' }],
                '3xl': ['20px', { lineHeight: '1.3' }],
                '4xl': ['24px', { lineHeight: '1.25' }],
                '5xl': ['28px', { lineHeight: '1.2' }],
                '6xl': ['32px', { lineHeight: '1.15' }],
            },
            colors: {
                // Surfaces (cool gray neutrals)
                surface: {
                    DEFAULT: '#fafafa',  // bg
                    0:       '#ffffff',  // bg-elev (cards, inputs)
                    50:      '#fafafa',  // bg page
                    100:     '#f4f5f7',  // bg-subtle (table head, kbd)
                    200:     '#f0f1f3',  // bg-hover
                    300:     '#e8eaed',  // bg-active
                    border:  '#e4e6ea',
                    'border-strong': '#d4d7dc',
                },
                ink: {
                    DEFAULT: '#18181b',  // text
                    900:     '#18181b',
                    800:     '#27272a',
                    700:     '#52525b',  // text-secondary
                    600:     '#52525b',
                    500:     '#71717a',  // text-tertiary
                    400:     '#a1a1aa',  // text-muted
                    50:      '#fafafa',
                    inverse: '#ffffff',
                },
                accent: {
                    50:  '#eff4ff',
                    500: '#2563eb',
                    600: '#1d4ed8',
                    700: '#1d4ed8',
                },
                brand: {
                    DEFAULT: '#2563eb',
                    50:  '#eff4ff',  // primary-soft
                    100: '#dbe4fe',  // primary-soft-border
                    200: '#bfd1fc',
                    300: '#93b3fa',
                    400: '#5b8df5',
                    500: '#2563eb',  // primary
                    600: '#2563eb',
                    700: '#1d4ed8',  // primary-hover / primary-text
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
                success: {
                    DEFAULT: '#16a34a',
                    50:  '#ecfdf5',
                    100: '#c8eed4',
                    200: '#a7e0bc',
                    500: '#16a34a',
                    600: '#15803d',
                    700: '#15803d',
                    800: '#166534',
                    900: '#14532d',
                },
                warning: {
                    DEFAULT: '#d97706',
                    50:  '#fef6e7',
                    100: '#fde9b8',
                    200: '#fcd58c',
                    500: '#d97706',
                    600: '#b45309',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                },
                danger: {
                    DEFAULT: '#dc2626',
                    50:  '#fef2f2',
                    100: '#fde0e0',
                    200: '#fbb6b6',
                    500: '#dc2626',
                    600: '#b91c1c',
                    700: '#b91c1c',
                    800: '#991b1b',
                    900: '#7f1d1d',
                },
                info: {
                    DEFAULT: '#0891b2',
                    50:  '#ecfeff',
                    100: '#c5ecf3',
                    200: '#a3dfeb',
                    500: '#0891b2',
                    600: '#0e7490',
                    700: '#0e7490',
                    800: '#155e75',
                    900: '#164e63',
                },
                neutral: {
                    soft: '#f1f3f5',
                    text: '#52525b',
                },
            },
            spacing: {
                'header': '56px',
                'sidebar': '240px',
            },
            boxShadow: {
                'sm':         '0 1px 2px rgba(16, 24, 40, 0.04)',
                'md':         '0 4px 12px rgba(16, 24, 40, 0.08)',
                'lg':         '0 12px 32px rgba(16, 24, 40, 0.12)',
                'card':       '0 1px 2px rgba(16, 24, 40, 0.04)',
                'card-hover': '0 4px 16px rgba(16, 24, 40, 0.08)',
            },
            borderRadius: {
                'xs': '3px',
                'sm': '4px',
                DEFAULT: '6px',
                'md': '6px',
                'lg': '8px',
                'xl': '12px',
            },
            transitionTimingFunction: {
                'ease-ui': 'cubic-bezier(0.2, 0, 0, 1)',
            },
            transitionDuration: {
                'fast': '120ms',
                'base': '160ms',
                'slow': '200ms',
            },
            keyframes: {
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                'slide-down': {
                    '0%': { opacity: '0', transform: 'translateY(-4px)' },
                    '100%': { opacity: '1', transform: 'none' },
                },
                'toast-in': {
                    '0%': { opacity: '0', transform: 'translateY(-6px)' },
                    '100%': { opacity: '1', transform: 'none' },
                },
            },
            animation: {
                'fade-in':   'fade-in 160ms cubic-bezier(0.2, 0, 0, 1)',
                'slide-down':'slide-down 180ms cubic-bezier(0.2, 0, 0, 1) both',
                'toast-in':  'toast-in 180ms cubic-bezier(0.2, 0, 0, 1)',
            },
        },
    },

    plugins: [forms],
};
