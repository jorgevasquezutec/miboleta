"use client";

import { useTheme } from "next-themes";
import { Toaster as Sonner, ToasterProps } from "sonner";

const Toaster = ({ ...props }: ToasterProps) => {
  const { theme = "system" } = useTheme();

  return (
    <Sonner
      theme={theme as ToasterProps["theme"]}
      className="toaster group"
      toastOptions={{
        style: {
          background: "hsl(var(--popover))",
          color: "hsl(var(--popover-foreground))",
          border: "1px solid hsl(var(--border))",
        },
        classNames: {
          title: "text-gray-900 font-medium",
          description: "text-gray-600",
          actionButton: "bg-primary text-primary-foreground",
        },
      }}
      {...props}
    />
  );
};

export { Toaster };
