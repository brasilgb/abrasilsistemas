import { Link } from '@inertiajs/react';

export function PublicBrand({ inverse = false }: { inverse?: boolean }) {
    return (
        <Link href="/" className="group flex items-center gap-3" aria-label="ABrasil Sistemas — início">
            <span className="relative grid size-11 place-items-center overflow-hidden rounded-xl bg-blue-600 shadow-lg shadow-blue-950/20">
                <span className="absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgba(255,255,255,.45),transparent_35%)]" />
                <span className="relative text-lg font-black tracking-[-0.08em] text-white">AB</span>
            </span>
            <span className="leading-none">
                <span className={`block text-[15px] font-extrabold tracking-[-0.02em] ${inverse ? 'text-white' : 'text-slate-950'}`}>
                    ABrasil
                </span>
                <span className="mt-1 block text-[9px] font-bold tracking-[0.18em] text-slate-500 uppercase">Sistemas</span>
            </span>
        </Link>
    );
}
