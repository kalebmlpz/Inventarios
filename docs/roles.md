# Actualizar roles en XAMPP

1. Haz una copia de seguridad de la base `punto_de_venta` desde Exportar en phpMyAdmin.
2. Conserva una copia de tu carpeta actual fuera de `htdocs`. Sustituye `htdocs/inventarios` por la carpeta `App` de esta versión y renómbrala `inventarios`. No mezcles carpetas: esta versión sustituye páginas `.html` por `.php`. Si personalizaste la configuración de conexión, conserva esos valores en `backend/config.php`.
3. En phpMyAdmin selecciona `punto_de_venta` e importa **una sola vez** `App/migrations/002_roles.sql`. No importes el volcado completo sobre una base existente: elimina y recrea tablas.
4. Cierra sesión y entra con tu cuenta Admin actual. Esta migración conserva su contraseña.
5. Desde **Administrar usuarios**, crea una cuenta con rol Cajero y contraseña de al menos ocho caracteres.
6. Prueba que el cajero puede consultar productos, registrar ventas y ver Mis operaciones, pero no dar altas/bajas/cambios ni crear usuarios. Las URL antiguas `.html` se sustituyen por `.php`.

El administrador ve todos los registros; el cajero solo los asociados a su usuario. Los registros anteriores conservan sus datos y son visibles para el administrador, pues no se conoce su autor. El servidor consulta el rol actual en cada petición protegida.

Para una instalación nueva importa `App/punto_de_venta.sql` (compatible con MySQL y MariaDB). Incluye Admin / Admin123 como cuenta inicial; crea las demás cuentas desde el sistema. La migración 002 no se ejecuta en instalaciones nuevas.

La etapa de roles permite crear usuarios y asignar sus permisos. La edición de roles de usuarios existentes sigue pendiente.

## Catálogo de florería (primera etapa)

Con respaldo previo, importa una sola vez `App/migrations/003_catalogo_floreria.sql` después de la migración 002 para conservar los datos actuales. No vuelvas a importar el volcado completo sobre la base existente. Las instalaciones nuevas ya incluyen estos campos en el volcado.

Productos y existencias permite crear, editar y buscar flores, arreglos, bases y accesorios; el Cajero solo consulta. Se conservan los códigos numéricos y las existencias enteras para mantener compatibilidad con Ventas. Los productos anteriores quedan sin clasificar y conservan su stock. El precio se expresa por la unidad seleccionada; no se hacen conversiones entre paquetes y tallos.

La modalidad identifica arreglos preparados, por encargo o ambas formas. Antes de actualizar a la etapa de componentes aplica la migración 004 descrita a continuación.

## Componentes y existencias

1. Exporta un respaldo de `punto_de_venta` en phpMyAdmin.
2. Actualiza los archivos con Git y aplica **una sola vez** `App/migrations/004_componentes_existencias.sql` sobre la base existente, después de la 003. No importes el volcado completo sobre datos existentes. La migración conserva productos y registros y convierte las tablas de stock/historial a InnoDB para operaciones completas y simultáneas.
3. Cierra sesión y vuelve a entrar. Para una instalación nueva, el volcado completo ya incluye todos los campos y tablas; no apliques migraciones adicionales.
4. En el catálogo crea materiales individuales (flores, bases o accesorios) con existencias y su unidad. Al editar cantidades se registra un ajuste y su responsable.
5. Crea un producto Arreglos con cero existencias y elige Preparado, Por encargo o Ambas. Las unidades de arreglos se aumentan mediante elaboración, no desde la edición del stock.
6. Usa **Componentes / Preparar** para elegir materiales y cantidades **por una unidad de arreglo**. No se permiten otros arreglos como materiales, cantidades cero ni materiales repetidos. Guardar componentes no descuenta stock. Modificar la lista cambia futuras elaboraciones; los movimientos anteriores conservan su detalle.
7. **Preparar arreglos** (solo Administrador) consume materiales y suma unidades listas. Una venta Disponible de ese arreglo descuenta solo la unidad terminada.
8. En Ventas, busca el código, elige la modalidad y agrega al pedido. Una venta Por encargo descuenta componentes al confirmar la elaboración/venta, sin añadir ni descontar unidades terminadas. No gestiona reservas, pedidos pendientes ni fechas de entrega.
9. Si falta un componente, la operación de esa línea no modifica ninguna existencia ni crea registros parciales. Cada línea del pedido se confirma por separado; si una falla, se conservan las pendientes. Un reintento de la misma línea no la cobra ni descuenta nuevamente. El pedido pendiente se conserva en esa pestaña del navegador y por usuario.
10. En Registros consulta por fecha. Ver movimientos muestra cantidades consumidas o añadidas y el stock resultante; también se muestra tipo y responsable. El Cajero ve solo sus operaciones. Las ventas antiguas no se descuentan retroactivamente ni se les inventan componentes.

Un material usado en una receta no se puede eliminar sin retirarlo antes de sus componentes. El historial nuevo conserva el nombre y código usados en el momento de la operación, incluso si luego se edita o elimina el producto. El stock se consulta al abrir/buscar cada pantalla y se vuelve a verificar al guardar; no hay actualización automática entre pantallas abiertas.

Validación en el entorno de desarrollo: `python3 tests/roles.py` y `python3 tests/inventario.py`. Usan productos temporales y los eliminan al terminar. La prueba de inventario incluye elaboración, ventas de ambas modalidades, stock insuficiente, recuperación de un reenvío y dos ventas concurrentes de la última unidad.
