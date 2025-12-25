import { useNavigate, useRouteError, isRouteErrorResponse } from "react-router-dom";
import { useDocumentTitle } from "@/presentation/hooks";
import { Home, ArrowLeft, AlertTriangle, Bug, RefreshCw } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent } from "@/presentation/components/ui/card";

export function ErrorPage() {
    useDocumentTitle('Error');
    const navigate = useNavigate();
    const error = useRouteError();

    let errorMessage = "Ha ocurrido un error inesperado";
    let errorDetails = "Por favor, intenta de nuevo o contacta al soporte técnico.";
    let errorCode = "Error";

    // Parse the error to extract meaningful information
    if (isRouteErrorResponse(error)) {
        errorCode = error.status.toString();
        errorMessage = error.statusText || errorMessage;
        if (error.data?.message) {
            errorDetails = error.data.message;
        }
    } else if (error instanceof Error) {
        errorMessage = error.message;
        errorDetails = error.stack?.split('\n').slice(0, 3).join('\n') || errorDetails;
    }

    const handleGoHome = () => {
        navigate("/");
    };

    const handleGoBack = () => {
        navigate(-1);
    };

    const handleReload = () => {
        window.location.reload();
    };

    // Log error for debugging
    console.error("Application Error:", error);

    return (
        <div className="min-h-screen bg-[#F8FAFC] flex items-center justify-center p-6">
            <Card className="max-w-lg w-full">
                <CardContent className="p-8 text-center">
                    {/* Icon */}
                    <div className="inline-flex items-center justify-center w-20 h-20 bg-red-50 rounded-full mb-6">
                        <AlertTriangle className="w-10 h-10 text-red-600" />
                    </div>

                    {/* Error Code */}
                    <h1 className="text-5xl font-bold text-red-600 mb-2">{errorCode}</h1>

                    {/* Title */}
                    <h2 className="text-xl font-semibold text-gray-900 mb-2">
                        {errorMessage}
                    </h2>

                    {/* Description */}
                    <p className="text-[#64748B] mb-6">
                        {errorDetails}
                    </p>

                    {/* Suggestions */}
                    <div className="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                        <p className="text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                            <Bug className="w-4 h-4" />
                            ¿Qué puedes hacer?
                        </p>
                        <ul className="text-sm text-[#64748B] space-y-1">
                            <li>• Intenta recargar la página</li>
                            <li>• Verifica tu conexión a internet</li>
                            <li>• Si el problema persiste, contacta al soporte técnico</li>
                        </ul>
                    </div>

                    {/* Buttons */}
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
                            variant="outline"
                            onClick={handleReload}
                            className="gap-2"
                        >
                            <RefreshCw className="w-4 h-4" />
                            Recargar página
                        </Button>
                        <Button
                            onClick={handleGoHome}
                            className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF] text-white"
                        >
                            <Home className="w-4 h-4" />
                            Ir al inicio
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

export default ErrorPage;
