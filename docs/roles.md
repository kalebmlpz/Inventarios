# Actualizar roles en XAMPP

1. Haz una copia de seguridad de la base `punto_de_venta` desde Exportar en phpMyAdmin.
2. Conserva una copia de tu carpeta actual fuera de `htdocs`. Sustituye `htdocs/inventarios` por la carpeta `App` de esta versión y renómbrala `inventarios`. No mezcles carpetas: esta versión sustituye páginas `.html` por `.php`. Si personalizaste la configuración de conexión, conserva esos valores en `backend/config.php`.
3. En phpMyAdmin selecciona `punto_de_venta` e importa **una sola vez** `App/migrations/002_roles.sql`. No importes el volcado completo sobre una base existente: elimina y recrea tablas.
4. Cierra sesión y entra con tu cuenta Admin actual. Esta migración conserva su contraseña.
5. Desde **Administrar usuarios**, crea una cuenta con rol Cajero y contraseña de al menos ocho caracteres.
6. Prueba que el cajero puede consultar productos, registrar ventas y ver Mis operaciones, pero no dar altas/bajas/cambios ni crear usuarios. Las URL antiguas `.html` se sustituyen por `.php`.

El administrador ve todos los registros; el cajero solo los asociados a su usuario. Los registros anteriores conservan sus datos y son visibles para el administrador, pues no se conoce su autor. El servidor consulta el rol actual en cada petición protegida.

Para una instalación nueva importa `App/punto_de_venta.sql` (compatible con MySQL y MariaDB). Incluye Admin / Admin123 como cuenta inicial; crea las demás cuentas desde el sistema. La migración 002 no se ejecuta en instalaciones nuevas.

Este paso implementa permisos y atribución de operaciones. El descuento automático de existencias y el cambio de roles de usuarios existentes quedan para los siguientes pasos.
