import { useEffect } from 'react';

/**
 * Hook para actualizar el título de la página dinámicamente
 * Mejora SEO y experiencia de usuario mostrando contexto en el tab del navegador
 * 
 * @param title - Título de la página actual
 * @param suffix - Sufijo opcional, por defecto "MiBoleta"
 */
export function useDocumentTitle(title: string, suffix: string = 'MiBoleta') {
    useEffect(() => {
        const previousTitle = document.title;
        document.title = title ? `${title} | ${suffix}` : suffix;

        return () => {
            document.title = previousTitle;
        };
    }, [title, suffix]);
}

export default useDocumentTitle;
