import { useEffect, useState, useRef } from 'react';
import { useDocumentTitle } from '@/presentation/hooks';
import { useNavigate, useParams } from 'react-router-dom';
import { useTenantsStore } from '@/presentation/stores/tenantsStore';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/presentation/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/presentation/components/ui/select';
import { Badge } from '@/presentation/components/ui/badge';
import { ArrowLeft, Save, Loader2, Building2, X, ImageIcon, Mail, Users, PlugZap } from 'lucide-react';
import { toast } from 'sonner';
import { uploadTenantLogo, validateImageFile } from '@/infrastructure/http/fileUpload';
import { tenantRepository } from '@/infrastructure/persistence/repositories/TenantRepository';
import type { CreateTenantData, UpdateTenantData, MailEncryption } from '@/core/domain/entities/Tenant';

export function TenantFormPage() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const isEditing = Boolean(id);
    useDocumentTitle(isEditing ? 'Editar Empresa' : 'Nueva Empresa');
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { fetchTenantById, currentTenant, createTenant, updateTenant, isLoading } = useTenantsStore();

    const [formData, setFormData] = useState({
        name: '',
        ruc: '',
        business_name: '',
        address: '',
        phone: '',
        logo_path: '',
        status: 'active' as 'active' | 'inactive' | 'suspended',
        labor_regime: 'general' as 'general' | 'micro' | 'pequena',
        // SMTP por empresa (todos opcionales). mail_port se maneja como string
        // en el input y se convierte a número al armar el payload.
        // mail_encryption usa el centinela 'none' para "sin cifrado".
        mail_host: '',
        mail_port: '',
        mail_username: '',
        mail_encryption: 'none' as 'none' | MailEncryption,
        mail_from_address: '',
        mail_from_name: '',
    });

    // La contraseña SMTP se maneja aparte: nunca se precarga (el backend no la
    // devuelve) y solo se envía cuando el usuario escribe una nueva.
    const [mailPassword, setMailPassword] = useState('');

    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isUploading, setIsUploading] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const [logoPreviewUrl, setLogoPreviewUrl] = useState<string | null>(null);
    // Estado local del botón "Probar conexión" SMTP (ítem 24-menor). No usa
    // el `isLoading` global del store porque ese ya controla los botones de
    // Guardar/Cancelar del formulario.
    const [isTestingSmtp, setIsTestingSmtp] = useState(false);

    // Clear currentTenant when creating new tenant
    useEffect(() => {
        if (!isEditing) {
            // Clear any previous tenant data from store
            useTenantsStore.getState().clearCurrentTenant();
        }
    }, [isEditing]);

    // Load tenant data if editing
    useEffect(() => {
        if (isEditing && id) {
            loadTenant(id);
        }
    }, [id, isEditing]);

    const loadTenant = async (tenantId: string) => {
        await fetchTenantById(tenantId);
    };

    // Update form when currentTenant changes (only when editing)
    useEffect(() => {
        // Compare IDs as strings to handle number vs string mismatch
        if (isEditing && currentTenant && String(currentTenant.id) === String(id)) {
            setFormData({
                name: currentTenant.name,
                ruc: currentTenant.ruc,
                business_name: currentTenant.business_name || '',
                address: currentTenant.address || '',
                phone: currentTenant.phone || '',
                logo_path: currentTenant.logo_path || '',
                status: currentTenant.status,
                labor_regime: currentTenant.labor_regime || 'general',
                mail_host: currentTenant.mail_host || '',
                mail_port: currentTenant.mail_port != null ? String(currentTenant.mail_port) : '',
                mail_username: currentTenant.mail_username || '',
                mail_encryption: currentTenant.mail_encryption || 'none',
                mail_from_address: currentTenant.mail_from_address || '',
                mail_from_name: currentTenant.mail_from_name || '',
            });
            // La contraseña nunca se precarga (write-only en el backend).
            setMailPassword('');
            // Set preview URL from backend-generated logo_url
            setLogoPreviewUrl(currentTenant.logo_url || null);
        }
    }, [currentTenant, isEditing, id]);

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (!formData.name.trim()) {
            newErrors.name = 'El nombre es requerido';
        }

        if (!formData.ruc.trim()) {
            newErrors.ruc = 'El RUC es requerido';
        } else if (formData.ruc.length !== 11) {
            newErrors.ruc = 'El RUC debe tener 11 dígitos';
        } else if (!/^\d+$/.test(formData.ruc)) {
            newErrors.ruc = 'El RUC solo debe contener números';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            toast.error('Por favor corrige los errores del formulario');
            return;
        }

        try {
            const payload = buildPayload();
            if (isEditing && id) {
                const result = await updateTenant(id, payload as UpdateTenantData);
                if (result) {
                    toast.success('Organización actualizada exitosamente');
                    navigate('/tenants');
                }
            } else {
                const result = await createTenant(payload as CreateTenantData);
                if (result) {
                    toast.success('Organización creada exitosamente');
                    navigate('/tenants');
                }
            }
        } catch (error) {
            // Error already handled by store
        }
    };

    /**
     * Arma el payload de create/update.
     * Regla crítica SMTP: la contraseña solo se incluye cuando el usuario
     * escribe una nueva; si se deja vacía NO se envía `mail_password`, para no
     * pisar/borrar la contraseña ya guardada en el backend (un
     * mail_password:"" presente SÍ la borraría).
     */
    const buildPayload = (): CreateTenantData => {
        const trimmed = (v: string) => v.trim();
        const payload: CreateTenantData = {
            name: formData.name,
            ruc: formData.ruc,
            business_name: formData.business_name,
            address: formData.address,
            phone: formData.phone,
            logo_path: formData.logo_path,
            status: formData.status,
            labor_regime: formData.labor_regime,
            // SMTP: normalizamos vacíos a null para no romper validaciones
            // (ej. formato email) y permitir limpiar valores existentes.
            mail_host: trimmed(formData.mail_host) || null,
            mail_port: formData.mail_port ? Number(formData.mail_port) : null,
            mail_username: trimmed(formData.mail_username) || null,
            mail_encryption: formData.mail_encryption === 'none' ? null : formData.mail_encryption,
            mail_from_address: trimmed(formData.mail_from_address) || null,
            mail_from_name: trimmed(formData.mail_from_name) || null,
        };

        // Solo incluir la contraseña si el usuario escribió una nueva.
        if (mailPassword.trim() !== '') {
            payload.mail_password = mailPassword;
        }

        return payload;
    };

    /**
     * Prueba la conexión SMTP ya guardada para esta empresa (POST
     * /tenants/{id}/smtp/test, solo root). Prueba la configuración
     * persistida en el backend, no los valores sin guardar del formulario
     * (si el usuario cambió el host/puerto/etc. debe guardar primero).
     */
    const handleTestSmtp = async () => {
        if (!id) return;

        setIsTestingSmtp(true);
        try {
            const result = await tenantRepository.testSmtp(id);
            if (result.success) {
                toast.success(result.message);
            } else {
                toast.error(result.message);
            }
        } catch (error: any) {
            toast.error(error.message || 'Error al probar la conexión SMTP');
        } finally {
            setIsTestingSmtp(false);
        }
    };

    const handleChange = (field: string, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors(prev => ({ ...prev, [field]: '' }));
        }
    };

    const handleFileSelect = async (file: File) => {
        const validationError = validateImageFile(file);
        if (validationError) {
            toast.error(validationError);
            return;
        }

        // Create object URL for immediate preview
        const previewUrl = URL.createObjectURL(file);
        setLogoPreviewUrl(previewUrl);

        setIsUploading(true);
        try {
            const path = await uploadTenantLogo(file);
            setFormData(prev => ({ ...prev, logo_path: path }));
            toast.success('Logo subido exitosamente');
        } catch (error: any) {
            // If upload fails, clear the preview
            setLogoPreviewUrl(null);
            toast.error(error.message || 'Error al subir el logo');
        } finally {
            setIsUploading(false);
        }
    };

    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            handleFileSelect(file);
        }
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);

        const file = e.dataTransfer.files[0];
        if (file) {
            handleFileSelect(file);
        }
    };

    const handleRemoveLogo = () => {
        setFormData(prev => ({ ...prev, logo_path: '' }));
        setLogoPreviewUrl(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    return (
        <div className="container mx-auto py-6 max-w-4xl">
            {/* Header */}
            <div className="mb-6">
                <Button
                    variant="ghost"
                    onClick={() => navigate('/tenants')}
                    className="mb-4"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Volver a Organizaciones
                </Button>
                <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100">
                        <Building2 className="h-6 w-6 text-blue-600" />
                    </div>
                    <div>
                        <h1 className="text-xl sm:text-2xl font-bold">
                            {isEditing ? 'Editar Organización' : 'Nueva Organización'}
                        </h1>
                        <p className="text-gray-500 mt-1 text-sm sm:text-base">
                            {isEditing
                                ? 'Actualiza la información de la organización'
                                : 'Completa los datos para crear una nueva organización'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit} className="space-y-6 mt-8">
                {/* Logo Upload Section */}
                <Card>
                    <CardHeader>
                        <CardTitle>Logo de la Organización</CardTitle>
                        <CardDescription>
                            Sube el logo de la organización (JPG, PNG, GIF, WEBP - Máx. 2MB)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {/* Current Logo Preview */}
                            {(logoPreviewUrl || formData.logo_path) && (
                                <div className="flex items-center gap-4 p-4 border rounded-lg bg-gray-50">
                                    {logoPreviewUrl ? (
                                        <img
                                            src={logoPreviewUrl}
                                            alt="Logo actual"
                                            className="h-20 w-20 rounded-lg object-cover border-2 border-gray-200 shadow-sm"
                                            onError={() => setLogoPreviewUrl(null)}
                                        />
                                    ) : (
                                        <div className="flex h-20 w-20 items-center justify-center rounded-lg bg-blue-100 border-2 border-gray-200">
                                            <Building2 className="h-10 w-10 text-blue-600" />
                                        </div>
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <p className="font-medium text-sm text-gray-900">Logo actual</p>
                                        <p className="text-xs text-gray-500 truncate">
                                            {formData.logo_path.split('/').pop() || 'Archivo de imagen'}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleRemoveLogo}
                                        disabled={isUploading}
                                        className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                                    >
                                        <X className="h-4 w-4 mr-1" />
                                        Eliminar
                                    </Button>
                                </div>
                            )}

                            {/* Upload Area */}
                            <div
                                className={`border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer ${isDragging
                                    ? 'border-blue-500 bg-blue-50'
                                    : 'border-gray-300 hover:border-gray-400'
                                    } ${isUploading ? 'opacity-50 pointer-events-none' : ''}`}
                                onDragOver={handleDragOver}
                                onDragLeave={handleDragLeave}
                                onDrop={handleDrop}
                                onClick={(e) => {
                                    console.log('🔵 Upload area clicked!');
                                    e.stopPropagation(); // Solo prevenir propagación, NO preventDefault
                                    if (fileInputRef.current) {
                                        console.log('✅ Opening file picker...');
                                        fileInputRef.current.click();
                                    }
                                }}
                            >
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/*"
                                    onChange={handleFileInputChange}
                                    className="hidden"
                                    disabled={isUploading}
                                />

                                {isUploading ? (
                                    <div className="flex flex-col items-center gap-3">
                                        <Loader2 className="h-10 w-10 animate-spin text-blue-600" />
                                        <p className="text-sm text-gray-600">Subiendo logo...</p>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center gap-3">
                                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                                            <ImageIcon className="h-8 w-8 text-gray-400" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-700">
                                                Arrastra una imagen aquí o haz click para seleccionar
                                            </p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                JPG, PNG, GIF o WEBP hasta 2MB
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Employee Counters (RP1-C / ítem 19) - solo lectura, informativo */}
                {isEditing && currentTenant && String(currentTenant.id) === String(id) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5 text-blue-600" />
                                Contador de empleados
                            </CardTitle>
                            <CardDescription>
                                Información de solo lectura. El conteo inicial se fija automáticamente
                                al completar la primera carga masiva de usuarios de esta organización.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="p-3 rounded-lg border bg-gray-50">
                                    <p className="text-xs text-gray-500">Carga inicial</p>
                                    <p className="text-xl font-semibold text-gray-900">
                                        {currentTenant.initial_employee_count ?? 0}
                                    </p>
                                </div>
                                <div className="p-3 rounded-lg border bg-gray-50">
                                    <p className="text-xs text-gray-500">Total actual</p>
                                    <p className="text-xl font-semibold text-gray-900">
                                        {currentTenant.current_employee_count ?? 0}
                                    </p>
                                </div>
                                <div className="p-3 rounded-lg border bg-gray-50">
                                    <p className="text-xs text-gray-500">Agregados después</p>
                                    <p className="text-xl font-semibold text-gray-900">
                                        {currentTenant.subsequent_employee_count ?? 0}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Basic Information */}
                <Card>
                    <CardHeader>
                        <CardTitle>Información Básica</CardTitle>
                        <CardDescription>
                            Datos principales de la organización
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Name */}
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Nombre <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={formData.name}
                                    onChange={(e) => handleChange('name', e.target.value)}
                                    placeholder="Ej: Corporación ABC"
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">{errors.name}</p>
                                )}
                            </div>

                            {/* RUC */}
                            <div className="space-y-2">
                                <Label htmlFor="ruc">
                                    RUC <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="ruc"
                                    value={formData.ruc}
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        // Solo permitir números en el RUC
                                        if (value === '' || /^\d+$/.test(value)) {
                                            handleChange('ruc', value);
                                        }
                                    }}
                                    placeholder="Ej: 20123456789"
                                    maxLength={11}
                                    className={errors.ruc ? 'border-red-500' : ''}
                                />
                                <p className="text-xs text-gray-500">RUC: 11 dígitos numéricos</p>
                                {errors.ruc && (
                                    <p className="text-sm text-red-500">{errors.ruc}</p>
                                )}
                            </div>
                        </div>

                        {/* Business Name */}
                        <div className="space-y-2">
                            <Label htmlFor="business_name">Razón Social</Label>
                            <Input
                                id="business_name"
                                value={formData.business_name}
                                onChange={(e) => handleChange('business_name', e.target.value)}
                                placeholder="Ej: ABC S.A.C."
                            />
                        </div>

                        {/* Status */}
                        <div className="space-y-2">
                            <Label htmlFor="status">Estado</Label>
                            <Select
                                value={formData.status}
                                onValueChange={(value: 'active' | 'inactive' | 'suspended') =>
                                    handleChange('status', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Activo</SelectItem>
                                    <SelectItem value="inactive">Inactivo</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Labor Regime */}
                        <div className="space-y-2">
                            <Label htmlFor="labor_regime">Régimen Laboral</Label>
                            <Select
                                value={formData.labor_regime}
                                onValueChange={(value: 'general' | 'micro' | 'pequena') =>
                                    handleChange('labor_regime', value)
                                }
                            >
                                <SelectTrigger id="labor_regime">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="general">Régimen General (30 días)</SelectItem>
                                    <SelectItem value="micro">Microempresa (15 días)</SelectItem>
                                    <SelectItem value="pequena">Pequeña Empresa (15 días)</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-gray-500">
                                Determina los días de vacaciones que corresponden a los empleados por año.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Contact Information */}
                <Card>
                    <CardHeader>
                        <CardTitle>Información de Contacto</CardTitle>
                        <CardDescription>
                            Datos de contacto y ubicación (opcional)
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Address */}
                        <div className="space-y-2">
                            <Label htmlFor="address">Dirección</Label>
                            <Input
                                id="address"
                                value={formData.address}
                                onChange={(e) => handleChange('address', e.target.value)}
                                placeholder="Ej: Av. Principal 123, Lima"
                            />
                        </div>

                        {/* Phone */}
                        <div className="space-y-2">
                            <Label htmlFor="phone">Teléfono</Label>
                            <Input
                                id="phone"
                                value={formData.phone}
                                onChange={(e) => handleChange('phone', e.target.value)}
                                placeholder="Ej: +51 999 999 999"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* SMTP / Mail Server */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Mail className="h-5 w-5 text-blue-600" />
                                    Servidor de correo (SMTP)
                                </CardTitle>
                                <CardDescription>
                                    Opcional. Si no configuras un servidor propio, los correos de esta
                                    organización se envían con el servidor por defecto de la plataforma.
                                </CardDescription>
                            </div>
                            {isEditing && currentTenant && String(currentTenant.id) === String(id) && (
                                <div className="flex items-center gap-2">
                                    {currentTenant.has_custom_mailer ? (
                                        <Badge variant="default" className="whitespace-nowrap">
                                            Correo propio
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary" className="whitespace-nowrap">
                                            Correo de la plataforma
                                        </Badge>
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleTestSmtp}
                                        disabled={isTestingSmtp || !currentTenant.has_custom_mailer}
                                        title={
                                            !currentTenant.has_custom_mailer
                                                ? 'Guarda un host y correo remitente para poder probar la conexión'
                                                : undefined
                                        }
                                    >
                                        {isTestingSmtp ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Probando...
                                            </>
                                        ) : (
                                            <>
                                                <PlugZap className="mr-2 h-4 w-4" />
                                                Probar conexión
                                            </>
                                        )}
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Host */}
                            <div className="space-y-2">
                                <Label htmlFor="mail_host">Host</Label>
                                <Input
                                    id="mail_host"
                                    value={formData.mail_host}
                                    onChange={(e) => handleChange('mail_host', e.target.value)}
                                    placeholder="Ej: smtp.gmail.com"
                                />
                            </div>

                            {/* Port */}
                            <div className="space-y-2">
                                <Label htmlFor="mail_port">Puerto</Label>
                                <Input
                                    id="mail_port"
                                    type="number"
                                    min={1}
                                    max={65535}
                                    value={formData.mail_port}
                                    onChange={(e) => handleChange('mail_port', e.target.value)}
                                    placeholder="Ej: 587"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Username */}
                            <div className="space-y-2">
                                <Label htmlFor="mail_username">Usuario</Label>
                                <Input
                                    id="mail_username"
                                    value={formData.mail_username}
                                    onChange={(e) => handleChange('mail_username', e.target.value)}
                                    placeholder="Ej: no-reply@empresa.com"
                                    autoComplete="off"
                                />
                            </div>

                            {/* Password */}
                            <div className="space-y-2">
                                <Label htmlFor="mail_password">
                                    {isEditing && currentTenant?.has_mail_password
                                        ? 'Cambiar contraseña'
                                        : 'Contraseña'}
                                </Label>
                                <Input
                                    id="mail_password"
                                    type="password"
                                    value={mailPassword}
                                    onChange={(e) => setMailPassword(e.target.value)}
                                    placeholder={
                                        isEditing && currentTenant?.has_mail_password
                                            ? '•••••• (sin cambios)'
                                            : 'Contraseña del servidor SMTP'
                                    }
                                    autoComplete="new-password"
                                />
                                {isEditing && currentTenant?.has_mail_password && (
                                    <p className="text-xs text-gray-500">
                                        Déjalo vacío para mantener la contraseña actual.
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Encryption */}
                            <div className="space-y-2">
                                <Label htmlFor="mail_encryption">Cifrado</Label>
                                <Select
                                    value={formData.mail_encryption}
                                    onValueChange={(value: 'none' | MailEncryption) =>
                                        handleChange('mail_encryption', value)
                                    }
                                >
                                    <SelectTrigger id="mail_encryption">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Ninguno</SelectItem>
                                        <SelectItem value="tls">TLS</SelectItem>
                                        <SelectItem value="ssl">SSL</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* From Address */}
                            <div className="space-y-2">
                                <Label htmlFor="mail_from_address">Correo remitente</Label>
                                <Input
                                    id="mail_from_address"
                                    type="email"
                                    value={formData.mail_from_address}
                                    onChange={(e) => handleChange('mail_from_address', e.target.value)}
                                    placeholder="Ej: no-reply@empresa.com"
                                />
                            </div>

                            {/* From Name */}
                            <div className="space-y-2">
                                <Label htmlFor="mail_from_name">Nombre remitente</Label>
                                <Input
                                    id="mail_from_name"
                                    value={formData.mail_from_name}
                                    onChange={(e) => handleChange('mail_from_name', e.target.value)}
                                    placeholder="Ej: Recursos Humanos ABC"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex justify-start gap-4 sticky bottom-0 bg-white py-4 pl-8 border-t">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => navigate('/tenants')}
                        disabled={isLoading || isUploading}
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={isLoading || isUploading}>
                        {isLoading ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                {isEditing ? 'Actualizando...' : 'Creando...'}
                            </>
                        ) : (
                            <>
                                <Save className="mr-2 h-4 w-4" />
                                {isEditing ? 'Actualizar' : 'Crear'} Organización
                            </>
                        )}
                    </Button>
                </div>
            </form>
        </div>
    );
}
export default TenantFormPage;