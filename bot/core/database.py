#!/usr/bin/env python3
"""
Gestor de base de datos para el Bot
"""

import mysql.connector
import logging
from mysql.connector import Error, pooling
from typing import List, Dict, Any, Optional
from config.settings import DB_CONFIG

logger = logging.getLogger('bybot.database')

class DatabaseManager:
    """Gestor de conexiones y operaciones de base de datos"""
    
    _pool = None
    
    @classmethod
    def get_connection(cls):
        """Obtener conexión del pool"""
        if cls._pool is None:
            try:
                cls._pool = pooling.MySQLConnectionPool(
                    pool_name="bybot_pool",
                    pool_size=5,
                    pool_reset_session=True,
                    **DB_CONFIG
                )
                logger.info("✅ Pool de conexiones creado")
            except Error as e:
                logger.error(f"❌ Error creando pool de conexiones: {e}")
                raise
        
        try:
            return cls._pool.get_connection()
        except Error as e:
            logger.error(f"❌ Error obteniendo conexión del pool: {e}")
            raise
    
    @staticmethod
    def execute_query(query: str, params: Optional[tuple] = None, fetch_one: bool = False) -> Any:
        """Ejecutar consulta SELECT"""
        conn = None
        cursor = None
        try:
            conn = DatabaseManager.get_connection()
            cursor = conn.cursor(dictionary=True)
            cursor.execute(query, params or ())
            
            if fetch_one:
                result = cursor.fetchone()
            else:
                result = cursor.fetchall()
            
            return result
        except Error as e:
            logger.error(f"❌ Error ejecutando consulta: {e}")
            raise
        finally:
            if cursor:
                cursor.close()
            if conn:
                conn.close()
    
    @staticmethod
    def execute_update(query: str, params: Optional[tuple] = None) -> int:
        """Ejecutar UPDATE/INSERT/DELETE"""
        conn = None
        cursor = None
        try:
            conn = DatabaseManager.get_connection()
            cursor = conn.cursor()
            cursor.execute(query, params or ())
            conn.commit()
            affected_rows = cursor.rowcount
            return affected_rows
        except Error as e:
            if conn:
                conn.rollback()
            logger.error(f"❌ Error ejecutando actualización: {e}")
            raise
        finally:
            if cursor:
                cursor.close()
            if conn:
                conn.close()

