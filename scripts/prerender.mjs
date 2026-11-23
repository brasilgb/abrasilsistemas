/**
 * Gera o HTML inicial das páginas públicas em public/build/prerender (roda no fim do `npm run build`).
 * Usa só o que o projeto já tem (Vite e react-dom): nenhum processo Node fica em execução em produção.
 * Uma página que falhe interrompe o build, para não publicar HTML incompleto.
 */
import { mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { build } from 'vite';

const root = path.resolve(import.meta.dirname, '..');
const temp = path.join(root, 'node_modules/.prerender');
const out = path.join(root, 'public/build/prerender');

try {
    await build({
        root,
        configFile: false,
        publicDir: false,
        logLevel: 'warn',
        resolve: { alias: { '@': path.join(root, 'resources/js') } },
        build: {
            ssr: path.join(root, 'resources/js/prerender/entry.tsx'),
            outDir: temp,
            emptyOutDir: true,
            rollupOptions: { output: { entryFileNames: 'entry.mjs' } },
        },
    });

    const { targets, renderTarget } = await import(
        pathToFileURL(path.join(temp, 'entry.mjs')).href
    );

    await rm(out, { recursive: true, force: true });
    await mkdir(out, { recursive: true });

    for (const target of targets) {
        const html = await renderTarget(target);

        if (!html.includes('<footer')) {
            throw new Error(`Pré-renderização incompleta: ${target.path}`);
        }

        const name = target.path === '/' ? 'home' : target.path.slice(1).replaceAll('/', '__');
        // JSON, e não .html: o diretório é público e não deve virar uma segunda cópia indexável.
        await writeFile(path.join(out, `${name}.json`), JSON.stringify({ component: target.component, html }));
    }

    console.log(`✓ ${targets.length} páginas pré-renderizadas em public/build/prerender`);
} finally {
    await rm(temp, { recursive: true, force: true });
}
