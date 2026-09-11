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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // ============================================================
                // Design tokens — identidade visual "Amor a Dois" (Etapa 5)
                // Fonte unica de verdade para as cores da marca. Nunca usar
                // hex direto nas views: usar sempre as classes bg-brand-*,
                // text-brand-*, border-brand-* geradas a partir daqui.
                // ============================================================
                brand: {
                    // --- paleta oficial (nomes da marca) ---
                    terracota: '#953C36',
                    vinho: '#743338',
                    ameixa: '#62304F',
                    'rose-antigo': '#C98C86',
                    'rosa-nude': '#E8C8C2',
                    creme: '#F7F0E8',
                    'marrom-cafe': '#3C2928',
                    branco: '#FFFDFC',

                    // --- aliases semanticos (funcao de cada cor, Etapa 5 #4) ---
                    primary: '#953C36', // terracota
                    'primary-dark': '#743338', // vinho (hover/pressed)
                    accent: '#62304F', // ameixa
                    border: '#C98C86', // rose antigo
                    soft: '#E8C8C2', // rosa nude (fundos suaves)
                    background: '#F7F0E8', // creme (fundo principal)
                    surface: '#FFFDFC', // branco quente (cards)
                    text: '#3C2928', // marrom cafe (texto principal)
                    'text-muted': '#8A6F63', // variacao mais clara do marrom cafe

                    // --- estados (nao dependem so de cor - ver componentes Badge/Alert) ---
                    success: '#3F6B4B',
                    'success-soft': '#E4EEE6',
                    warning: '#A3701C',
                    'warning-soft': '#F5E9D6',
                    danger: '#B3261E',
                    'danger-soft': '#F6E0DE',
                    info: '#3D5A73',
                    'info-soft': '#E1E8ED',
                },
            },
            boxShadow: {
                // sombras suaves com tom quente (marrom-cafe) em vez de preto puro
                'brand-sm': '0 1px 2px 0 rgba(60, 41, 40, 0.06)',
                'brand-md': '0 4px 16px -2px rgba(60, 41, 40, 0.10)',
                'brand-lg': '0 12px 32px -6px rgba(60, 41, 40, 0.14)',
            },
            borderRadius: {
                'brand-sm': '0.5rem',
                'brand-md': '0.875rem',
                'brand-lg': '1.25rem',
            },
        },
    },

    plugins: [forms],
};
