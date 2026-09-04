"""parser.py — Extracción de datos del comprobante de aportes (PILA) de Simple.co.

Un comprobante Simple.co (Certificado de pago de aportes a seguridad social) es un PDF
de 1 página con estructura similar a SuAporte/AportesEnLínea:

    COMPROBANTE DE PAGO DE APORTES
    Fecha creación: ... Tipo Planilla: E  Número Planilla: 84815260
    Periodo Cotización: 202603  Periodo Servicio: 202604   PAGADA ...
    ... la empresa <RAZON SOCIAL>, con documento de identificación <NI/NIT>,
    canceló los aportes ... empleado <NOMBRE>, con CC <CEDULA>, dirigido a las siguientes entidades.
    Tipo Admin  Nit        Código      Nombre
    ARP         N890903790 14-11       ARL SURA
    AFP         N800229739 230201      PROTECCION
    EPS         N860066942 EPS008      COMPENSAR EPS
    CCF         N860013570 CCF21       CAFAM
    Tarifa ARL  Clase Riesgo  ...
    0.00522     1

El parseo es tolerante: nunca lanza excepción; devuelve lo que encuentre y deja la
información completa (líneas + aportes) en "metadata_json"/"observaciones".
"""
from __future__ import annotations

import logging
import re
from typing import Any

logger = logging.getLogger(__name__)

# Claves que tienen columna propia en simpleco_consultas
COLUMNAS = {
    "tipo_planilla", "numero_planilla", "periodo_cotizacion", "periodo_servicio",
    "fecha_comprobante", "empresa", "documento_identificacion", "empleado", "cedula",
    "tipo_admin", "nit_entidad", "codigo_entidad", "nombre_entidad",
}


def _normalizar_numero(val: str) -> str:
    """Devuelve solo dígitos (para cédulas/NIT)."""
    return re.sub(r"\D", "", val or "")


def _lineas(texto: str) -> list[str]:
    return [l.strip() for l in (texto or "").splitlines() if l.strip()]


def _buscar_claves(texto: str, pares: list[tuple[str, str]]) -> dict[str, str]:
    """Busca 'Clave   Valor' en párrafos separados por espacios (estructura plana)."""
    out: dict[str, str] = {}
    for clave, destino in pares:
        m = re.search(re.escape(clave) + r"\s+([A-Za-z0-9:/._\- ]{2,60}?)(?=\s+[A-Z][A-Za-z]*:|\s*$)", texto)
        if m:
            out[destino] = m.group(1).strip()
    return out


