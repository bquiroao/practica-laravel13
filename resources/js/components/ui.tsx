import { forwardRef, useEffect, useId, useRef, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, ArrowLeft, CheckCircle2, Info, Loader2, X } from 'lucide-react';
import { cn } from '@/lib/cn';

/*
 * Componentes base con la estética de shadcn/ui. Cuando llegue el diseño de Stitch se ajustan aquí
 * y el cambio se propaga a todas las pantallas.
 */

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

const variants: Record<Variant, string> = {
    primary: 'bg-nido-700 text-white hover:bg-nido-800 disabled:bg-nido-300',
    secondary: 'bg-white text-nido-800 ring-1 ring-inset ring-nido-200 hover:bg-nido-50',
    ghost: 'text-nido-800 hover:bg-nido-50',
    danger: 'bg-critico text-white hover:brightness-95',
};

export const Button = forwardRef<HTMLButtonElement, ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant; loading?: boolean; size?: 'sm' | 'md' }>(
    function Button({ variant = 'primary', loading, size = 'md', className, children, disabled, ...props }, ref) {
        return (
            <button ref={ref} disabled={disabled || loading}
                className={cn('inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition disabled:cursor-not-allowed',
                    size === 'sm' ? 'h-8 px-3 text-sm' : 'h-10 px-4 text-sm', variants[variant], className)} {...props}>
                {loading && <Loader2 className="size-4 animate-spin" aria-hidden />}
                {children}
            </button>
        );
    },
);

export function Field({ label, error, hint, children, htmlFor }: { label: string; error?: string; hint?: string; children: ReactNode; htmlFor: string }) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={htmlFor} className="block text-sm font-semibold text-slate-700">{label}</label>
            {children}
            {hint && !error && <p className="text-xs text-slate-500">{hint}</p>}
            {error && <p className="text-xs font-medium text-red-700" role="alert">{error}</p>}
        </div>
    );
}

const control = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-500 focus:ring-2 focus:ring-nido-500 aria-[invalid=true]:ring-red-500';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input({ className, ...props }, ref) {
    return <input ref={ref} className={cn(control, 'h-10', className)} {...props} />;
});

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(function Select({ className, children, ...props }, ref) {
    return <select ref={ref} className={cn(control, 'h-10 pr-8', className)} {...props}>{children}</select>;
});

export function Card({ className, children }: { className?: string; children: ReactNode }) {
    return <section className={cn('rounded-[var(--radius-nido)] bg-white shadow-sm ring-1 ring-slate-200/70', className)}>{children}</section>;
}

const tones = {
    neutral: 'bg-slate-100 text-slate-700',
    nido: 'bg-nido-100 text-nido-800',
    ok: 'bg-green-100 text-green-800',
    warn: 'bg-amber-100 text-amber-800',
    critical: 'bg-red-100 text-red-800',
};

export function Badge({ tone = 'neutral', children }: { tone?: keyof typeof tones; children: ReactNode }) {
    return <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold', tones[tone])}>{children}</span>;
}

export function Alert({ tone = 'info', children }: { tone?: 'info' | 'error' | 'success'; children: ReactNode }) {
    const Icon = tone === 'error' ? AlertTriangle : tone === 'success' ? CheckCircle2 : Info;
    const style = tone === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : tone === 'success' ? 'bg-green-50 text-green-800 ring-green-200' : 'bg-nido-50 text-nido-800 ring-nido-200';
    return (
        <div role={tone === 'error' ? 'alert' : 'status'} className={cn('flex gap-2 rounded-lg px-3 py-2 text-sm ring-1', style)}>
            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
            <div>{children}</div>
        </div>
    );
}

export function PageHeader({ title, description, actions }: { title: string; description?: string; actions?: ReactNode }) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 className="text-2xl font-bold text-nido-900">{title}</h1>
                {description && <p className="mt-1 text-sm text-slate-600">{description}</p>}
            </div>
            {actions && <div className="flex gap-2">{actions}</div>}
        </div>
    );
}

export function Spinner({ label = 'Cargando…' }: { label?: string }) {
    return (
        <div className="flex items-center justify-center gap-2 py-10 text-sm text-slate-500" role="status">
            <Loader2 className="size-5 animate-spin" aria-hidden /> {label}
        </div>
    );
}

export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
    return (
        <div className="py-10 text-center">
            <p className="font-semibold text-slate-700">{title}</p>
            {children && <div className="mt-1 text-sm text-slate-500">{children}</div>}
        </div>
    );
}

export function Table({ children, caption }: { children: ReactNode; caption: string }) {
    return (
        // Región enfocable: una tabla ancha se puede desplazar con el teclado (WCAG 2.1.1).
        <div className="overflow-x-auto" tabIndex={0} role="region" aria-label={caption}>
            <table className="min-w-full divide-y divide-slate-200 text-sm">
                <caption className="sr-only">{caption}</caption>
                {children}
            </table>
        </div>
    );
}

export const th = 'px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500';
export const td = 'px-4 py-3 align-top text-slate-700';

/** Diálogo modal accesible con <dialog>: foco atrapado y Escape para cerrar. */
export function Dialog({ open, onClose, title, children }: { open: boolean; onClose: () => void; title: string; children: ReactNode }) {
    const ref = useRef<HTMLDialogElement>(null);
    const titleId = useId();
    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);
    return (
        <dialog ref={ref} aria-labelledby={titleId} onClose={onClose}
            className="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-[var(--radius-nido)] p-0 shadow-xl backdrop:bg-nido-900/40">
            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 id={titleId} className="text-lg font-bold text-nido-900">{title}</h2>
                <button type="button" onClick={onClose} className="rounded p-1 text-slate-500 hover:bg-slate-100" aria-label="Cerrar">
                    <X className="size-5" aria-hidden />
                </button>
            </div>
            <div className="px-5 py-4">{children}</div>
        </dialog>
    );
}

export function Logo({ className }: { className?: string }) {
    return <img src="/nido.svg" alt="" aria-hidden className={cn('size-9', className)} />;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function Textarea({ className, ...props }, ref) {
    return <textarea ref={ref} rows={3} className={cn(control, 'min-h-20', className)} {...props} />;
});

/** Enlace de regreso visible arriba del título: el usuario siempre sabe cómo volver. */
export function BackLink({ to, children }: { to: string; children: ReactNode }) {
    return (
        <Link to={to} className="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-nido-700 hover:underline">
            <ArrowLeft className="size-4" aria-hidden /> {children}
        </Link>
    );
}

/** Bloque de un formulario con título y una línea que explica para qué sirve. */
export function FormSection({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <fieldset className="grid gap-4 border-t border-slate-200 py-6 first:border-t-0 first:pt-0 md:grid-cols-3">
            <div>
                <legend className="font-bold text-nido-900">{title}</legend>
                {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
            </div>
            <div className="grid gap-4 sm:grid-cols-2 md:col-span-2">{children}</div>
        </fieldset>
    );
}
