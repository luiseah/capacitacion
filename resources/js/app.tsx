import { createInertiaApp } from '@inertiajs/react';
import { QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { createQueryClient } from '@/lib/query-client';
// Plugs the agreements into the mobility pages.
import '@agreements/register';

// Pages of the academic-mobility package (dev mode: read from packages/ through the
// `@academic` alias). Inertia names `academic-mobility/<path>` map to `pages/<path>`.
const PACKAGE_PAGE_PREFIX = 'academic-mobility/';
const hostPages = import.meta.glob('./pages/**/*.tsx');
const packagePages = import.meta.glob(
    '../../packages/academic-mobility/resources/js/pages/**/*.tsx',
);

// Pages of the agreements package: Inertia names `agreements/<page>` live in its `pages/agreements/`.
const AGREEMENTS_PAGE_PREFIX = 'agreements/';
const agreementPages = import.meta.glob(
    '../../packages/agreements/resources/js/pages/**/*.tsx',
);

const queryClient = createQueryClient();
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    resolve: async (name) => {
        const importer = name.startsWith(PACKAGE_PAGE_PREFIX)
            ? packagePages[
                  `../../packages/academic-mobility/resources/js/pages/${name.slice(PACKAGE_PAGE_PREFIX.length)}.tsx`
              ]
            : name.startsWith(AGREEMENTS_PAGE_PREFIX)
              ? agreementPages[`../../packages/agreements/resources/js/pages/${name}.tsx`]
              : hostPages[`./pages/${name}.tsx`];

        if (!importer) {
            throw new Error(`Page not found: ${name}`);
        }

        const module = (await importer()) as { default: never };

        return module.default;
    },
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <QueryClientProvider client={queryClient}>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </QueryClientProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
