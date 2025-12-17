import { RouterProvider } from "react-router-dom";
import { router } from "@/presentation/routes";
import { Toaster } from "@/presentation/components/ui/sonner";

export default function App() {
  return (
    <>
      <RouterProvider router={router} />
      <Toaster />
    </>
  );
}
