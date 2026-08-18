import { useState } from 'react';
import { useDocumentTitle } from '@/presentation/hooks';
import { useNavigate } from 'react-router-dom';
import { Lock, Loader2, Shield, CheckCircle } from 'lucide-react';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/presentation/components/ui/card';
import { useAuthStore } from '@/presentation/stores/authStore';
import apiClient from '@/infrastructure/http/apiClient';
import { useFormErrors } from '@/presentation/hooks/useFormErrors';
import { showApiError } from '@/presentation/utils/showApiError';
import { FieldError, FormErrorSummary } from '@/presentation/components/shared/FieldError';
import { toast } from 'sonner';

export default function ForceChangePasswordPage() {
    useDocumentTitle('Cambiar Contraseña');
    const navigate = useNavigate();
    const { user, me } = useAuthStore();
    const [isLoading, setIsLoading] = useState(false);
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const { errors, formErrors, setErrors, applyApiError } = useFormErrors({
        knownFields: ['password', 'password_confirmation'],
    });

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

        if (!validateForm()) return;

        setIsLoading(true);
        try {
            await apiClient.post('/password/force-change', {
                password,
                password_confirmation: passwordConfirmation,
            });

            // Reload user data (now must_change_password should be false)
            await me();

            toast.success('¡Contraseña actualizada correctamente!');
            navigate('/');
        } catch (error) {
            const apiError = applyApiError(error);
            showApiError(apiError);
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
                        <Shield className="w-10 h-10" style={{ color: primaryColor }} />
                    </div>
                    <h1 className="text-white text-2xl font-bold mb-2">
                        Actualiza tu Contraseña
                    </h1>
                    <p className="text-white opacity-90">
                        Por seguridad, debes establecer una nueva contraseña
                    </p>
                </div>

                {/* Card */}
                <Card className="shadow-2xl">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Lock className="h-5 w-5" />
                            Nueva Contraseña
                        </CardTitle>
                        <CardDescription>
                            Hola <strong>{user?.name || 'Usuario'}</strong>, esta es tu primera vez
                            iniciando sesión. Por favor, establece una contraseña segura.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <FormErrorSummary messages={formErrors} />

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
                                            if (errors.password) setErrors({ ...errors, password: '' });
                                        }}
                                        className={`pl-10 h-11 ${errors.password ? 'border-red-500' : ''}`}
                                        disabled={isLoading}
                                    />
                                </div>
                                <FieldError message={errors.password} />
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
                                            if (errors.password_confirmation) setErrors({ ...errors, password_confirmation: '' });
                                        }}
                                        className={`pl-10 h-11 ${errors.password_confirmation ? 'border-red-500' : ''}`}
                                        disabled={isLoading}
                                    />
                                </div>
                                <FieldError message={errors.password_confirmation} />
                            </div>

                            {/* Password Requirements */}
                            <div className="bg-blue-50 rounded-lg p-4 text-sm">
                                <p className="font-medium text-blue-900 mb-2">Requisitos de contraseña:</p>
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
                                    <>
                                        <Shield className="mr-2 h-4 w-4" />
                                        Establecer Contraseña
                                    </>
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Footer */}
                <p className="text-center text-white opacity-75 mt-8 text-sm">
                    © 2025 MiBoleta. Todos los derechos reservados.
                </p>
            </div>
        </div>
    );
}
