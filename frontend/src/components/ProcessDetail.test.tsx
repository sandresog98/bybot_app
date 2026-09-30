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
  busyUpload: false,
  busyAnalyze: false,
  onUpload: vi.fn().mockResolvedValue(undefined),
  onAnalyze: vi.fn().mockResolvedValue(undefined),
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
    expect(screen.getByText('2.0 KB')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Analizar IA' }));
    expect(baseProps.onAnalyze).toHaveBeenCalledWith(7);
  });

  it('describe la descarga de un archivo', async () => {
    render(<ProcessDetail {...baseProps} />);
    await userEvent.click(screen.getByTitle('Descargar'));
    expect(baseProps.onDownload).toHaveBeenCalledWith(7, 'extracto.pdf');
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
});