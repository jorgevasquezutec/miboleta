import { useState } from 'react';
import { Lock, Mail, Key, Shield, Loader2, AlertCircle } from 'lucide-react';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Checkbox } from '@/presentation/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/presentation/components/ui/dialog';
import { RadioGroup, RadioGroupItem } from '@/presentation/components/ui/radio-group';
import apiClient from '@/infrastructure/http/apiClient';
import { toast } from 'sonner';

type ResetAction = 'no_change' | 'generate' | 'manual' | 'force_change_only';

interface PasswordResetModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    userId: string;
    userName: string;
    onSuccess?: () => void;
}

export function PasswordResetModal({
    open,
    onOpenChange,
    userId,
    userName,
    onSuccess,
}: PasswordResetModalProps) {
    const [action, setAction] = useState<ResetAction>('generate');
    const [manualPassword, setManualPassword] = useState('');
    const [mustChangePassword, setMustChangePassword] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleReset = () => {
        setAction('generate');
        setManualPassword('');
        setMustChangePassword(false);
        setErrors({});
    };

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (action === 'manual') {
            if (!manualPassword) {
                newErrors.password = 'La contraseña es requerida';
            } else if (manualPassword.length < 8) {
                newErrors.password = 'La contraseña debe tener al menos 8 caracteres';
            }
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async () => {
        if (!validateForm()) return;

        setIsLoading(true);
        try {
            const payload: any = {
                action,
                must_change_password: mustChangePassword,
            };

            if (action === 'manual') {
                payload.password = manualPassword;
            }

            await apiClient.post(`/users/${userId}/reset-password`, payload);

            let message = '';
            switch (action) {
                case 'generate':
                    message = 'Contraseña generada y enviada por email exitosamente';
                    break;
                case 'manual':
                    message = mustChangePassword
                        ? 'Contraseña establecida. El usuario deberá cambiarla en el próximo login'
                        : 'Contraseña actualizada exitosamente. Email enviado al usuario';
                    break;
                case 'force_change_only':
                    message = 'El usuario deberá cambiar su contraseña en el próximo login';
                    break;
                default:
                    message = 'Contraseña actualizada correctamente';
            }

            toast.success(message);
            onOpenChange(false);
            handleReset();
            onSuccess?.();
        } catch (error: any) {
            const message = error.response?.data?.message || 'Error al restablecer contraseña';
            toast.error(message);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(open: boolean) => {
            onOpenChange(open);
            if (!open) handleReset();
        }}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Lock className="h-5 w-5 text-blue-600" />
                        Restablecer Contraseña
                    </DialogTitle>
                    <DialogDescription>
                        Gestiona la contraseña de <strong>{userName}</strong>
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Action Selection */}
                    <RadioGroup value={action} onValueChange={(value: string) => {
                        setAction(value as ResetAction);
                        setErrors({});
                    }}>
                        {/* Option 1: Generate New Password */}
                        <div className={`flex items-start space-x-3 p-4 rounded-lg border-2 cursor-pointer transition-colors ${action === 'generate' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'
                            }`} onClick={() => setAction('generate')}>
                            <RadioGroupItem value="generate" id="generate" className="mt-1" />
                            <div className="flex-1">
                                <Label htmlFor="generate" className="cursor-pointer flex items-center gap-2 font-semibold">
                                    <Key className="h-4 w-4 text-blue-600" />
                                    Generar nueva contraseña
                                </Label>
                                <p className="text-sm text-gray-600 mt-1">
                                    Se generará automáticamente y se enviará por email al usuario
                                </p>
                            </div>
                        </div>

                        {/* Option 2: Set Manual Password */}
                        <div className={`flex items-start space-x-3 p-4 rounded-lg border-2 cursor-pointer transition-colors ${action === 'manual' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'
                            }`} onClick={() => setAction('manual')}>
                            <RadioGroupItem value="manual" id="manual" className="mt-1" />
                            <div className="flex-1">
                                <Label htmlFor="manual" className="cursor-pointer flex items-center gap-2 font-semibold">
                                    <Shield className="h-4 w-4 text-blue-600" />
                                    Establecer manualmente
                                </Label>
                                <p className="text-sm text-gray-600 mt-1 mb-3">
                                    Define una contraseña específica para el usuario
                                </p>
                                {action === 'manual' && (
                                    <div className="space-y-2">
                                        <Input
                                            type="password"
                                            placeholder="Nueva contraseña (mínimo 8 caracteres)"
                                            value={manualPassword}
                                            onChange={(e) => {
                                                setManualPassword(e.target.value);
                                                if (errors.password) setErrors(prev => ({ ...prev, password: '' }));
                                            }}
                                            className={errors.password ? 'border-red-500' : ''}
                                        />
                                        {errors.password && (
                                            <p className="text-sm text-red-500">{errors.password}</p>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Option 3: Force Change Only */}
                        <div className={`flex items-start space-x-3 p-4 rounded-lg border-2 cursor-pointer transition-colors ${action === 'force_change_only' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'
                            }`} onClick={() => setAction('force_change_only')}>
                            <RadioGroupItem value="force_change_only" id="force_change_only" className="mt-1" />
                            <div className="flex-1">
                                <Label htmlFor="force_change_only" className="cursor-pointer flex items-center gap-2 font-semibold">
                                    <AlertCircle className="h-4 w-4 text-orange-600" />
                                    Solo requerir cambio
                                </Label>
                                <p className="text-sm text-gray-600 mt-1">
                                    Mantener contraseña actual pero forzar cambio en próximo login
                                </p>
                            </div>
                        </div>
                    </RadioGroup>

                    {/* Require Password Change Checkbox */}
                    {(action === 'generate' || action === 'manual') && (
                        <div className="flex items-start space-x-3 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                            <Checkbox
                                id="must_change"
                                checked={mustChangePassword}
                                onCheckedChange={(checked: boolean) => setMustChangePassword(checked)}
                            />
                            <div className="flex-1">
                                <Label htmlFor="must_change" className="cursor-pointer font-medium text-yellow-900">
                                    Requerir cambio en próximo inicio de sesión
                                </Label>
                                <p className="text-sm text-yellow-700 mt-1">
                                    El usuario deberá establecer una nueva contraseña al iniciar sesión
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Info Alert */}
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <div className="flex items-start gap-2">
                            <Mail className="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0" />
                            <p className="text-sm text-blue-800">
                                {action === 'force_change_only'
                                    ? 'No se enviará ningún email al usuario'
                                    : 'Se enviará un correo de notificación al usuario con las instrucciones correspondientes'}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isLoading}
                    >
                        Cancelar
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={isLoading}
                        className="bg-blue-600 hover:bg-blue-700"
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Procesando...
                            </>
                        ) : (
                            <>
                                <Lock className="mr-2 h-4 w-4" />
                                Confirmar
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
