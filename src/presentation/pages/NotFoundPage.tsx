import { useNavigate } from "react-router-dom";
import { Home, ArrowLeft, FileQuestion, Search } from "lucide-react";
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
        <div className="min-h-screen bg-[#F8FAFC] flex items-center justify-center p-6">
            <Card className="max-w-lg w-full">
                <CardContent className="p-8 text-center">
                    {/* Icon */}
                    <div className="inline-flex items-center justify-center w-20 h-20 bg-blue-50 rounded-full mb-6">
                        <FileQuestion className="w-10 h-10 text-[#2563EB]" />
                    </div>

                    {/* Error Code */}
                    <h1 className="text-5xl font-bold text-[#2563EB] mb-2">404</h1>

                    {/* Title */}
                    <h2 className="text-xl font-semibold text-gray-900 mb-2">
                        Página no encontrada
                    </h2>

                    {/* Description */}
                    <p className="text-[#64748B] mb-6">
                        La página que buscas no existe o ha sido movida.
                    </p>

                    {/* Suggestions */}
                    <div className="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                        <p className="text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                            <Search className="w-4 h-4" />
                            Sugerencias:
                        </p>
                        <ul className="text-sm text-[#64748B] space-y-1">
                            <li>• Verifica que la URL esté escrita correctamente</li>
                            <li>• Es posible que el contenido haya sido eliminado</li>
                            <li>• Intenta navegar desde el menú principal</li>
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

export default NotFoundPage;
