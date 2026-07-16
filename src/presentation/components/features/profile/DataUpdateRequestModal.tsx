import { useState } from "react";
import { FileEdit, Loader2, Mail } from "lucide-react";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/presentation/components/ui/dialog";
import { Button } from "@/presentation/components/ui/button";
import { Textarea } from "@/presentation/components/ui/textarea";
import { Label } from "@/presentation/components/ui/label";
import apiClient, { getErrorMessage } from "@/infrastructure/http/apiClient";
import { toast } from "sonner";

const MESSAGE_MAX_LENGTH = 1000;

interface DataUpdateRequestModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function DataUpdateRequestModal({ open, onOpenChange }: DataUpdateRequestModalProps) {
    const [message, setMessage] = useState("");
    const [error, setError] = useState("");
    const [isLoading, setIsLoading] = useState(false);

    const resetState = () => {
        setMessage("");
        setError("");
    };

    const handleClose = (nextOpen: boolean) => {
        if (isLoading) return;
        onOpenChange(nextOpen);
        if (!nextOpen) resetState();
    };

    const handleSubmit = async () => {
        const trimmed = message.trim();

        if (!trimmed) {
            setError("Debes indicar qué deseas actualizar");
            return;
        }

        if (trimmed.length > MESSAGE_MAX_LENGTH) {
            setError(`El mensaje no puede exceder ${MESSAGE_MAX_LENGTH} caracteres`);
            return;
        }

        setError("");
        setIsLoading(true);
        try {
            await apiClient.post("/profile/request-data-update", { message: trimmed });

            toast.success("Tu solicitud fue enviada correctamente. El administrador de tu empresa la recibirá por correo.");
            onOpenChange(false);
            resetState();
        } catch (err) {
            toast.error(getErrorMessage(err));
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-[#1E40AF]">
                        <FileEdit className="w-5 h-5" />
                        Solicitar Actualización de Datos
                    </DialogTitle>
                    <DialogDescription>
                        Describe qué datos deseas actualizar. Se enviará un correo al administrador de tu empresa
                        para que gestione el cambio.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="flex items-start gap-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <Mail className="w-4 h-4 text-[#2563EB] mt-0.5 flex-shrink-0" />
                        <p className="text-xs text-blue-800">
                            Esta solicitud no aplica cambios automáticamente; solo notifica al administrador para que
                            los realice.
                        </p>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="data-update-message">
                            Mensaje <span className="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="data-update-message"
                            placeholder="Ej: Necesito actualizar mi número de teléfono y mi dirección..."
                            value={message}
                            onChange={(e) => {
                                const value = e.target.value.slice(0, MESSAGE_MAX_LENGTH);
                                setMessage(value);
                                if (error) setError("");
                            }}
                            className={`min-h-[120px] ${error ? "border-red-500" : ""}`}
                            disabled={isLoading}
                            maxLength={MESSAGE_MAX_LENGTH}
                        />
                        <div className="flex items-center justify-between">
                            {error ? (
                                <p className="text-sm text-red-500">{error}</p>
                            ) : (
                                <span />
                            )}
                            <p className="text-xs text-[#94A3B8]">
                                {message.length}/{MESSAGE_MAX_LENGTH}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button variant="outline" onClick={() => handleClose(false)} disabled={isLoading}>
                        Cancelar
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={isLoading || !message.trim()}
                        className="bg-[#2563EB] hover:bg-[#1E40AF]"
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                Enviando...
                            </>
                        ) : (
                            <>
                                <FileEdit className="w-4 h-4 mr-2" />
                                Enviar Solicitud
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default DataUpdateRequestModal;
