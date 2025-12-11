import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Mail, Loader2, ArrowLeft, CheckCircle } from 'lucide-react';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/presentation/components/ui/card';
import apiClient from '@/infrastructure/http/apiClient';
import { toast } from 'sonner';

export default function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isEmailSent, setIsEmailSent] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');

        if (!email) {
            setError('El correo electrónico es requerido');
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setError('Ingresa un correo electrónico válido');
            return;
        }

        setIsLoading(true);
        try {
            await apiClient.post('/password/forgot', { email });
            setIsEmailSent(true);
            toast.success('Correo enviado correctamente');
        } catch (error: any) {
            // Siempre mostramos éxito por seguridad (no revelar si email existe)
            setIsEmailSent(true);
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
                        {isEmailSent ? (
                            <CheckCircle className="w-10 h-10 text-green-500" />
                        ) : (
                            <Mail className="w-10 h-10" style={{ color: primaryColor }} />
                        )}
                    </div>
                    <h1 className="text-white text-2xl font-bold mb-2">
                        {isEmailSent ? '¡Correo Enviado!' : 'Recuperar Contraseña'}
                    </h1>
                    <p className="text-white opacity-90">
                        {isEmailSent
                            ? 'Revisa tu bandeja de entrada'
                            : 'Te enviaremos un enlace para restablecer tu contraseña'}
                    </p>
                </div>

                {/* Card */}
                <Card className="shadow-2xl">
                    {isEmailSent ? (
                        /* Success State */
                        <CardContent className="pt-6">
                            <div className="text-center space-y-4">
                                <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
                                    <CheckCircle className="w-8 h-8 text-green-600" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-lg text-gray-900">
                                        Revisa tu correo electrónico
                                    </h3>
                                    <p className="text-gray-500 mt-2">
                                        Si existe una cuenta con el correo <strong>{email}</strong>,
                                        recibirás un enlace para restablecer tu contraseña.
                                    </p>
                                </div>
                                <div className="bg-blue-50 rounded-lg p-4 text-sm text-left">
                                    <p className="text-blue-800">
                                        <strong>💡 Consejos:</strong>
                                    </p>
                                    <ul className="mt-2 space-y-1 text-blue-700">
                                        <li>• Revisa tu carpeta de spam</li>
                                        <li>• El enlace expira en 60 minutos</li>
                                        <li>• Solo puedes usarlo una vez</li>
                                    </ul>
                                </div>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => {
                                        setIsEmailSent(false);
                                        setEmail('');
                                    }}
                                >
                                    Enviar a otro correo
                                </Button>
                            </div>
                        </CardContent>
                    ) : (
                        /* Form State */
                        <>
                            <CardHeader>
                                <CardTitle>¿Olvidaste tu contraseña?</CardTitle>
                                <CardDescription>
                                    Ingresa tu correo electrónico y te enviaremos instrucciones
                                    para recuperar el acceso a tu cuenta.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div className="space-y-2">
                                        <Label htmlFor="email">Correo Electrónico</Label>
                                        <div className="relative">
                                            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                            <Input
                                                id="email"
                                                type="email"
                                                placeholder="tu@correo.com"
                                                value={email}
                                                onChange={(e) => {
                                                    setEmail(e.target.value);
                                                    if (error) setError('');
                                                }}
                                                className={`pl-10 h-11 ${error ? 'border-red-500' : ''}`}
                                                disabled={isLoading}
                                            />
                                        </div>
                                        {error && (
                                            <p className="text-sm text-red-500">{error}</p>
                                        )}
                                    </div>

                                    <Button
                                        type="submit"
                                        className="w-full h-11 text-white"
                                        style={{ backgroundColor: primaryColor }}
                                        disabled={isLoading}
                                    >
                                        {isLoading ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Enviando...
                                            </>
                                        ) : (
                                            <>
                                                <Mail className="mr-2 h-4 w-4" />
                                                Enviar Enlace de Recuperación
                                            </>
                                        )}
                                    </Button>
                                </form>
                            </CardContent>
                        </>
                    )}
                </Card>

                {/* Back to Login */}
                <div className="text-center mt-6">
                    <Link
                        to="/login"
                        className="inline-flex items-center text-white hover:underline"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Volver a Iniciar Sesión
                    </Link>
                </div>

                {/* Footer */}
                <p className="text-center text-white opacity-75 mt-8 text-sm">
                    © 2025 MiBoleta. Todos los derechos reservados.
                </p>
            </div>
        </div>
    );
}
