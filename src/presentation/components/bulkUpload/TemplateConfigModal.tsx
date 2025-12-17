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
    const [maxOrganizations, setMaxOrganizations] = useState(1);
    const [selectedOrgs, setSelectedOrgs] = useState<number[]>([]);
    const [isDownloading, setIsDownloading] = useState(false);

    const handleDownload = async () => {
        setIsDownloading(true);
        try {
            await onDownload({
                max_organizations: maxOrganizations,
                organization_ids: selectedOrgs.length > 0 ? selectedOrgs : undefined,
            });
            onClose();
        } catch (error) {
            console.error('Error downloading template:', error);
        } finally {
            setIsDownloading(false);
        }
    };

    const toggleOrg = (orgId: number) => {
        setSelectedOrgs((prev) =>
            prev.includes(orgId)
                ? prev.filter((id) => id !== orgId)
                : [...prev, orgId]
        );
    };

    if (!configData) {
        return null;
    }

    const orgOptions = [1, 2, 3];

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>Configurar Template</DialogTitle>
                    <DialogDescription>
                        Personaliza el template Excel según tus necesidades
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Número de organizaciones */}
                    <div className="space-y-2">
                        <Label>Número máximo de organizaciones por usuario</Label>
                        <div className="flex gap-2">
                            {orgOptions.map((num) => (
                                <Button
                                    key={num}
                                    variant={maxOrganizations === num ? 'default' : 'outline'}
                                    onClick={() => setMaxOrganizations(num)}
                                    className="flex-1"
                                >
                                    {num} {num === 1 ? 'organización' : 'organizaciones'}
                                </Button>
                            ))}
                        </div>
                        <p className="text-xs text-gray-500">
                            Usuarios con más organizaciones pueden repetirse en múltiples filas
                        </p>
                    </div>

                    {/* Preview de columnas */}
                    <div className="space-y-2">
                        <Label>Columnas del template</Label>
                        <div className="bg-gray-50 p-4 rounded-lg border">
                            <div className="text-sm space-y-1 font-mono">
                                <div className="text-gray-600">Campos básicos:</div>
                                <div className="text-xs text-gray-500 ml-2">
                                    nombre, apellido, email, tipo_documento, numero_documento, rol, estado, teléfono
                                </div>
                                <div className="text-gray-600 mt-2">Organizaciones:</div>
                                {Array.from({ length: maxOrganizations }).map((_, i) => (
                                    <div key={i} className="text-xs text-gray-500 ml-2">
                                        org{i + 1}_ruc, org{i + 1}_supervisor_email
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Filtrar por organizaciones (opcional) */}
                    <div className="space-y-2">
                        <Label>Filtrar por organizaciones (opcional)</Label>
                        <div className="max-h-48 overflow-y-auto border rounded-lg p-3 space-y-2">
                            {configData.organizations.map((org) => (
                                <label
                                    key={org.id}
                                    className="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-2 rounded"
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedOrgs.includes(org.id)}
                                        onChange={() => toggleOrg(org.id)}
                                        className="rounded"
                                    />
                                    <span className="text-sm flex-1">
                                        <span className="font-medium">{org.ruc}</span> - {org.name}
                                    </span>
                                    <span className="text-xs text-gray-500">
                                        {org.supervisors_count} supervisores
                                    </span>
                                </label>
                            ))}
                        </div>
                        <p className="text-xs text-gray-500">
                            {selectedOrgs.length > 0
                                ? `${selectedOrgs.length} organizaciones seleccionadas`
                                : 'Sin filtro - todas las organizaciones disponibles'}
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
                                Generar y Descargar
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
