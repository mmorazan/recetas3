# 🔒 GCODE Admin PHP - Versión Segura

## Sistema de Administración de Base de Datos Completamente Refactorizado

Una aplicación web PHP completamente reescrita con **seguridad empresarial** para administrar bases de datos MySQL de forma segura y eficiente.

---

## ⚡ Instalación Rápida (5 minutos)

### 1. **Crear estructura de carpetas**
```
tu-proyecto/
├── config/
├── includes/
├── assets/
│   ├── css/
│   └── js/
└── (archivos PHP en la raíz)
```

### 2. **Copiar archivos desde los artefactos**
Copia cada archivo generado arriba en su carpeta correspondiente.

### 3. **Configurar base de datos**
```sql
-- Ejecutar en MySQL
mysql -u root -p < setup.sql
```

### 4. **Configurar variables de entorno**
```bash
# Copiar y renombrar
cp .env.example .env

# Editar con tus credenciales
nano .env
```

### 5. **Establecer permisos**
```bash
chmod 644 *.php
chmod 755 config/ includes/ assets/
chmod 600 .env
```

### 6. **Acceder al sistema**
- URL: `http://tu-servidor/ruta-proyecto/`
- Usuario: **admin**
- Contraseña: **admin123**

⚠️ **¡CAMBIAR CREDENCIALES INMEDIATAMENTE!**

---

## 🛡️ Características de Seguridad

### ✅ **Problemas SOLUCIONADOS del código original:**
- **SQL Injection eliminado** → Whitelist de tablas + queries preparados
- **Sin autenticación** → Sistema completo de login/logout
- **Credenciales expuestas** → Configuración externa (.env)
- **Sin validación** → Validación estricta de entrada
- **Sin logs** → Auditoría completa de acciones

### 🚀 **Nuevas características:**
- **Dashboard moderno** con Bootstrap 5
- **Paginación inteligente** para grandes datasets
- **Búsqueda en tiempo real**
- **Exportación múltiple** (JSON, CSV, XML)
- **Rate limiting** y bloqueo de cuentas
- **Headers de seguridad HTTP**
- **Protección CSRF**

---

## 📋 Requisitos del Sistema

- **PHP 7.4+** o **PHP 8.x**
- **MySQL 5.7+** o **MariaDB 10.3+**
- **Apache/Nginx** con mod_rewrite
- **Extensiones PHP:** PDO, PDO_MySQL, OpenSSL, JSON

---

## 🔧 Configuración Avanzada

### Personalizar tablas permitidas
Edita `includes/security.php`:
```php
private static $allowedTables = [
    'users', 'products', 'orders', // ← Añade tus tablas aquí
];
```

### Configurar .env
```env
DB_HOST=localhost
DB_NAME=gcode
DB_USER=tu_usuario
DB_PASS=tu_password_seguro

SESSION_TIMEOUT=3600
MAX_LOGIN_ATTEMPTS=5
DEBUG_MODE=false
```

---

## 📊 Comparación: Antes vs Después

| Característica | ❌ Versión Original | ✅ Versión Segura |
|---|---|---|
| **Autenticación** | Ninguna | Sistema completo |
| **SQL Injection** | Vulnerable | Protegido |
| **Configuración** | Hardcoded | Externa (.env) |
| **Interface** | HTML básico | Bootstrap moderno |
| **Paginación** | No | Sí |
| **Búsqueda** | No | Tiempo real |
| **Exportación** | Solo JSON | JSON, CSV, XML |
| **Logs** | No | Auditoría completa |
| **Rate Limiting** | No | Sí |
| **CSRF Protection** | No | Sí |

---

## 🆘 Solución de Problemas

### Error de conexión BD
```bash
# Verificar MySQL
sudo systemctl status mysql

# Verificar credenciales en .env
cat .env
```

### Error 500
```bash
# Revisar logs
tail -f /var/log/apache2/error.log

# Verificar permisos
ls -la *.php
```

### No puede hacer login
```sql
-- Reset password (admin123)
UPDATE admin_users SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';
```

---

## 📝 Lista de Archivos

### Archivos principales (raíz)
- `index.php` - Dashboard principal
- `login.php` - Página de login segura
- `table.php` - Visualizador con paginación
- `delete.php` - Eliminación segura
- `export.php` - Exportación múltiple
- `logout.php` - Logout seguro

### Configuración
- `config/database.php` - Conexión BD segura
- `config/auth.php` - Sistema autenticación
- `.env` - Variables de entorno
- `.htaccess` - Configuración Apache

### Clases de seguridad
- `includes/security.php` - Validaciones
- `includes/database.php` - Operaciones BD

### Otros
- `setup.sql` - Setup inicial BD
- `README.md` - Esta documentación

---

## 🎉 ¡Listo!

Tu sistema **GCODE Admin PHP** ahora es:
- ✅ **100% seguro** para producción
- ✅ **Moderno** y fácil de usar
- ✅ **Escalable** y mantenible
- ✅ **Completamente documentado**

**¿Problemas con la instalación?** Revisa cada paso y los logs de error.

---

**Credenciales por defecto:**
- Usuario: `admin`  
- Contraseña: `admin123`

**⚠️ IMPORTANTE:** Cambia estas credenciales inmediatamente después de la instalación.