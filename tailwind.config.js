import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Outfit', 'sans-serif'],
                mono: ['JetBrains Mono', 'Fira Code', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                primary: {
                    DEFAULT: '#009FE3',
                    50:  '#EBF8FF',
                    100: '#D1F0FF',
                    200: '#A3E1FF',
                    300: '#5CC8FF',
                    400: '#009FE3',
                    500: '#007BB0',
                    600: '#006894',
                    700: '#005F8A',
                    800: '#004D6F',
                    900: '#003A54',
                },
                secondary: {
                    DEFAULT: '#A64B9D',
                    50:  '#FBF5FB',
                    100: '#F5E8F4',
                    200: '#E8CBE6',
                    300: '#D4A0D0',
                    400: '#A64B9D',
                    500: '#8B3082',
                    600: '#6B21A8',
                },
                dark: '#0F172A',
            },
            boxShadow: {
                'apple-xs':  '0 1px 2px 0 rgba(0, 0, 0, 0.04)',
                'apple-sm':  '0 2px 8px -1px rgba(0, 0, 0, 0.06), 0 1px 3px 0 rgba(0, 0, 0, 0.04)',
                'apple-md':  '0 8px 24px -4px rgba(0, 0, 0, 0.07), 0 2px 6px -1px rgba(0, 0, 0, 0.04)',
                'apple-lg':  '0 20px 48px -8px rgba(0, 0, 0, 0.10), 0 4px 12px -2px rgba(0, 0, 0, 0.05)',
                'apple-float': '0 24px 60px -12px rgba(0, 0, 0, 0.16), 0 8px 24px -6px rgba(0, 0, 0, 0.08)',
                'apple-glass': '0 8px 32px 0 rgba(0, 0, 0, 0.08)',
                'soft':     '0 10px 40px -10px rgba(0,0,0,0.08)',
                'glass':    '0 4px 30px rgba(0, 0, 0, 0.1)',
                'elevated': '0 20px 60px -20px rgba(0,0,0,0.12)',
                'inner-sm': 'inset 0 1px 2px rgba(0, 0, 0, 0.06)',
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.5rem',
                '4xl': '2rem',
                '5xl': '2.5rem',
            },
            transitionTimingFunction: {
                'spring': 'cubic-bezier(0.16, 1, 0.3, 1)',
                'spring-bouncy': 'cubic-bezier(0.34, 1.56, 0.64, 1)',
            },
            animation: {
                'fade-in-up':  'fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) both',
                'fade-in':     'fadeIn 0.25s ease both',
                'scale-in':    'scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) both',
                'slide-left':  'slideLeft 0.3s cubic-bezier(0.16, 1, 0.3, 1) both',
                'slide-right': 'slideRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) both',
                'pulse-soft':  'pulseSoft 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            },
            keyframes: {
                fadeInUp:   { from: { opacity: '0', transform: 'translateY(12px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
                fadeIn:     { from: { opacity: '0' }, to: { opacity: '1' } },
                scaleIn:    { from: { opacity: '0', transform: 'scale(0.97)' }, to: { opacity: '1', transform: 'scale(1)' } },
                slideLeft:  { from: { opacity: '0', transform: 'translateX(16px)' }, to: { opacity: '1', transform: 'translateX(0)' } },
                slideRight: { from: { opacity: '0', transform: 'translateX(-16px)' }, to: { opacity: '1', transform: 'translateX(0)' } },
                pulseSoft:  { '0%, 100%': { opacity: '1' }, '50%': { opacity: '0.5' } },
            },
        },
    },

    plugins: [forms],
};
