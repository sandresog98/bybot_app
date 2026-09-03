"""
documentos.py — Normalización de formatos de archivo para enviar a Gemini.

Gemini acepta PDF e imágenes png/jpeg/webp de forma nativa, pero NO TIFF. Este
módulo convierte al vuelo los formatos no soportados (TIFF→PDF) sin alterar el
original almacenado.

Seguridad: se limita el número de píxeles que Pillow decodifica (anti image /
decompression bomb) y el número de páginas del TIFF.
"""
import io
from pathlib import Path

from PIL import Image

# Anti decompression/image bomb: tope de píxeles que Pillow decodificará.
# Por encima de 2x este valor, Pillow lanza DecompressionBombError.
Image.MAX_IMAGE_PIXELS = 64_000_000  # ~64 MP

# Formatos que Gemini acepta directamente (inline o File API).
GEMINI_OK_MIMES = {"application/pdf", "image/png", "image/jpeg", "image/webp"}
TIFF_MIMES = {"image/tiff", "image/tif"}

# Tope de páginas al convertir un TIFF multipágina.
MAX_TIFF_PAGES = 30


def to_gemini_bytes(path, mime: str) -> tuple[bytes, str]:
    """
    Devuelve (bytes, mime) apto para Gemini a partir del archivo en `path`.
    - Formatos soportados: se leen tal cual.
    - TIFF: se convierte a PDF multipágina.
    El archivo original NO se modifica.
    """
    p = Path(path)
    m = (mime or "").lower()
    if m in GEMINI_OK_MIMES:
        return p.read_bytes(), m
    if m in TIFF_MIMES:
        return _tiff_to_pdf(p), "application/pdf"
    # Formato desconocido: pasar tal cual (Gemini lo rechazará si no lo soporta).
    return p.read_bytes(), m


def _tiff_to_pdf(path: Path) -> bytes:
    """Convierte un TIFF (posiblemente multipágina/bilevel) a un PDF en memoria."""
    try:
        with Image.open(path) as img:
            frames: list[Image.Image] = []
            idx = 0
            while idx < MAX_TIFF_PAGES:
                try:
                    img.seek(idx)
                except EOFError:
                    break
                # convert('RGB') carga y normaliza el frame (bilevel group4 incluido).
                frames.append(img.convert("RGB"))
                idx += 1
            if not frames:
                raise ValueError("TIFF sin páginas legibles")
            buf = io.BytesIO()
            first, rest = frames[0], frames[1:]
            first.save(buf, format="PDF", save_all=bool(rest), append_images=rest)
            return buf.getvalue()
    except Image.DecompressionBombError as e:
        raise ValueError(f"Imagen demasiado grande (posible image bomb): {e}") from e
