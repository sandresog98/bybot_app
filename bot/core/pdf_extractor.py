#!/usr/bin/env python3
"""
Utilidades para extraer páginas de PDFs
"""

import logging
import os
import tempfile
from typing import List, Optional

logger = logging.getLogger('bybot.pdf_extractor')

class PDFExtractor:
    """Clase para extraer páginas específicas de PDFs"""
    
    @staticmethod
    def extract_pages(pdf_path: str, page_numbers: List[int], output_path: Optional[str] = None) -> str:
        """
        Extraer páginas específicas de un PDF
        Intenta primero con pypdf, si falla usa PyMuPDF (más robusto)
        
        Args:
            pdf_path: Ruta al PDF original
            page_numbers: Lista de números de página a extraer (1-based)
            output_path: Ruta donde guardar el PDF extraído (opcional, se crea temporal si no se proporciona)
        
        Returns:
            Ruta del archivo PDF extraído
        """
        if not os.path.exists(pdf_path):
            raise FileNotFoundError(f"PDF no encontrado: {pdf_path}")
        
        # Determinar ruta de salida
        if output_path is None:
            temp_dir = tempfile.gettempdir()
            base_name = os.path.splitext(os.path.basename(pdf_path))[0]
            output_path = os.path.join(
                temp_dir,
                f"bybot_extracted_{base_name}_pages_{'_'.join(map(str, page_numbers))}.pdf"
            )
        
        # Intentar primero con pypdf
        try:
            return PDFExtractor._extract_with_pypdf(pdf_path, page_numbers, output_path)
        except Exception as e:
            logger.warning(f"⚠️ Error con pypdf: {e}. Intentando con PyMuPDF...")
            # Si falla, intentar con PyMuPDF
            try:
                return PDFExtractor._extract_with_pymupdf(pdf_path, page_numbers, output_path)
            except Exception as e2:
                logger.error(f"❌ Error extrayendo páginas de PDF con ambos métodos: pypdf={e}, PyMuPDF={e2}")
                raise Exception(f"No se pudo extraer páginas. Último error: {e2}")
    
    @staticmethod
    def _extract_with_pypdf(pdf_path: str, page_numbers: List[int], output_path: str) -> str:
        """Extraer páginas usando pypdf"""
        from pypdf import PdfReader, PdfWriter
        
        # Leer PDF
        reader = PdfReader(pdf_path, strict=False)  # strict=False para ser más tolerante
        total_pages = len(reader.pages)
        
        # Validar números de página
        for page_num in page_numbers:
            if page_num < 1 or page_num > total_pages:
                raise ValueError(f"Página {page_num} fuera de rango (1-{total_pages})")
        
        # Crear writer
        writer = PdfWriter()
        
        # Agregar páginas solicitadas (convertir de 1-based a 0-based)
        for page_num in page_numbers:
            page_index = page_num - 1  # Convertir a 0-based
            writer.add_page(reader.pages[page_index])
        
        # Guardar PDF extraído
        with open(output_path, 'wb') as output_file:
            writer.write(output_file)
        
        logger.info(f"✅ Páginas {page_numbers} extraídas con pypdf de {pdf_path} → {output_path}")
        return output_path
    
    @staticmethod
    def _extract_with_pymupdf(pdf_path: str, page_numbers: List[int], output_path: str) -> str:
        """Extraer páginas usando PyMuPDF (más robusto)"""
        import fitz  # PyMuPDF
        
        # Abrir PDF
        doc = fitz.open(pdf_path)
        total_pages = len(doc)
        
        # Validar números de página
        for page_num in page_numbers:
            if page_num < 1 or page_num > total_pages:
                doc.close()
                raise ValueError(f"Página {page_num} fuera de rango (1-{total_pages})")
        
        # Crear nuevo documento
        new_doc = fitz.open()
        
        # Agregar páginas solicitadas (convertir de 1-based a 0-based)
        for page_num in page_numbers:
            page_index = page_num - 1  # Convertir a 0-based
            new_doc.insert_pdf(doc, from_page=page_index, to_page=page_index)
        
        # Guardar PDF extraído
        new_doc.save(output_path)
        new_doc.close()
        doc.close()
        
        logger.info(f"✅ Páginas {page_numbers} extraídas con PyMuPDF de {pdf_path} → {output_path}")
        return output_path

