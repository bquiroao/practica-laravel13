import { useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { Alert, Button, Field, Input, Logo, Select } from '@/components/ui';

const schema = z.object({
    hospital: z.string().min(1, 'Seleccione el hospital.'),
    email: z.string().trim().min(1, 'Ingrese su correo.').email('Correo inválido.'),
    password: z.string().min(1, 'Ingrese su contraseña.'),
});
type Form = z.infer<typeof schema>;

const LAST_HOSPITAL = 'nido.ultimo-hospital';

function lastHospital(): string {
    try {
        return localStorage.getItem(LAST_HOSPITAL) ?? '';
    } catch {
        return '';
    }
}

export function LoginPage() {
    const { session, login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [error, setError] = useState<string | null>(null);
    const hospitals = useQuery({
        queryKey: ['hospitales'],
        queryFn: async () => (await api.get<{ data: { id: string; name: string }[] }>('/hospitales')).data.data,
    });
    const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<Form>({
        resolver: zodResolver(schema),
        defaultValues: { hospital: lastHospital(), email: '', password: '' },
    });

    if (session) return <Navigate to="/inicio" replace />;

    const onSubmit = async (form: Form) => {
        setError(null);
        try {
            await login(form.hospital, form.email, form.password);
            try {
                localStorage.setItem(LAST_HOSPITAL, form.hospital);
            } catch {
                /* preferencia opcional */
            }
            navigate((location.state as { from?: string } | null)?.from ?? '/inicio', { replace: true });
        } catch (e) {
            setError(errorMessage(e));
        }
    };

    return (
        <div className="grid min-h-screen lg:grid-cols-2">
            <div className="relative hidden flex-col justify-between overflow-hidden bg-nido-700 p-12 text-white lg:flex">
                <div className="flex items-center gap-3">
                    <Logo className="size-11" />
                    <span className="text-xl font-extrabold">Hospital Nido</span>
                </div>
                <div className="relative z-10 max-w-md">
                    <p className="text-4xl font-extrabold leading-tight">Protección y cuidado para cada paciente.</p>
                    <p className="mt-4 text-nido-100">Sistema hospitalario integrado para la red de hospitales Nido.</p>
                </div>
                <svg aria-hidden viewBox="0 0 400 200" className="absolute -bottom-6 -right-10 w-[34rem] text-salvia/30">
                    <path d="M20 60c0 80 80 120 180 120s180-40 180-120" fill="none" stroke="currentColor" strokeWidth="14" strokeLinecap="round" />
                    <path d="M60 90c25 40 75 60 140 60s115-20 140-60" fill="none" stroke="currentColor" strokeWidth="10" strokeLinecap="round" />
                </svg>
                <p className="relative z-10 text-sm text-nido-200">© {new Date().getFullYear()} Red Hospital Nido</p>
            </div>

            <div className="flex items-center justify-center px-4 py-12">
                <div className="w-full max-w-sm">
                    <div className="mb-8 flex items-center gap-3 lg:hidden">
                        <Logo className="size-11" />
                        <span className="text-xl font-extrabold text-nido-800">Hospital Nido</span>
                    </div>
                    <h1 className="text-2xl font-bold text-nido-900">Iniciar sesión</h1>
                    <p className="mt-1 text-sm text-slate-600">Ingrese con su cuenta del hospital.</p>

                    <form onSubmit={handleSubmit(onSubmit)} noValidate className="mt-8 space-y-5">
                        {error && <Alert tone="error">{error}</Alert>}
                        {hospitals.isError && <Alert tone="error">No se pudo cargar la lista de hospitales.</Alert>}
                        <Field label="Hospital" htmlFor="hospital" error={errors.hospital?.message}>
                            <Select id="hospital" aria-invalid={Boolean(errors.hospital)} {...register('hospital')}>
                                <option value="">{hospitals.isLoading ? 'Cargando…' : 'Seleccione…'}</option>
                                {hospitals.data?.map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}
                            </Select>
                        </Field>
                        <Field label="Correo" htmlFor="email" error={errors.email?.message}>
                            <Input id="email" type="email" autoComplete="username" aria-invalid={Boolean(errors.email)} {...register('email')} />
                        </Field>
                        <Field label="Contraseña" htmlFor="password" error={errors.password?.message}>
                            <Input id="password" type="password" autoComplete="current-password" aria-invalid={Boolean(errors.password)} {...register('password')} />
                        </Field>
                        <Button type="submit" className="w-full" loading={isSubmitting}>Entrar</Button>
                    </form>

                    {import.meta.env.DEV && (
                        <div className="mt-8 rounded-lg bg-white p-3 text-xs text-slate-600 ring-1 ring-slate-200">
                            <p className="font-semibold text-slate-700">Usuarios de demostración (solo desarrollo)</p>
                            <p className="mt-1">admin@nido.test, admin.hospital@nido.test, medico@nido.test, enfermeria@nido.test… · contraseña <code>NidoDemo2026</code></p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
