import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { Lock, Loader2, CheckCircle, XCircle, ArrowLeft } from 'lucide-react';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/presentation/components/ui/card';
import apiClient from '@/infrastructure/http/apiClient';
import { toast } from 'sonner';

export default function ResetPasswordPage() {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();

    const token = searchParams.get('token');
    const email = searchParams.get('email');

    const [isLoading, setIsLoading] = useState(false);
    const [isSuccess, setIsSuccess] = useState(false);
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [globalError, setGlobalError] = useState('');

    // Verificar que tenemos token y email
    useEffect(() => {
        if (!token || !email) {
            setGlobalError('Enlace de recuperación inválido o incompleto.');
        }
    }, [token, email]);

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (!password) {
            newErrors.password = 'La contraseña es requerida';
        } else if (password.length < 8) {
            newErrors.password = 'La contraseña debe tener al menos 8 caracteres';
        }

        if (password !== passwordConfirmation) {
            newErrors.password_confirmation = 'Las contraseñas no coinciden';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setGlobalError('');

        if (!validateForm()) return;

        setIsLoading(true);
        try {
            await apiClient.post('/password/reset', {
                email,
                token,
                password,
                password_confirmation: passwordConfirmation,
            });

            setIsSuccess(true);
            toast.success('¡Contraseña actualizada correctamente!');
        } catch (error: any) {
            const message = error.response?.data?.message || 'Error al restablecer la contraseña';
            setGlobalError(message);
            toast.error(message);
        } finally {
            setIsLoading(false);
        }
    };

    // Colores del tema
    const gradientFrom = '#2563EB';
    const gradientTo = '#1E40AF';
    const primaryColor = '#2563EB';

    return (
        <div
            className="min-h-screen bg-gradient-to-br flex items-center justify-center p-4"
            style={{
                background: `linear-gradient(to bottom right, ${gradientFrom}, ${gradientTo})`,
            }}
        >
            <div className="w-full max-w-md">
                {/* Header */}
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center w-20 h-20 bg-white rounded-2xl shadow-lg mb-4">
                        {isSuccess ? (
                            <CheckCircle className="w-10 h-10 text-green-500" />
                        ) : globalError ? (
                            <XCircle className="w-10 h-10 text-red-500" />
                        ) : (
                            <Lock className="w-10 h-10" style={{ color: primaryColor }} />
                        )}
                    </div>
                    <h1 className="text-white text-2xl font-bold mb-2">
                        {isSuccess ? '¡Listo!' : 'Nueva Contraseña'}
                    </h1>
                    <p className="text-white opacity-90">
                        {isSuccess
                            ? 'Tu contraseña ha sido actualizada'
                            : 'Establece tu nueva contraseña'}
                    </p>
                </div>

                {/* Card */}
                <Card className="shadow-2xl">
                    {isSuccess ? (
                        /* Success State */
                        <CardContent className="pt-6">
                            <div className="text-center space-y-4">
                                <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
                                    <CheckCircle className="w-8 h-8 text-green-600" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-lg text-gray-900">
                                        ¡Contraseña Actualizada!
                                    </h3>
                                    <p className="text-gray-500 mt-2">
                                        Tu contraseña ha sido restablecida correctamente.
                                        Ya puedes iniciar sesión con tu nueva contraseña.
                                    </p>
                                </div>
                                <Button
                                    className="w-full text-white"
                                    style={{ backgroundColor: primaryColor }}
                                    onClick={() => navigate('/login')}
                                >
                                    Ir a Iniciar Sesión
                                </Button>
                            </div>
                        </CardContent>
                    ) : globalError && !token ? (
                        /* Error State - Invalid Link */
                        <CardContent className="pt-6">
                            <div className="text-center space-y-4">
                                <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto">
                                    <XCircle className="w-8 h-8 text-red-600" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-lg text-gray-900">
                                        Enlace Inválido
                                    </h3>
                                    <p className="text-gray-500 mt-2">
                                        {globalError}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => navigate('/forgot-password')}
                                >
                                    Solicitar Nuevo Enlace
                                </Button>
                            </div>
                        </CardContent>
                    ) : (
                        /* Form State */
                        <>
                            <CardHeader>
                                <CardTitle>Restablecer Contraseña</CardTitle>
                                <CardDescription>
                                    Ingresa tu nueva contraseña para la cuenta <strong>{email}</strong>
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {globalError && (
                                    <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                                        {globalError}
                                    </div>
                                )}

                                <form onSubmit={handleSubmit} className="space-y-6">
                                    {/* Password */}
                                    <div className="space-y-2">
                                        <Label htmlFor="password">Nueva Contraseña</Label>
                                        <div className="relative">
                                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                            <Input
                                                id="password"
                                                type="password"
                                                placeholder="Mínimo 8 caracteres"
                                                value={password}
                                                onChange={(e) => {
                                                    setPassword(e.target.value);
                                                    if (errors.password) setErrors(prev => ({ ...prev, password: '' }));
                                                }}
                                                className={`pl-10 h-11 ${errors.password ? 'border-red-500' : ''}`}
                                                disabled={isLoading}
                                            />
                                        </div>
                                        {errors.password && (
                                            <p className="text-sm text-red-500">{errors.password}</p>
                                        )}
                                    </div>

                                    {/* Confirm Password */}
                                    <div className="space-y-2">
                                        <Label htmlFor="password_confirmation">Confirmar Contraseña</Label>
                                        <div className="relative">
                                            <CheckCircle className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                            <Input
                                                id="password_confirmation"
                                                type="password"
                                                placeholder="Repite la contraseña"
                                                value={passwordConfirmation}
                                                onChange={(e) => {
                                                    setPasswordConfirmation(e.target.value);
                                                    if (errors.password_confirmation) setErrors(prev => ({ ...prev, password_confirmation: '' }));
                                                }}
                                                className={`pl-10 h-11 ${errors.password_confirmation ? 'border-red-500' : ''}`}
                                                disabled={isLoading}
                                            />
                                        </div>
                                        {errors.password_confirmation && (
                                            <p className="text-sm text-red-500">{errors.password_confirmation}</p>
                                        )}
                                    </div>

                                    {/* Password Validation Feedback */}
                                    <div className="bg-blue-50 rounded-lg p-4 text-sm">
                                        <p className="font-medium text-blue-900 mb-2">Requisitos:</p>
                                        <ul className="space-y-1 text-blue-700">
                                            <li className={`flex items-center gap-2 ${password.length >= 8 ? 'text-green-600' : ''}`}>
                                                <span className={`w-4 h-4 rounded-full flex items-center justify-center text-xs ${password.length >= 8 ? 'bg-green-100' : 'bg-gray-100'}`}>
                                                    {password.length >= 8 ? '✓' : '•'}
                                                </span>
                                                Mínimo 8 caracteres
                                            </li>
                                            <li className={`flex items-center gap-2 ${password === passwordConfirmation && password.length > 0 ? 'text-green-600' : ''}`}>
                                                <span className={`w-4 h-4 rounded-full flex items-center justify-center text-xs ${password === passwordConfirmation && password.length > 0 ? 'bg-green-100' : 'bg-gray-100'}`}>
                                                    {password === passwordConfirmation && password.length > 0 ? '✓' : '•'}
                                                </span>
                                                Las contraseñas coinciden
                                            </li>
                                        </ul>
                                    </div>

                                    {/* Submit Button */}
                                    <Button
                                        type="submit"
                                        className="w-full h-11 text-white"
                                        style={{ backgroundColor: primaryColor }}
                                        disabled={isLoading}
                                    >
                                        {isLoading ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Actualizando...
                                            </>
                                        ) : (
                                            'Restablecer Contraseña'
                                        )}
                                    </Button>
                                </form>
                            </CardContent>
                        </>
                    )}
                </Card>

                {/* Back to Login */}
                {!isSuccess && (
                    <div className="text-center mt-6">
                        <Link
                            to="/login"
                            className="inline-flex items-center text-white hover:underline"
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Volver a Iniciar Sesión
                        </Link>
                    </div>
                )}

                {/* Footer */}
                <p className="text-center text-white opacity-75 mt-8 text-sm">
                    © 2025 MiBoleta. Todos los derechos reservados.
                </p>
            </div>
        </div>
    );
}
