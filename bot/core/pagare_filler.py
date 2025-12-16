#!/usr/bin/env python3
"""
Módulo para llenar pagarés con datos extraídos por IA
"""

import logging
import os
import tempfile
from typing import Dict, Any, Optional, Tuple
import fitz  # PyMuPDF

logger = logging.getLogger('bybot.pagare_filler')

class PagareFiller:
    """Clase para llenar pagarés PDF con datos extraídos"""
    
    def __init__(self):
        """Inicializar llenador de pagarés"""
        pass
    
    def identificar_posiciones_campos(self, pdf_path: str) -> Dict[str, Any]:
        """
        Identificar las posiciones de los campos en el pagaré
        Retorna un diccionario con las coordenadas de cada campo
        
        Nota: Como los pagarés son PDFs escaneados (imágenes), usamos coordenadas aproximadas
        basadas en el tamaño de la página. En el futuro se puede mejorar usando Gemini Vision.
        """
        # Abrir PDF para obtener dimensiones
        doc = fitz.open(pdf_path)
        page = doc[0]
        page_width = page.rect.width
        page_height = page.rect.height
        
        # Si hay segunda página, obtener sus dimensiones también
        page2_height = page_height
        tiene_segunda_pagina = len(doc) > 1
        if tiene_segunda_pagina:
            page2 = doc[1]
            page2_height = page2.rect.height
        
        doc.close()
        
        # Coordenadas ajustadas para el pagaré de CREARCOOP
        # Los valores deben estar cerca de sus etiquetas en la columna izquierda
        # Las etiquetas están aproximadamente en: x = 0.10-0.15, los valores deben ir justo después
        posiciones = {
            'capital': {
                'x': page_width * 0.42,  # Justo después de la etiqueta "CAPITAL." (más cerca)
                'y': page_height * 0.18,  # Más arriba, cerca de la etiqueta
                'page': 0
            },
            'interes_plazo': {
                'x': page_width * 0.42,  # Justo después de la etiqueta "INTERÉS DE PLAZO."
                'y': page_height * 0.21,  # Un poco más abajo que capital
                'page': 0
            },
            'tasa_interes': {
                'x': page_width * 0.42,  # Justo después de la etiqueta "TASA DE INTERÉS PLAZO."
                'y': page_height * 0.24,  # Un poco más abajo que interes
                'page': 0
            },
            'fecha_vencimiento': {
                'x': page_width * 0.42,  # Justo después de la etiqueta "FECHA DE VENCIMIENTO."
                'y': page_height * 0.27,  # Más arriba, cerca de su etiqueta (no en el texto legal)
                'page': 0
            },
            'deudor_nombre': {
                'x': page_width * 0.30,  # Dentro del recuadro blanco de DEUDOR(ES)
                'y': page_height * 0.32,  # En el recuadro blanco, no en el texto legal
                'page': 0
            },
            'codeudor_nombre': {
                'x': page_width * 0.30,  # Dentro del recuadro blanco, debajo del deudor
                'y': page_height * 0.36,  # Un poco más abajo que deudor
                'page': 0
            },
            'deudor_cedula': {
                'x': page_width * 0.30,  # En el recuadro, debajo del nombre del deudor
                'y': page_height * 0.40,  # Más abajo que el nombre
                'page': 0
            },
            'codeudor_cedula': {
                'x': page_width * 0.30,  # En el recuadro, debajo del nombre del codeudor
                'y': page_height * 0.44,  # Más abajo que el nombre del codeudor
                'page': 0
            },
            'endoso': {
                'x': page_width * 0.20,
                'y': page2_height * 0.85,  # Cerca del final de la segunda página
                'page': 1 if tiene_segunda_pagina else 0
            }
        }
        
        return posiciones
    
    def llenar_pagare(self, pdf_path: str, datos: Dict[str, Any], output_path: Optional[str] = None) -> str:
        """
        Llenar un pagaré PDF con los datos proporcionados
        
        Args:
            pdf_path: Ruta al PDF del pagaré original
            datos: Diccionario con los datos a llenar
            output_path: Ruta donde guardar el pagaré llenado (opcional)
        
        Returns:
            Ruta del archivo PDF llenado
        """
        try:
            if not os.path.exists(pdf_path):
                raise FileNotFoundError(f"PDF no encontrado: {pdf_path}")
            
            # Abrir PDF
            doc = fitz.open(pdf_path)
            
            # Identificar posiciones de campos
            posiciones = self.identificar_posiciones_campos(pdf_path)
            
            # Preparar datos (datos ya viene con la estructura correcta desde el procesador)
            if not isinstance(datos, dict):
                raise ValueError(f"Los datos deben ser un diccionario, se recibió: {type(datos)}")
            
            logger.debug(f"📊 Estructura de datos recibida: {list(datos.keys())}")
            
            # Los datos vienen directamente con saldo_capital, saldo_interes, etc. (no anidados)
            try:
                capital = datos.get('saldo_capital', 0) or 0
                interes = datos.get('saldo_interes', 0) or 0
                mora = datos.get('saldo_mora', 0) or 0
                interes_plazo = interes + mora
                tasa_interes = datos.get('tasa_interes_efectiva_anual', 0) or 0
                fecha_vencimiento = datos.get('fecha_causacion', '') or ''
                
                logger.debug(f"📊 Datos extraídos: capital={capital}, interes={interes}, mora={mora}, tasa={tasa_interes}, fecha={fecha_vencimiento}")
            except Exception as e:
                logger.error(f"❌ Error extrayendo datos numéricos: {e}")
                logger.error(f"   Tipo de datos: {type(datos)}")
                logger.error(f"   Keys: {list(datos.keys()) if isinstance(datos, dict) else 'N/A'}")
                raise
            
            # Formatear valores
            capital_str = f"${capital:,.0f}" if capital else "$0"
            interes_plazo_str = f"${interes_plazo:,.0f}" if interes_plazo else "$0"
            tasa_interes_str = f"{tasa_interes:.2f}%" if tasa_interes else "0%"
            
            # Formatear fecha (de YYYY-MM-DD a DD/MM/YYYY)
            fecha_formateada = ""
            if fecha_vencimiento:
                try:
                    from datetime import datetime
                    fecha_obj = datetime.strptime(fecha_vencimiento, '%Y-%m-%d')
                    fecha_formateada = fecha_obj.strftime('%d/%m/%Y')
                except:
                    fecha_formateada = fecha_vencimiento
            
            # Datos del deudor
            deudor = datos.get('deudor', {})
            # Validar que deudor sea un diccionario
            if not isinstance(deudor, dict):
                logger.warning(f"⚠️ deudor no es un diccionario, es: {type(deudor)}. Usando diccionario vacío.")
                deudor = {}
            
            deudor_nombres = deudor.get('nombres', '') if isinstance(deudor, dict) else ''
            deudor_apellidos = deudor.get('apellidos', '') if isinstance(deudor, dict) else ''
            deudor_nombre = f"{deudor_nombres} {deudor_apellidos}".strip()
            deudor_cedula = deudor.get('numero_identificacion', '') if isinstance(deudor, dict) else ''
            
            # Datos del codeudor (si existe)
            codeudor = datos.get('codeudor', {})
            # Validar que codeudor sea un diccionario
            if not isinstance(codeudor, dict):
                logger.warning(f"⚠️ codeudor no es un diccionario, es: {type(codeudor)}. Usando diccionario vacío.")
                codeudor = {}
            
            codeudor_nombre = ""
            codeudor_cedula = ""
            if isinstance(codeudor, dict) and codeudor.get('numero_identificacion'):
                codeudor_nombres = codeudor.get('nombres', '')
                codeudor_apellidos = codeudor.get('apellidos', '')
                codeudor_nombre = f"{codeudor_nombres} {codeudor_apellidos}".strip()
                codeudor_cedula = codeudor.get('numero_identificacion', '') or ''
            
            # Texto del endoso
            texto_endoso = "Endoso en procuración a favor de:\nAndrés Bello Arias T.P. 378.676"
            
            # Llenar campos en la primera página
            if len(doc) > 0:
                page = doc[0]
                
                # Usar fontsize ligeramente más grande para mejor legibilidad
                fontsize = 11
                
                # CAPITAL - Justo después de la etiqueta "CAPITAL."
                if 'capital' in posiciones:
                    pos = posiciones['capital']
                    page.insert_text(
                        (pos['x'], pos['y']),
                        capital_str,
                        fontsize=fontsize,
                        color=(0, 0, 0),
                        render_mode=0  # Modo de renderizado normal
                    )
                
                # INTERÉS DE PLAZO - Justo después de la etiqueta "INTERÉS DE PLAZO."
                if 'interes_plazo' in posiciones:
                    pos = posiciones['interes_plazo']
                    page.insert_text(
                        (pos['x'], pos['y']),
                        interes_plazo_str,
                        fontsize=fontsize,
                        color=(0, 0, 0),
                        render_mode=0
                    )
                
                # TASA DE INTERÉS - Justo después de la etiqueta "TASA DE INTERÉS PLAZO."
                if 'tasa_interes' in posiciones:
                    pos = posiciones['tasa_interes']
                    page.insert_text(
                        (pos['x'], pos['y']),
                        tasa_interes_str,
                        fontsize=fontsize,
                        color=(0, 0, 0),
                        render_mode=0
                    )
                
                # FECHA DE VENCIMIENTO - Justo después de la etiqueta, no en el texto legal
                if 'fecha_vencimiento' in posiciones and fecha_formateada:
                    pos = posiciones['fecha_vencimiento']
                    page.insert_text(
                        (pos['x'], pos['y']),
                        fecha_formateada,
                        fontsize=fontsize,
                        color=(0, 0, 0),
                        render_mode=0
                    )
                
                # DEUDOR - Nombre (en el recuadro blanco, no en el texto legal)
                if 'deudor_nombre' in posiciones and deudor_nombre:
                    pos = posiciones['deudor_nombre']
                    page.insert_text(
                        (pos['x'], pos['y']),
                        deudor_nombre,
                        fontsize=fontsize,
                        color=(0, 0, 0),
                        render_mode=0
                    )
                
                # CODEUDOR - Nombre (en el recuadro blanco, debajo del deudor)
                if 'codeudor_nombre' in posiciones and codeudor_nombre:
                    pos = posiciones['codeudor_nombre']
                    page.insert_text(
                        (pos['x'], pos['y']),
                        codeudor_nombre,
                        fontsize=fontsize,
                        color=(0, 0, 0),
                        render_mode=0
                    )
                
                # DEUDOR - Cédula (en el recuadro blanco)
                if 'deudor_cedula' in posiciones and deudor_cedula:
                    pos = posiciones['deudor_cedula']
                    page.insert_text(
                        (pos['x'], pos['y']),
                        deudor_cedula,
                        fontsize=fontsize,
                        color=(0, 0, 0),
                        render_mode=0
                    )
                
                # CODEUDOR - Cédula (en el recuadro blanco)
                if 'codeudor_cedula' in posiciones and codeudor_cedula:
                    pos = posiciones['codeudor_cedula']
                    page.insert_text(
                        (pos['x'], pos['y']),
                        codeudor_cedula,
                        fontsize=fontsize,
                        color=(0, 0, 0),
                        render_mode=0
                    )
            
            # Agregar texto del endoso en la segunda página
            if len(doc) > 1:
                page2 = doc[1]
                
                # Buscar el texto "Personería Jurídica No. 131 de 1972 Vigilada SUPERSOLIDARIA"
                # y agregar el endoso debajo
                if 'endoso' in posiciones:
                    pos = posiciones['endoso']
                    page2.insert_text(
                        (pos['x'], pos['y']),
                        texto_endoso,
                        fontsize=10,
                        color=(0, 0, 0)
                    )
            elif len(doc) == 1:
                # Si solo hay una página, agregar el endoso al final
                page = doc[0]
                if 'endoso' in posiciones:
                    pos = posiciones['endoso']
                    # Ajustar posición para que esté al final de la primera página
                    pos_y = page.rect.height * 0.85
                    page.insert_text(
                        (pos['x'], pos_y),
                        texto_endoso,
                        fontsize=10,
                        color=(0, 0, 0)
                    )
            
            # Determinar ruta de salida
            if output_path is None:
                temp_dir = tempfile.gettempdir()
                base_name = os.path.splitext(os.path.basename(pdf_path))[0]
                output_path = os.path.join(
                    temp_dir,
                    f"bybot_pagare_llenado_{base_name}.pdf"
                )
            
            # Guardar PDF llenado
            doc.save(output_path)
            doc.close()
            
            logger.info(f"✅ Pagaré llenado guardado en: {output_path}")
            return output_path
            
        except Exception as e:
            logger.error(f"❌ Error llenando pagaré: {e}")
            raise

