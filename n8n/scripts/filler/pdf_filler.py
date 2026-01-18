"""
Llenador de PDF para pagarés
Utiliza PyMuPDF para insertar texto en coordenadas específicas
"""

import json
import logging
from pathlib import Path
from typing import Dict, Any, Optional, List, Tuple

import fitz  # PyMuPDF

import sys
sys.path.append(str(Path(__file__).parent.parent))

from shared.utils import format_currency, number_to_words, format_date, parse_date

logger = logging.getLogger(__name__)


class PDFFiller:
    """
    Clase para llenar PDFs con datos extraídos
    """
    
    def __init__(self, template_config: Dict[str, Any] = None):
        """
        Args:
            template_config: Configuración de posiciones de campos
        """
        self.config = template_config or self._get_default_config()
    
    def fill_pagare(self, 
                    pdf_path: Path, 
                    datos: Dict[str, Any],
                    output_path: Path = None) -> Path:
        """
        Llena un pagaré con los datos proporcionados.
        
        Args:
            pdf_path: Ruta al PDF original
            datos: Datos para llenar
            output_path: Ruta de salida (opcional)
        
        Returns:
            Path del PDF llenado
        """
        if output_path is None:
            output_path = pdf_path.parent / f"llenado_{pdf_path.name}"
        
        # Abrir PDF
        doc = fitz.open(str(pdf_path))
        
        try:
            # Procesar cada página
            for page_num in range(len(doc)):
                page = doc[page_num]
                page_config = self.config.get('pages', {}).get(str(page_num), {})
                
                # Insertar campos para esta página
                self._fill_page(page, datos, page_config)
            
            # Guardar PDF
            doc.save(str(output_path))
            logger.info(f"PDF llenado guardado: {output_path}")
            
            return output_path
            
        finally:
            doc.close()
    
    def _fill_page(self, 
                   page: fitz.Page, 
                   datos: Dict[str, Any],
                   page_config: Dict[str, Any]) -> None:
        """
        Llena una página específica del PDF.
        """
        campos = page_config.get('campos', [])
        
        for campo in campos:
            try:
                # Obtener valor del campo
                valor = self._get_valor(campo, datos)
                
                if valor is None or valor == '':
                    continue
                
                # Obtener posición
                x = campo.get('x', 0)
                y = campo.get('y', 0)
                
                # Configuración de fuente
                font_size = campo.get('font_size', 10)
                font_name = campo.get('font', 'helv')
                color = campo.get('color', (0, 0, 0))  # Negro por defecto
                
                # Insertar texto
                page.insert_text(
                    point=(x, y),
                    text=str(valor),
                    fontsize=font_size,
                    fontname=font_name,
                    color=color
                )
                
                logger.debug(f"Campo '{campo.get('nombre')}' insertado en ({x}, {y}): {valor}")
                
            except Exception as e:
                logger.warning(f"Error insertando campo {campo.get('nombre', 'unknown')}: {e}")
    
    def _get_valor(self, campo: Dict[str, Any], datos: Dict[str, Any]) -> Optional[str]:
        """
        Obtiene el valor formateado para un campo.
        """
        # Ruta al dato (ej: "deudor.nombre_completo" o "estado_cuenta.total_deuda")
        path = campo.get('path', '')
        formato = campo.get('formato', 'text')
        
        # Navegar por el path
        valor = datos
        for key in path.split('.'):
            if isinstance(valor, dict):
                valor = valor.get(key)
            else:
                valor = None
                break
        
        if valor is None:
            return campo.get('default', '')
        
        # Aplicar formato
        if formato == 'currency':
            return format_currency(float(valor))
        elif formato == 'currency_words':
            return number_to_words(float(valor))
        elif formato == 'date':
            date = parse_date(str(valor))
            if date:
                return format_date(date)
            return valor
        elif formato == 'date_short':
            date = parse_date(str(valor))
            if date:
                return date.strftime('%d/%m/%Y')
            return valor
        elif formato == 'uppercase':
            return str(valor).upper()
        elif formato == 'number':
            return f"{float(valor):,.0f}".replace(',', '.')
        else:
            return str(valor)
    
    def detect_fields(self, pdf_path: Path) -> List[Dict[str, Any]]:
        """
        Detecta campos de formulario existentes en el PDF.
        Útil para PDFs con campos editables.
        """
        doc = fitz.open(str(pdf_path))
        fields = []
        
        try:
            for page_num in range(len(doc)):
                page = doc[page_num]
                
                # Buscar widgets (campos de formulario)
                for widget in page.widgets():
                    field_info = {
                        'nombre': widget.field_name,
                        'tipo': widget.field_type_string,
                        'pagina': page_num,
                        'rect': {
                            'x0': widget.rect.x0,
                            'y0': widget.rect.y0,
                            'x1': widget.rect.x1,
                            'y1': widget.rect.y1
                        },
                        'valor_actual': widget.field_value
                    }
                    fields.append(field_info)
            
            return fields
            
        finally:
            doc.close()
    
    def fill_form_fields(self, 
                         pdf_path: Path,
                         field_values: Dict[str, str],
                         output_path: Path = None) -> Path:
        """
        Llena campos de formulario existentes en el PDF.
        """
        if output_path is None:
            output_path = pdf_path.parent / f"llenado_{pdf_path.name}"
        
        doc = fitz.open(str(pdf_path))
        
        try:
            for page in doc:
                for widget in page.widgets():
                    if widget.field_name in field_values:
                        widget.field_value = field_values[widget.field_name]
                        widget.update()
            
            doc.save(str(output_path))
            return output_path
            
        finally:
            doc.close()
    
    def _get_default_config(self) -> Dict[str, Any]:
        """
        Retorna configuración por defecto para pagaré estándar.
        """
        return {
            "nombre": "Pagaré Estándar",
            "version": "1.0",
            "pages": {
                "0": {
                    "campos": [
                        # Encabezado
                        {
                            "nombre": "numero_pagare",
                            "path": "estado_cuenta.numero_credito",
                            "x": 450,
                            "y": 80,
                            "font_size": 12,
                            "formato": "text"
                        },
                        {
                            "nombre": "ciudad",
                            "path": "deudor.ciudad",
                            "x": 100,
                            "y": 120,
                            "font_size": 11,
                            "formato": "uppercase"
                        },
                        {
                            "nombre": "fecha",
                            "path": "estado_cuenta.fecha_corte",
                            "x": 350,
                            "y": 120,
                            "font_size": 11,
                            "formato": "date"
                        },
                        
                        # Valor
                        {
                            "nombre": "valor_numerico",
                            "path": "estado_cuenta.total_deuda",
                            "x": 480,
                            "y": 150,
                            "font_size": 11,
                            "formato": "currency"
                        },
                        {
                            "nombre": "valor_letras",
                            "path": "estado_cuenta.total_deuda",
                            "x": 100,
                            "y": 180,
                            "font_size": 10,
                            "formato": "currency_words"
                        },
                        
                        # Datos del deudor
                        {
                            "nombre": "deudor_nombre",
                            "path": "deudor.nombre_completo",
                            "x": 100,
                            "y": 250,
                            "font_size": 11,
                            "formato": "uppercase"
                        },
                        {
                            "nombre": "deudor_documento",
                            "path": "deudor.numero_documento",
                            "x": 100,
                            "y": 275,
                            "font_size": 11,
                            "formato": "text"
                        },
                        {
                            "nombre": "deudor_expedicion",
                            "path": "deudor.lugar_expedicion",
                            "x": 300,
                            "y": 275,
                            "font_size": 11,
                            "formato": "text"
                        },
                        {
                            "nombre": "deudor_direccion",
                            "path": "deudor.direccion",
                            "x": 100,
                            "y": 300,
                            "font_size": 10,
                            "formato": "text"
                        },
                        {
                            "nombre": "deudor_telefono",
                            "path": "deudor.celular",
                            "x": 400,
                            "y": 300,
                            "font_size": 10,
                            "formato": "text"
                        },
                        
                        # Tasas
                        {
                            "nombre": "tasa_corriente",
                            "path": "estado_cuenta.tasa_interes_corriente",
                            "x": 200,
                            "y": 350,
                            "font_size": 10,
                            "formato": "number"
                        },
                        {
                            "nombre": "tasa_mora",
                            "path": "estado_cuenta.tasa_interes_mora",
                            "x": 400,
                            "y": 350,
                            "font_size": 10,
                            "formato": "number"
                        }
                    ]
                }
            }
        }
    
    @classmethod
    def load_config(cls, config_path: Path) -> 'PDFFiller':
        """
        Carga configuración desde un archivo JSON.
        """
        with open(config_path, 'r', encoding='utf-8') as f:
            config = json.load(f)
        return cls(config)

