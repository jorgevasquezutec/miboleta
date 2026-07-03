import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/presentation/components/ui/dialog';
import { Button } from '@/presentation/components/ui/button';
import { Label } from '@/presentation/components/ui/label';
import { Download, Loader2 } from 'lucide-react';
import type { TemplateConfig, BulkUploadConfigData } from '@/domain/types/bulkUserUpload.types';

interface TemplateConfigModalProps {
    isOpen: boolean;
    onClose: () => void;
    onDownload: (config: TemplateConfig) => Promise<void>;
    configData: BulkUploadConfigData | null;
}

export function TemplateConfigModal({
    isOpen,
    onClose,
    onDownload,
    configData,
}: TemplateConfigModalProps) {
    const [isDownloading, setIsDownloading] = useState(false);

    const handleDownload = async () => {
        setIsDownloading(true);
        try {
            await onDownload({
                max_organizations: 1, // Siempre 1 organización
                organization_ids: undefined, // Sin filtro
            });
            onClose();
        } catch (error) {
            console.error('Error downloading template:', error);
        } finally {
            setIsDownloading(false);
        }
    };

    if (!configData) {
        return null;
    }

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Descargar Template</DialogTitle>
                    <DialogDescription>
                        El template incluirá las columnas para cargar usuarios masivamente
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* Preview de columnas */}
                    <div className="space-y-2">
                        <Label>Columnas del template</Label>
                        <div className="bg-gray-50 p-4 rounded-lg border">
                            <div className="text-sm space-y-2 font-mono">
                                <div className="text-gray-600 font-semibold">Datos del usuario:</div>
                                <div className="text-xs text-gray-500 ml-2 grid grid-cols-2 gap-1">
                                    <span>• nombre</span>
                                    <span>• apellido</span>
                                    <span>• email</span>
                                    <span>• tipo_documento</span>
                                    <span>• numero_documento</span>
                                    <span>• rol</span>
                                    <span>• estado</span>
                                    <span>• telefono</span>
                                </div>
                                <div className="text-gray-600 font-semibold mt-3">Organización:</div>
                                <div className="text-xs text-gray-500 ml-2 space-y-0.5">
                                    <div>• org1_ruc</div>
                                    <div>• org1_supervisor_email</div>
                                    <div>• org1_rol <span className="text-gray-400">(admin, client, aprobador, administrador_clientes)</span></div>
                                    <div>• org1_fecha_ingreso</div>
                                    <div>• org1_saldo_vacaciones</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Info de organizaciones disponibles */}
                    <div className="space-y-2">
                        <Label>Organizaciones disponibles: {configData.organizations.length}</Label>
                        <p className="text-xs text-gray-500">
                            En el Excel podrás asignar cualquier organización usando su RUC
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={isDownloading}>
                        Cancelar
                    </Button>
                    <Button onClick={handleDownload} disabled={isDownloading}>
                        {isDownloading ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Generando...
                            </>
                        ) : (
                            <>
                                <Download className="mr-2 h-4 w-4" />
                                Descargar Template
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