def parsear_comprobante(pdf_path: str) -> dict[str, Any]:
    """Lee el PDF y devuelve un dict con las columnas simples y el detalle completo."""
    try:
        import pdfplumber
    except ImportError:
        logger.warning("pdfplumber no está instalado; no se puede parsear el comprobante.")
        return {}

    resultado: dict[str, Any] = {}
    texto = ""
    tablas: list[list[list[str]]] = []

    try:
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                texto += (page.extract_text() or "") + "\n"
                try:
                    tablas.extend(page.extract_tables() or [])
                except Exception:
                    pass
    except Exception as e:
        logger.warning("No se pudo leer el PDF %s: %s", pdf_path, e)
        return {}

    if not texto.strip():
        return {}

    texto_plano = re.sub(r"\s+", " ", texto)

    # --- Campos planos (fila de cabecera) ---
    m = re.search(r"Tipo\s+Planilla[:\s]+([A-Za-z0-9]{1,10})", texto_plano)
    if m:
        resultado["tipo_planilla"] = m.group(1)
    m = re.search(r"(?:N[uú]mero|No\.?)\s+Planilla[:\s]+([0-9]+)", texto_plano)
    if m:
        resultado["numero_planilla"] = m.group(1)
    m = re.search(r"Periodo\s*Cotizaci[oó]n[:\s]+([0-9]{6})", texto_plano)
    if m:
        pc = m.group(1)
        resultado["periodo_cotizacion"] = f"{pc[0:4]}-{pc[4:6]}"
    m = re.search(r"Periodo\s*Servicio[:\s]+([0-9]{6})", texto_plano)
    if m:
        ps = m.group(1)
        resultado["periodo_servicio"] = f"{ps[0:4]}-{ps[4:6]}"
    m = re.search(r"Fecha\s+(?:de\s+)?creaci[oó]n[:\s]+([0-9]{4}[-/][0-9]{2}[-/][0-9]{2})", texto_plano)
    if m:
        resultado["fecha_comprobante"] = m.group(1).replace("/", "-")

    # --- Empresa (razón social + NIT) y empleado (nombre + CC) ---
    m = re.search(
        r"(?:la\s+)?empresa\s+([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ0-9.,& ]+?),\s+con\s+documento\s+de\s+"
        r"identificaci[oó]n\s+([A-Za-z]{0,3}?)[:]?\s*([0-9]+)",
        texto_plano,
    )
    if m:
        resultado["empresa"] = m.group(1).strip().rstrip(",")
        resultado["documento_identificacion"] = m.group(2) + m.group(3)
    m = re.search(
        r"empleado\s+([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ ]+?),\s+con\s+([A-Z]{1,3})\s*([0-9]+)",
        texto_plano,
    )
    if m:
        resultado["empleado"] = m.group(1).strip()
        resultado["cedula"] = _normalizar_numero(m.group(3))

    # --- Entidades (tabla Tipo Admin / Nit / Código / Nombre) ---
    entidades: list[dict[str, str]] = []
    for fila in tablas:
        for celdas in fila:
            l = [str(c or "").strip() for c in celdas]
            if not l or len(l) < 2:
                continue
            if l[0].upper() in ("ARP", "AFP", "EPS", "CCF", "CSS"):
                entidades.append({
                    "tipo": l[0].upper(),
                    "nit": l[1] if len(l) > 1 else "",
                    "codigo": l[2] if len(l) > 2 else "",
                    "nombre": l[3] if len(l) > 3 else "",
                })

    # Fallback: parsear entidades desde el texto plano (split por tipo conocido)
    if not entidades:
        for tok in re.finditer(r"(ARP|AFP|EPS|CCF)\s+(N?[0-9]+)\s+([^\s]{1,15})\s+([A-ZÁÉÍÓÚÑ0-9 &]{2,60}?)(?=\s+(?:ARP|AFP|EPS|CCF|Tarifa|Página|$))", texto_plano):
            entidades.append({"tipo": tok.group(1), "nit": tok.group(2), "codigo": tok.group(3), "nombre": tok.group(4).strip()})

    # Preferir la EPS como entidad principal de la consulta
    eps = next((e for e in entidades if e["tipo"] == "EPS"), None)
    ent = eps or (entidades[0] if entidades else None)
    if ent:
        resultado["tipo_admin"] = ent["tipo"]
        resultado["nit_entidad"] = _normalizar_numero(ent["nit"])
        resultado["codigo_entidad"] = ent.get("codigo", "")
        resultado["nombre_entidad"] = ent.get("nombre", "")
        if entidades:
            resultado["administradoras"] = entidades

    # --- Aportes / valores (detalle completo) ---
    valores: dict[str, str] = {}
    if eps:
        nombre_eps = eps.get("nombre", "")
        # Sección de liquidación: pares "Concepto" -> valor
        seg = texto_plano
        for concepto, patron in [
            ("ibc", r"IBC\s*[:]?\s*\$?\s*([0-9.,]+)"),
            ("salud", r"[Ss]alud\s*\$?\s*([0-9.,]+)"),
            ("pension", r"[Pp]ensi[oó]n\s*\$?\s*([0-9.,]+)"),
            ("arl", r"ARL\s*\$?\s*([0-9.,]+)"),
            ("total", r"TOTAL\s*\$?\s*([0-9.,]+)"),
        ]:
            mm = re.search(patron, seg)
            if mm:
                valores[concepto] = mm.group(1)
    if valores:
        resultado["valores"] = valores

    # Observaciones + texto crudo para revisión humana
    resultado["observaciones"] = texto.strip()[:1500]
    return resultado


def merge_para_campos_extra(parsed: dict[str, Any]) -> dict[str, Any]:
    """Vuelca el parseo en un dict apto para `registrar_consulta(campos_extra=...)`.

    Las claves que tienen columna van a su columna; las demás se guardan en metadata_json.
    """
    extras: dict[str, Any] = {}
    meta: dict[str, Any] = {}
    for k, v in parsed.items():
        if v is None or v == "" or (isinstance(v, (list, dict)) and not v):
            continue
        if k in COLUMNAS:
            extras[k] = v
        else:
            meta[k] = v
    if meta:
        extras["metadata_json"] = meta
    return extras