import { useEffect, useRef, useState } from 'react';
import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist/legacy/build/pdf.mjs';
import workerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url';

GlobalWorkerOptions.workerSrc = workerUrl;

function PdfPage({ document, pageNumber, width, onError }) {
  const canvasRef = useRef(null);

  useEffect(() => {
    if (!width) return undefined;

    let cancelled = false;
    let renderTask;

    const render = async () => {
      try {
        const page = await document.getPage(pageNumber);
        if (cancelled || !canvasRef.current) return;

        const baseViewport = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: Math.min(width / baseViewport.width, 2) });
        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        const canvas = canvasRef.current;
        const context = canvas.getContext('2d');
        canvas.width = Math.floor(viewport.width * pixelRatio);
        canvas.height = Math.floor(viewport.height * pixelRatio);
        canvas.style.width = `${viewport.width}px`;
        canvas.style.height = `${viewport.height}px`;

        renderTask = page.render({
          canvasContext: context,
          viewport,
          transform: pixelRatio === 1 ? null : [pixelRatio, 0, 0, pixelRatio, 0, 0],
        });
        await renderTask.promise;
      } catch (error) {
        if (!cancelled && error?.name !== 'RenderingCancelledException') {
          onError('Unable to render the PDF pages.');
        }
      }
    };

    render();
    return () => {
      cancelled = true;
      renderTask?.cancel();
    };
  }, [document, pageNumber, width, onError]);

  return <canvas ref={canvasRef} role="img" aria-label={`PDF page ${pageNumber}`} className="mx-auto max-w-full bg-white shadow-md" />;
}

export default function PdfPages({ file }) {
  const containerRef = useRef(null);
  const [width, setWidth] = useState(0);
  const [document, setDocument] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return undefined;

    const observer = new ResizeObserver(([entry]) => {
      setWidth(Math.max(0, Math.floor(entry.contentRect.width - 24)));
    });
    observer.observe(container);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    let active = true;
    let loadingTask;
    setDocument(null);
    setError('');

    const load = async () => {
      try {
        const buffer = await file.arrayBuffer();
        if (!active) return;
        loadingTask = getDocument({ data: new Uint8Array(buffer), useSystemFonts: true });
        const pdf = await loadingTask.promise;
        if (active) setDocument(pdf);
      } catch {
        if (active) setError('Unable to read the PDF file.');
      }
    };

    load();
    return () => {
      active = false;
      loadingTask?.destroy();
    };
  }, [file]);

  return (
    <div ref={containerRef} className="min-h-full w-full">
      {error && <p role="alert" className="py-12 text-center text-sm text-red-600 dark:text-red-400">{error}</p>}
      {!error && !document && <p className="py-12 text-center text-sm text-slate-600 dark:text-slate-300">Rendering PDF...</p>}
      {document && !error && <div className="space-y-4 py-1">{Array.from({ length: document.numPages }, (_, index) => (
        <PdfPage key={`${index + 1}-${width}`} document={document} pageNumber={index + 1} width={width} onError={setError} />
      ))}</div>}
    </div>
  );
}
