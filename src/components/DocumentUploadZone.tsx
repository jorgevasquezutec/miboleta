import { useCallback, useState } from "react";
import { Upload, X, FileText, CheckCircle } from "lucide-react";
import { Button } from "./ui/button";
import { Progress } from "./ui/progress";

interface UploadedFile {
  id: string;
  name: string;
  size: string;
  status: "uploading" | "completed" | "error";
  progress: number;
}

interface DocumentUploadZoneProps {
  onFilesSelected?: (files: File[]) => void;
}

export function DocumentUploadZone({ onFilesSelected }: DocumentUploadZoneProps) {
  const [isDragging, setIsDragging] = useState(false);
  const [uploadedFiles, setUploadedFiles] = useState<UploadedFile[]>([]);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  }, []);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
  }, []);

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      setIsDragging(false);

      const files = Array.from(e.dataTransfer.files);
      handleFiles(files);
    },
    [onFilesSelected]
  );

  const handleFileInput = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = e.target.files ? Array.from(e.target.files) : [];
      handleFiles(files);
    },
    [onFilesSelected]
  );

  const handleFiles = (files: File[]) => {
    const newFiles: UploadedFile[] = files.map((file) => ({
      id: Math.random().toString(36).substr(2, 9),
      name: file.name,
      size: formatFileSize(file.size),
      status: "uploading",
      progress: 0,
    }));

    setUploadedFiles((prev) => [...prev, ...newFiles]);

    // Simulate upload progress
    newFiles.forEach((file, index) => {
      let progress = 0;
      const interval = setInterval(() => {
        progress += 10;
        setUploadedFiles((prev) =>
          prev.map((f) =>
            f.id === file.id
              ? {
                  ...f,
                  progress,
                  status: progress === 100 ? "completed" : "uploading",
                }
              : f
          )
        );
        if (progress >= 100) {
          clearInterval(interval);
        }
      }, 200);
    });

    if (onFilesSelected) {
      onFilesSelected(files);
    }
  };

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + " " + sizes[i];
  };

  const removeFile = (id: string) => {
    setUploadedFiles((prev) => prev.filter((f) => f.id !== id));
  };

  return (
    <div className="space-y-6">
      {/* Drop Zone */}
      <div
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        className={`
          border-2 border-dashed rounded-lg p-12 text-center transition-colors
          ${
            isDragging
              ? "border-[#2563EB] bg-blue-50"
              : "border-[rgba(0,0,0,0.1)] hover:border-[#2563EB]"
          }
        `}
      >
        <div className="flex flex-col items-center gap-4">
          <div className="w-16 h-16 bg-[#F1F5F9] rounded-full flex items-center justify-center">
            <Upload className="w-8 h-8 text-[#2563EB]" />
          </div>
          <div>
            <h3 className="mb-2">Arrastra tus archivos aquí</h3>
            <p className="text-[#64748B] mb-4">
              o haz clic para seleccionar archivos
            </p>
            <input
              type="file"
              id="file-upload"
              multiple
              onChange={handleFileInput}
              className="hidden"
              accept=".pdf,.doc,.docx"
            />
            <Button 
              className="bg-[#2563EB] hover:bg-[#1E40AF]"
              onClick={() => document.getElementById('file-upload')?.click()}
            >
              Seleccionar Archivos
            </Button>
          </div>
          <p className="text-[#64748B]">
            Formatos soportados: PDF, DOC, DOCX (Máx. 10MB)
          </p>
        </div>
      </div>

      {/* Uploaded Files List */}
      {uploadedFiles.length > 0 && (
        <div className="space-y-3">
          <h3>Archivos cargados ({uploadedFiles.length})</h3>
          {uploadedFiles.map((file) => (
            <div
              key={file.id}
              className="border border-[rgba(0,0,0,0.1)] rounded-lg p-4"
            >
              <div className="flex items-start gap-4">
                <div className="flex-shrink-0">
                  <div className="w-10 h-10 bg-[#F1F5F9] rounded-lg flex items-center justify-center">
                    <FileText className="w-5 h-5 text-[#2563EB]" />
                  </div>
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-start justify-between gap-4 mb-2">
                    <div className="flex-1 min-w-0">
                      <p className="truncate">{file.name}</p>
                      <p className="text-[#64748B]">{file.size}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      {file.status === "completed" && (
                        <CheckCircle className="w-5 h-5 text-[#10B981]" />
                      )}
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => removeFile(file.id)}
                      >
                        <X className="w-4 h-4" />
                      </Button>
                    </div>
                  </div>
                  {file.status === "uploading" && (
                    <Progress value={file.progress} className="h-2" />
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
