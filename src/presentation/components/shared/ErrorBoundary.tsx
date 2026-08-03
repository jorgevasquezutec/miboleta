import { Component, ErrorInfo, ReactNode } from 'react';
import { AlertTriangle, RefreshCw, Home } from 'lucide-react';
import { Button } from '@/presentation/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/presentation/components/ui/card';

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
    errorInfo: ErrorInfo | null;
}

export class ErrorBoundary extends Component<Props, State> {
    public state: State = {
        hasError: false,
        error: null,
        errorInfo: null,
    };

    public static getDerivedStateFromError(error: Error): Partial<State> {
        return { hasError: true, error };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        console.error('ErrorBoundary caught an error:', error, errorInfo);
        this.setState({ errorInfo });
    }

    private handleGoHome = () => {
        window.location.href = '/';
    };

    private handleRetry = () => {
        this.setState({ hasError: false, error: null, errorInfo: null });
    };

    public render() {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback;
            }

            return (
                <div className="min-h-screen flex items-center justify-center bg-background p-4">
                    <Card className="max-w-md w-full shadow-lg animate-fade-in">
                        <CardHeader className="text-center pb-2">
                            <div className="mx-auto w-16 h-16 bg-destructive/10 rounded-full flex items-center justify-center mb-4">
                                <AlertTriangle className="w-8 h-8 text-destructive" />
                            </div>
                            <CardTitle className="text-xl">Algo salió mal</CardTitle>
                            <CardDescription>
                                Ha ocurrido un error inesperado. Por favor, intenta de nuevo.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {process.env.NODE_ENV === 'development' && this.state.error && (
                                <div className="p-3 bg-muted rounded-lg text-sm">
                                    <p className="font-mono text-destructive break-all">
                                        {this.state.error.message}
                                    </p>
                                </div>
                            )}

                            <div className="flex flex-col gap-2">
                                <Button onClick={this.handleRetry} className="w-full gap-2">
                                    <RefreshCw className="w-4 h-4" />
                                    Intentar de nuevo
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={this.handleGoHome}
                                    className="w-full gap-2"
                                >
                                    <Home className="w-4 h-4" />
                                    Ir al inicio
                                </Button>
                            </div>

                            <p className="text-xs text-center text-muted-foreground">
                                Si el problema persiste, contacta a soporte técnico.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
