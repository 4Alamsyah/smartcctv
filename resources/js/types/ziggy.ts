import { Config, RouteParams } from 'ziggy-js';

declare global {
    function route(): Config;
    function route(name: string, params?: RouteParams<typeof name> | string | number | undefined, absolute?: boolean): string;
}

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        route: typeof route;
    }
}
