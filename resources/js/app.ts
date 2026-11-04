import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import FloatingVue from 'floating-vue'; // Importa el plugin
import 'floating-vue/dist/style.css';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { vigilarSesionNavegador } from './lib/sesionNavegador';

const appName = import.meta.env.VITE_APP_NAME || 'Macondas';

createInertiaApp({
    title: (title: string): string => `${title} - ${appName}`,
    resolve: (name: string) =>
        resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>(['./pages/**/*.vue', '!./pages/generic/**'])),
    setup: function ({ el, App, props, plugin }): void {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(FloatingVue)
            .component('vSelect', vSelect)
            .use(ZiggyVue)
            .mixin({
                methods: {
                    can: function (permissions: any) {
                        const allPermissions = this.$page.props.auth.can;
                        let hasPermission = false;
                        permissions.forEach(function (item: any): void {
                            if (allPermissions[item]) hasPermission = true;
                        });
                        return hasPermission;
                    },
                    lang: function () {
                        const translations = this.$page.props.translations ?? {};
                        const translate = (key: string, defaultValue: string = '') => {
                            if (!key) return defaultValue;
                            const keys = key.split('.');
                            let value: any = translations;

                            for (const k of keys) {
                                if (!value || typeof value !== 'object' || !value[k]) return defaultValue;
                                value = value[k];
                            }

                            return value || defaultValue;
                        };

                        return {
                            locale: this.$page.props.locale ?? 'es',
                            translations,
                            t: function (key: string, defaultValue: string = '') {
                                if (key === 'app.t') {
                                    return translate;
                                }
                                return translate(key, defaultValue);
                            },
                        };
                    },
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
vigilarSesionNavegador();
