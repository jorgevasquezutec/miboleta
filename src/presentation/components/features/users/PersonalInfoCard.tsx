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

interface PersonalInfoCardProps {
    formData: {
        name: string;
        last_name: string;
        email: string;
        document_type: string;
        document_text: string;
        phone: string;
        birth_date: string;
    };
    errors: Record<string, string>;
    onChange: (field: string, value: string | null) => void;
}

export function PersonalInfoCard({ formData, errors, onChange }: PersonalInfoCardProps) {
    const getDocumentPlaceholder = () => {
        switch (formData.document_type) {
            case 'dni': return '12345678';
            case 'ruc': return '20123456789';
            case 'ce': return '001234567';
            default: return 'A1234567';
        }
    };

    const getDocumentMaxLength = () => {
        switch (formData.document_type) {
            case 'dni': return 8;
            case 'ruc': return 11;
            case 'ce': return 12;
            default: return 20;
        }
    };

    const getDocumentHint = () => {
        switch (formData.document_type) {
            case 'dni': return 'DNI: 8 dígitos numéricos';
            case 'ruc': return 'RUC: 11 dígitos numéricos';
            case 'ce': return 'CE: 9-12 caracteres alfanuméricos';
            case 'passport': return 'Pasaporte: hasta 20 caracteres alfanuméricos';
            default: return '';
        }
    };

    const handleDocumentTextChange = (value: string) => {
        if (formData.document_type === 'dni' || formData.document_type === 'ruc') {
            if (value === '' || /^\d+$/.test(value)) {
                onChange('document_text', value);
            }
        } else {
            onChange('document_text', value);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Información Personal</CardTitle>
                <CardDescription>Datos básicos del usuario</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">Nombre *</Label>
                        <Input
                            id="name"
                            value={formData.name}
                            onChange={(e) => onChange('name', e.target.value)}
                            placeholder="Juan"
                            className={errors.name ? 'border-red-500' : ''}
                        />
                        {errors.name && (
                            <p className="text-sm text-red-500">{errors.name}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="last_name">Apellido</Label>
                        <Input
                            id="last_name"
                            value={formData.last_name}
                            onChange={(e) => onChange('last_name', e.target.value)}
                            placeholder="Pérez"
                        />
                    </div>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="email">Email *</Label>
                    <Input
                        id="email"
                        type="email"
                        value={formData.email}
                        onChange={(e) => onChange('email', e.target.value)}
                        placeholder="juan@ejemplo.com"
                        className={errors.email ? 'border-red-500' : ''}
                    />
                    {errors.email && (
                        <p className="text-sm text-red-500">{errors.email}</p>
                    )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="document_type">Tipo de Documento</Label>
                        <Select
                            value={formData.document_type}
                            onValueChange={(value: string) => {
                                onChange('document_type', value);
                                onChange('document_text', '');
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="dni">DNI</SelectItem>
                                <SelectItem value="ruc">RUC</SelectItem>
                                <SelectItem value="ce">Carné de Extranjería</SelectItem>
                                <SelectItem value="passport">Pasaporte</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="document_text">Número de Documento *</Label>
                        <Input
                            id="document_text"
                            value={formData.document_text}
                            onChange={(e) => handleDocumentTextChange(e.target.value)}
                            placeholder={getDocumentPlaceholder()}
                            maxLength={getDocumentMaxLength()}
                            className={errors.document_text ? 'border-red-500' : ''}
                        />
                        <p className="text-xs text-gray-500">{getDocumentHint()}</p>
                        {errors.document_text && (
                            <p className="text-sm text-red-500">{errors.document_text}</p>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="phone">Teléfono</Label>
                        <Input
                            id="phone"
                            value={formData.phone}
                            onChange={(e) => onChange('phone', e.target.value)}
                            placeholder="+51 999 999 999"
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="birth_date">Fecha de Nacimiento</Label>
                        <Input
                            id="birth_date"
                            type="date"
                            value={formData.birth_date}
                            onChange={(e) => onChange('birth_date', e.target.value)}
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
