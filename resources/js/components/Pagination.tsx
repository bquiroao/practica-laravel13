import { Button } from '@/components/ui';
import type { Paginated } from '@/lib/api';

export function Pagination({ meta, onPage }: { meta: Paginated<unknown>['meta']; onPage: (page: number) => void }) {
    if (meta.last_page <= 1) return null;
    return (
        <nav className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm" aria-label="Paginación">
            <span className="text-slate-600">Página {meta.current_page} de {meta.last_page} · {meta.total} registros</span>
            <div className="flex gap-2">
                <Button size="sm" variant="secondary" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Anterior</Button>
                <Button size="sm" variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Siguiente</Button>
            </div>
        </nav>
    );
}
