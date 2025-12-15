import { Loader2 } from "lucide-react";

/**
 * PageLoader - Spinner de carga para lazy loading de páginas
 */
export function PageLoader() {
    return (
        <div className="flex items-center justify-center min-h-[50vh]">
            <div className="text-center">
                <Loader2 className="w-8 h-8 animate-spin text-blue-600 mx-auto mb-3" />
                <p className="text-sm text-gray-500">Cargando...</p>
            </div>
        </div>
    );
}

export default PageLoader;
