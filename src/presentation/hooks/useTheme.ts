import { useState, useEffect, useCallback } from 'react';

type Theme = 'light' | 'dark' | 'system';

interface UseThemeReturn {
    theme: Theme;
    resolvedTheme: 'light' | 'dark';
    setTheme: (theme: Theme) => void;
    toggleTheme: () => void;
}

const THEME_STORAGE_KEY = 'miboleta-theme';

export function useTheme(): UseThemeReturn {
    const [theme, setThemeState] = useState<Theme>(() => {
        if (typeof window === 'undefined') return 'system';
        const stored = localStorage.getItem(THEME_STORAGE_KEY) as Theme | null;
        return stored || 'system';
    });

    const [resolvedTheme, setResolvedTheme] = useState<'light' | 'dark'>('light');

    // Get the actual theme based on system preference
    const getSystemTheme = useCallback((): 'light' | 'dark' => {
        if (typeof window === 'undefined') return 'light';
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }, []);

    // Resolve the theme (handle 'system' option)
    const resolveTheme = useCallback((t: Theme): 'light' | 'dark' => {
        if (t === 'system') {
            return getSystemTheme();
        }
        return t;
    }, [getSystemTheme]);

    // Apply theme to document
    const applyTheme = useCallback((resolved: 'light' | 'dark') => {
        const root = document.documentElement;
        root.classList.remove('light', 'dark');
        root.classList.add(resolved);

        // Update meta theme-color for mobile browsers
        const metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (metaThemeColor) {
            metaThemeColor.setAttribute('content', resolved === 'dark' ? '#0f172a' : '#2563EB');
        }
    }, []);

    // Set theme and persist
    const setTheme = useCallback((newTheme: Theme) => {
        setThemeState(newTheme);
        localStorage.setItem(THEME_STORAGE_KEY, newTheme);
    }, []);

    // Toggle between light and dark
    const toggleTheme = useCallback(() => {
        setTheme(resolvedTheme === 'light' ? 'dark' : 'light');
    }, [resolvedTheme, setTheme]);

    // Effect to apply theme changes
    useEffect(() => {
        const resolved = resolveTheme(theme);
        setResolvedTheme(resolved);
        applyTheme(resolved);
    }, [theme, resolveTheme, applyTheme]);

    // Listen for system theme changes
    useEffect(() => {
        if (theme !== 'system') return;

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = () => {
            const resolved = getSystemTheme();
            setResolvedTheme(resolved);
            applyTheme(resolved);
        };

        mediaQuery.addEventListener('change', handleChange);
        return () => mediaQuery.removeEventListener('change', handleChange);
    }, [theme, getSystemTheme, applyTheme]);

    return {
        theme,
        resolvedTheme,
        setTheme,
        toggleTheme,
    };
}

export default useTheme;
