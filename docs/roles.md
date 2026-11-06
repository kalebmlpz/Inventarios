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

## Catálogo de florería (primera etapa)

Con respaldo previo, importa una sola vez `App/migrations/003_catalogo_floreria.sql` después de la migración 002 para conservar los datos actuales. No vuelvas a importar el volcado completo sobre la base existente. Las instalaciones nuevas ya incluyen estos campos en el volcado.

Productos y existencias permite crear, editar y buscar flores, arreglos, bases y accesorios; el Cajero solo consulta. Se conservan los códigos numéricos y las existencias enteras para mantener compatibilidad con Ventas. Los productos anteriores quedan sin clasificar y conservan su stock. El precio se expresa por la unidad seleccionada; no se hacen conversiones entre paquetes y tallos.

La modalidad identifica arreglos preparados, por encargo o ambas formas. Esta primera etapa **no** registra recetas/componentes, elaboración, reservas ni consumo automático de materiales. Tampoco modifica todavía el descuento de existencias de Ventas. Un arreglo solo por encargo debe tener cero existencias de unidades terminadas. No uses la edición del stock para registrar elaboración de arreglos; ese flujo se añadirá en la etapa de movimientos con su historial.
