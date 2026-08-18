import { Alert, AlertDescription } from '@/presentation/components/ui/alert';
import { AlertTriangle } from 'lucide-react';

interface FieldErrorProps {
    message?: string;
}

/**
 * Mensaje de error bajo un input. Sustituye las copias sueltas de
 * `<p className="text-sm text-red-500">{message}</p>` repetidas por el
 * formulario para que todas se vean y se mantengan igual.
 */
export function FieldError({ message }: FieldErrorProps) {
    if (!message) return null;

    return <p className="text-sm text-red-500">{message}</p>;
}

interface FormErrorSummaryProps {
    messages: string[];
}

/**
 * Resumen de errores sin control asignado (p.ej. un campo anidado que ningún
 * input del formulario pinta todavía). Se pone encima del formulario para
 * que ese mensaje no muera en silencio: ver useFormErrors.applyApiError.
 */
export function FormErrorSummary({ messages }: FormErrorSummaryProps) {
    if (!messages || messages.length === 0) return null;

    return (
        <Alert variant="destructive">
            <AlertTriangle className="h-4 w-4" />
            <AlertDescription>
                <ul className="list-disc pl-4 space-y-0.5">
                    {messages.map((message, index) => (
                        <li key={index}>{message}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
