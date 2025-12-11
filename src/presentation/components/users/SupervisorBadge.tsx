import { User, SupervisorBasic } from '@/core/domain/entities/User';
import { useNavigate } from 'react-router-dom';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/presentation/components/ui/tooltip';
import { Badge } from '@/presentation/components/ui/badge';
import { UserCircle, Mail, Briefcase } from 'lucide-react';

interface SupervisorBadgeProps {
    supervisor: SupervisorBasic | null | undefined;
    showEmail?: boolean;
    clickable?: boolean;
    size?: 'sm' | 'md' | 'lg';
}

/**
 * SupervisorBadge - Displays supervisor information with tooltip
 * Shows name and optionally email, with click to navigate to profile
 */
export function SupervisorBadge({
    supervisor,
    showEmail = false,
    clickable = true,
    size = 'md'
}: SupervisorBadgeProps) {
    const navigate = useNavigate();

    if (!supervisor) {
        return (
            <Badge variant="outline" className="text-gray-500 bg-gray-50">
                <UserCircle className="h-3 w-3 mr-1" />
                Sin supervisor
            </Badge>
        );
    }

    const sizeClasses = {
        sm: 'text-xs px-2 py-0.5',
        md: 'text-sm px-3 py-1',
        lg: 'text-base px-4 py-1.5'
    };

    const handleClick = () => {
        if (clickable && supervisor.id) {
            navigate(`/users/${supervisor.id}`);
        }
    };

    const displayName = supervisor.full_name || supervisor.name;

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge
                        variant="secondary"
                        className={`
                            ${sizeClasses[size]}
                            ${clickable ? 'cursor-pointer hover:bg-blue-100 transition-colors' : ''}
                            bg-blue-50 text-blue-700 border border-blue-200
                        `}
                        onClick={handleClick}
                    >
                        <UserCircle className="h-3.5 w-3.5 mr-1.5" />
                        {displayName}
                        {showEmail && supervisor.email && (
                            <span className="text-blue-500 ml-1 text-xs">
                                ({supervisor.email})
                            </span>
                        )}
                    </Badge>
                </TooltipTrigger>
                <TooltipContent side="top" className="bg-white border shadow-lg p-3">
                    <div className="space-y-1.5">
                        <div className="font-semibold text-gray-900">
                            {displayName}
                        </div>
                        {supervisor.email && (
                            <div className="flex items-center gap-1.5 text-sm text-gray-600">
                                <Mail className="h-3.5 w-3.5" />
                                {supervisor.email}
                            </div>
                        )}
                        <div className="flex items-center gap-1.5 text-xs text-gray-500">
                            <Briefcase className="h-3 w-3" />
                            Jefe Inmediato
                        </div>
                        {clickable && (
                            <div className="text-xs text-blue-600 pt-1 border-t border-gray-100">
                                Click para ver perfil
                            </div>
                        )}
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
