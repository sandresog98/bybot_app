#!/usr/bin/env python3
"""
Script para probar la conexión con el servidor PHP
"""

import requests
import os
from dotenv import load_dotenv

# Cargar .env
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../.env'))

base_url = os.getenv('SERVER_BASE_URL', '').rstrip('/')
if not base_url.endswith('/admin'):
    base_url = base_url + '/admin'

api_token = os.getenv('BOT_API_TOKEN', '').strip()

print("=" * 60)
print("🧪 PRUEBA DE CONEXIÓN CON SERVIDOR PHP")
print("=" * 60)
print(f"\n📋 Configuración:")
print(f"   URL Base: {base_url}")
print(f"   Token (longitud): {len(api_token)}")
print(f"   Token (primeros 20): {api_token[:20]}...")
print(f"   Token (últimos 10): ...{api_token[-10:]}")
print()

# Probar endpoint
url = f"{base_url}/modules/crear_coop/api/serve_file_for_bot.php"
params = {
    'proceso_id': 1,
    'tipo': 'estado_cuenta'
}

headers = {
    'X-API-Token': api_token
}

print(f"🔗 URL: {url}")
print(f"📤 Headers: X-API-Token = {api_token[:20]}...")
print(f"📋 Params: {params}")
print()

try:
    print("📡 Enviando petición...")
    response = requests.get(url, params=params, headers=headers, timeout=10)
    
    print(f"\n📥 Respuesta:")
    print(f"   Status Code: {response.status_code}")
    print(f"   Headers recibidos: {dict(response.headers)}")
    
    if response.status_code == 200:
        print("✅ ¡Conexión exitosa! El token es válido.")
        print(f"   Tamaño de respuesta: {len(response.content)} bytes")
    elif response.status_code == 401:
        print("❌ Error 401: Token de API requerido")
        print(f"   Respuesta: {response.text}")
    elif response.status_code == 403:
        print("❌ Error 403: Token de API inválido")
        try:
            error_data = response.json()
            print(f"   Mensaje: {error_data.get('error', 'N/A')}")
            if 'debug' in error_data:
                print(f"\n   🔍 Información de Debug:")
                debug = error_data['debug']
                print(f"      Longitud recibida: {debug.get('received_length', 'N/A')}")
                print(f"      Longitud esperada: {debug.get('expected_length', 'N/A')}")
                print(f"      Inicio recibido: {debug.get('received_start', 'N/A')}")
                print(f"      Inicio esperado: {debug.get('expected_start', 'N/A')}")
                print(f"      Final recibido: {debug.get('received_end', 'N/A')}")
                print(f"      Final esperado: {debug.get('expected_end', 'N/A')}")
                if 'received_hex' in debug:
                    print(f"      Hex recibido: {debug.get('received_hex', 'N/A')}")
                    print(f"      Hex esperado: {debug.get('expected_hex', 'N/A')}")
            if 'hint' in error_data:
                print(f"\n   💡 Hint: {error_data['hint']}")
        except Exception as e:
            print(f"   Respuesta: {response.text}")
            print(f"   Error parseando JSON: {e}")
    elif response.status_code == 404:
        print("❌ Error 404: Archivo o proceso no encontrado")
        print(f"   Respuesta: {response.text}")
    else:
        print(f"❌ Error {response.status_code}")
        print(f"   Respuesta: {response.text[:500]}")
        
except requests.exceptions.ConnectionError as e:
    print(f"❌ Error de conexión: {e}")
    print("   Verifica que el servidor esté accesible")
except requests.exceptions.Timeout:
    print("❌ Timeout: El servidor no respondió a tiempo")
except Exception as e:
    print(f"❌ Error inesperado: {e}")

print("\n" + "=" * 60)

