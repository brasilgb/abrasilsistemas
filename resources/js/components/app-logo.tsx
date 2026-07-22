export default function AppLogo() {
    return (
        <>
            <img
                src="/images/logo_ab.png"
                alt="Logo da ABSistemas"
                className="size-9 rounded-lg border border-sidebar-border bg-white object-contain shadow-sm"
            />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    ABSistemas
                </span>
            </div>
        </>
    );
}
