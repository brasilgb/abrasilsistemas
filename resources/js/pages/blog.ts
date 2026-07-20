import { route } from 'ziggy-js';

type Params = Record<string, unknown> | undefined;

function blog(path: string, params?: Params) {
    return route(`blog.${path}`, params);
}

export function index(params?: Params) {
    return blog('index', params);
}