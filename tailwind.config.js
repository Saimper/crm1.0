// Tokens alineados al Design System wrapper unificado (perfil admin).
// Paleta slate + primario azul acero #2E75B6. Tipografía Inter + JetBrains Mono.
// Fuente de verdad: spec de unificación CRM + WhatsApp + ViciDial Modern.
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
                sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', ...defaultTheme.fontFamily.mono],
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
                '4xl': ['22px', { lineHeight: '1.3' }],
                '5xl': ['26px', { lineHeight: '1.15' }],
                '6xl': ['32px', { lineHeight: '1.15' }],
            },
            colors: {
                // Surfaces (slate neutrals — perfil admin)
                surface: {
                    DEFAULT: '#F8FAFC',  // bg page
                    0:       '#ffffff',  // bg elevated (cards, modals, dropdowns)
                    50:      '#F8FAFC',
                    100:     '#F1F5F9',  // bg subtle (inputs, table head, kbd)
                    200:     '#E2E8F0',  // hover
                    300:     '#CBD5E1',  // active / border strong
                    border:  '#E2E8F0',
                    'border-strong': '#CBD5E1',
                },
                ink: {
                    DEFAULT: '#0F172A',  // text primario (slate-900)
                    900:     '#0F172A',
                    800:     '#1E293B',
                    700:     '#334155',
                    600:     '#475569',  // text secundario (slate-600)
                    500:     '#64748B',  // text terciario (slate-500)
                    400:     '#94A3B8',  // muted / placeholders
                    300:     '#CBD5E1',
                    200:     '#E2E8F0',
                    100:     '#F1F5F9',
                    50:      '#F8FAFC',
                    inverse: '#FFFFFF',
                },
                // Primario — azul acero admin
                accent: {
                    50:  '#E8F1F9',
                    100: '#CFE0EE',
                    500: '#2E75B6',
                    600: '#266299',
                    700: '#1F517E',
                },
                brand: {
                    DEFAULT: '#2E75B6',
                    50:  '#E8F1F9',   // primary-soft
                    100: '#CFE0EE',   // primary-soft-strong
                    200: '#AECCE3',
                    300: '#7FAFD0',
                    400: '#5092C5',
                    500: '#2E75B6',   // base
                    600: '#266299',   // hover
                    700: '#1F517E',   // active / pressed / text sobre soft
                    800: '#173F61',
                    900: '#102C44',
                },
                success: {
                    DEFAULT: '#16A34A',
                    50:  '#DCFCE7',
                    100: '#BBF7D0',
                    200: '#86EFAC',
                    500: '#16A34A',
                    600: '#15803D',
                    700: '#15803D',
                    800: '#166534',
                    900: '#14532D',
                },
                warning: {
                    DEFAULT: '#D97706',
                    50:  '#FEF3C7',
                    100: '#FDE68A',
                    200: '#FCD34D',
                    500: '#D97706',
                    600: '#B45309',
                    700: '#B45309',
                    800: '#92400E',
                    900: '#78350F',
                },
                danger: {
                    DEFAULT: '#DC2626',
                    50:  '#FEE2E2',
                    100: '#FECACA',
                    200: '#FCA5A5',
                    500: '#DC2626',
                    600: '#B91C1C',
                    700: '#B91C1C',
                    800: '#991B1B',
                    900: '#7F1D1D',
                },
                info: {
                    DEFAULT: '#0369A1',
                    50:  '#E0F2FE',
                    100: '#BAE6FD',
                    200: '#7DD3FC',
                    500: '#0369A1',
                    600: '#075985',
                    700: '#0369A1',
                    800: '#0C4A6E',
                    900: '#082F49',
                },
                violet: {
                    DEFAULT: '#6D28D9',
                    50:  '#EDE9FE',
                    500: '#6D28D9',
                    600: '#5B21B6',
                    700: '#4C1D95',
                },
                neutral: {
                    soft: '#F1F5F9',
                    text: '#475569',
                },
                // Buckets de mora (rangos de cobranza)
                bucket: {
                    1: '#10B981',
                    2: '#3B82F6',
                    3: '#6366F1',
                    4: '#F59E0B',
                    5: '#F97316',
                    6: '#EF4444',
                    default: '#94A3B8',
                },
            },
            spacing: {
                'header': '56px',
                'sidebar': '240px',
            },
            boxShadow: {
                'xs':         '0 1px 2px rgba(15, 23, 42, 0.05)',
                'sm':         '0 1px 3px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03)',
                'md':         '0 4px 16px rgba(15, 23, 42, 0.06), 0 1px 4px rgba(15, 23, 42, 0.04)',
                'lg':         '0 8px 32px rgba(15, 23, 42, 0.08), 0 2px 8px rgba(15, 23, 42, 0.04)',
                'card':       '0 1px 2px rgba(15, 23, 42, 0.05)',
                'card-hover': '0 4px 16px rgba(15, 23, 42, 0.06)',
                'focus':      '0 0 0 3px rgba(46, 117, 182, 0.15)',
                'focus-danger': '0 0 0 3px rgba(220, 38, 38, 0.15)',
            },
            borderRadius: {
                'xs': '3px',
                'sm': '4px',
                DEFAULT: '6px',
                'md': '6px',
                'lg': '8px',
                'xl': '10px',
                '2xl': '12px',
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
                    '0%': { opacity: '0', transform: 'translateX(8px)' },
                    '100%': { opacity: '1', transform: 'none' },
                },
            },
            animation: {
                'fade-in':   'fade-in 160ms cubic-bezier(0.2, 0, 0, 1)',
                'slide-down':'slide-down 180ms cubic-bezier(0.2, 0, 0, 1) both',
                'toast-in':  'toast-in 200ms cubic-bezier(0.2, 0, 0, 1)',
            },
        },
    },

    plugins: [forms],
};
