import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ProcessDetail } from './ProcessDetail';
import type { Detail } from '../types';

const detail: Detail = {
  id: 1,
  code: 'PR-2026-AAA',
  title: 'Proceso A',
  status: 'files_uploaded',
  createdAt: '',
  files: [{ id: 7, originalName: 'extracto.pdf', mimeType: 'application/pdf', sizeBytes: 2048 }],
  analyses: [{ id: 9, status: 'completed', result: { resumen: 'ok' } }],
};

const baseProps = {
  selected: detail,
  structured: null,
  busyUpload: false,
  busyAnalyze: false,
  busyAnalyzeAll: false,
  busyConsolidate: false,
  onUpload: vi.fn().mockResolvedValue(undefined),
  onAnalyze: vi.fn().mockResolvedValue(undefined),
  onAnalyzeAll: vi.fn().mockResolvedValue(undefined),
  onConsolidate: vi.fn().mockResolvedValue(undefined),
  onDelete: vi.fn().mockResolvedValue(undefined),
  onReplace: vi.fn().mockResolvedValue(undefined),
  onView: vi.fn(),
  onDownload: vi.fn(),
  onValidar: vi.fn().mockResolvedValue(undefined),
};

describe('ProcessDetail', () => {
  it('muestra placeholder cuando no hay proceso seleccionado', () => {
    render(<ProcessDetail {...baseProps} selected={null} />);
    expect(screen.getByText('Selecciona o crea un proceso.')).toBeInTheDocument();
  });

  it('lista archivos, tamaño e inicia análisis por archivo', async () => {
    render(<ProcessDetail {...baseProps} />);
    expect(screen.getByText('extracto.pdf')).toBeInTheDocument();
    expect(screen.getByText(/2\.0 KB/)).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Analizar IA' }));
    expect(baseProps.onAnalyze).toHaveBeenCalledWith(7);
  });

  it('describe la descarga de un archivo', async () => {
    render(<ProcessDetail {...baseProps} />);
    await userEvent.click(screen.getByTitle('Descargar'));
    expect(baseProps.onDownload).toHaveBeenCalledWith(7, 'extracto.pdf');
  });

  it('abre la vista previa del archivo', async () => {
    const onView = vi.fn();
    render(<ProcessDetail {...baseProps} onView={onView} />);
    await userEvent.click(screen.getByRole('button', { name: 'Ver' }));
    expect(onView).toHaveBeenCalledWith(7, 'extracto.pdf');
  });

  it('envía el archivo elegido al subir', async () => {
    const props = { ...baseProps, onUpload: vi.fn().mockResolvedValue(undefined) };
    const { container } = render(<ProcessDetail {...props} />);
    const input = container.querySelector<HTMLInputElement>('input[type="file"]')!;
    const file = new File(['contenido'], 'datos.csv', { type: 'text/csv' });
    await userEvent.upload(input, file);
    fireEvent.submit(container.querySelector('form')!);
    expect(props.onUpload).toHaveBeenCalledTimes(1);
    const form = props.onUpload.mock.calls[0][0] as FormData;
    expect(form.get('file')).toBeInstanceOf(File);
  });

  it('muestra resultados de análisis con validación', () => {
    render(<ProcessDetail {...baseProps} />);
    expect(screen.getByText('Resumen')).toBeInTheDocument();
    expect(screen.getByText('ok')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Editar' })).toBeInTheDocument();
  });

  it('ofrece analizar todos los archivos y eliminar con confirmación', async () => {
    const onAnalyzeAll = vi.fn().mockResolvedValue(undefined);
    const onDelete = vi.fn().mockResolvedValue(undefined);
    render(<ProcessDetail {...baseProps} onAnalyzeAll={onAnalyzeAll} onDelete={onDelete} />);
    await userEvent.click(screen.getByRole('button', { name: 'Analizar todos los archivos IA' }));
    expect(onAnalyzeAll).toHaveBeenCalledTimes(1);
    await userEvent.click(screen.getByRole('button', { name: 'Eliminar' }));
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar' }));
    expect(onDelete).toHaveBeenCalledWith(7);
  });

  it('agrupa por archivo y muestra el historial de ejecuciones', () => {
    const withHistory: Detail = {
      ...detail,
      analyses: [
        { id: 12, status: 'completed', fileId: 7, result: { resumen: 'nuevo' } },
        { id: 6, status: 'failed', fileId: 7, error: 'timeout' },
      ],
    };
    render(<ProcessDetail {...baseProps} selected={withHistory} />);
    expect(screen.getByText('nuevo')).toBeInTheDocument();
    expect(screen.getByText(/Historial de ejecuciones \(2\)/)).toBeInTheDocument();
  });

  it('permite encolar la consolidación del proceso', async () => {
    const onConsolidate = vi.fn().mockResolvedValue(undefined);
    render(<ProcessDetail {...baseProps} onConsolidate={onConsolidate} />);
    await userEvent.click(screen.getByRole('button', { name: 'Consolidar análisis del proceso' }));
    expect(onConsolidate).toHaveBeenCalledTimes(1);
  });

  it('muestra los datos estructurados del proceso', () => {
    const structured = {
      partes: [{ id: 1, rol: 'deudor', orden: 0, nombreCompleto: 'Ana Pérez', numeroDocumento: '123' }],
      credito: null, movimientos: [], cuotas: [], campos: [],
    };
    render(<ProcessDetail {...baseProps} structured={structured} />);
    expect(screen.getByText('Ana Pérez')).toBeInTheDocument();
    expect(screen.getByText('Deudor')).toBeInTheDocument();
  });
});