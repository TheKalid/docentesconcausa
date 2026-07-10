import Link from "next/link";

export function AuthShell({
  title,
  subtitle,
  children,
  footer,
}: {
  title: string;
  subtitle?: string;
  children: React.ReactNode;
  footer?: React.ReactNode;
}) {
  return (
    <main className="flex flex-1 items-center justify-center px-5 py-16">
      <div className="w-full max-w-md rounded-[12px] bg-graphite p-8 shadow-[var(--shadow-card)] sm:p-10">
        <Link
          href="/"
          className="mb-8 flex items-center justify-center gap-3 text-[14px] font-[480] tracking-[-0.32px] text-snow"
        >
          <img
            src="/logo-header.png"
            alt="Docentes con causa"
            width={44}
            height={44}
            className="h-11 w-11 shrink-0 rounded-[10px] object-contain"
            decoding="async"
          />
          <span>Docentes con causa</span>
        </Link>

        <h1 className="text-center text-[32px] font-[400] leading-[1.25] tracking-[-0.64px] text-snow">
          {title}
        </h1>
        {subtitle && (
          <p className="mt-2 text-center text-[14px] tracking-[-0.32px] text-fog">
            {subtitle}
          </p>
        )}

        <div className="mt-8">{children}</div>

        {footer && (
          <div className="mt-6 text-center text-[13px] tracking-[-0.26px] text-fog">
            {footer}
          </div>
        )}
      </div>
    </main>
  );
}
