import { Link } from '@inertiajs/react';

export function PublicBrand({ inverse = false }: { inverse?: boolean }) {
    return (
        <Link href="/" className="group flex items-center gap-3" aria-label="ABrasil Sistemas — início">
            <span className="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl shadow-lg shadow-blue-950/20">
                <img src="/images/logo_ab.png" alt="ABrasil Sistemas" className="size-full object-cover" />
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
