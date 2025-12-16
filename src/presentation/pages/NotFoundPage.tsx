import { useNavigate } from "react-router-dom";
import { Home, ArrowLeft, Search, AlertTriangle } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent } from "@/presentation/components/ui/card";

export function NotFoundPage() {
    const navigate = useNavigate();

    const handleGoHome = () => {
        navigate("/");
    };

    const handleGoBack = () => {
        navigate(-1);
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 flex items-center justify-center p-4">
            <Card className="max-w-lg w-full shadow-xl border-0">
                <CardContent className="p-8 text-center">
                    {/* Ilustración */}
                    <div className="relative mb-6">
                        <div className="w-32 h-32 mx-auto bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center">
                            <Search className="w-16 h-16 text-blue-400" />
                        </div>
                        <div className="absolute top-0 right-1/4 -translate-y-2">
                            <AlertTriangle className="w-8 h-8 text-amber-500" />
                        </div>
                    </div>

                    {/* Código de error */}
                    <h1 className="text-8xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent mb-2">
                        404
                    </h1>

                    {/* Título */}
                    <h2 className="text-2xl font-semibold text-gray-800 mb-3">
                        Página no encontrada
                    </h2>

                    {/* Descripción */}
                    <p className="text-gray-500 mb-8 leading-relaxed">
                        Lo sentimos, la página que buscas no existe o ha sido movida.
                        <br />
                        Verifica la URL o regresa al inicio.
                    </p>

                    {/* Botones */}
                    <div className="flex flex-col sm:flex-row gap-3 justify-center">
                        <Button
                            variant="outline"
                            onClick={handleGoBack}
                            className="gap-2"
                        >
                            <ArrowLeft className="w-4 h-4" />
                            Volver atrás
                        </Button>
                        <Button
                            onClick={handleGoHome}
                            className="gap-2 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700"
                        >
                            <Home className="w-4 h-4" />
                            Ir al inicio
                        </Button>
                    </div>

                    {/* Links útiles */}
                    <div className="mt-8 pt-6 border-t border-gray-100">
                        <p className="text-sm text-gray-400">
                            ¿Necesitas ayuda?{" "}
                            <a
                                href="mailto:soporte@miboleta.com"
                                className="text-blue-600 hover:underline"
                            >
                                Contáctanos
                            </a>
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

export default NotFoundPage;
