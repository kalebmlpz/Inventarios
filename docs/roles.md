# Actualizar roles en XAMPP

1. Haz una copia de seguridad de la base `punto_de_venta` desde Exportar en phpMyAdmin.
2. Conserva una copia de tu carpeta actual fuera de `htdocs`. Sustituye `htdocs/inventarios` por la carpeta `App` de esta versión y renómbrala `inventarios`. No mezcles carpetas: esta versión sustituye páginas `.html` por `.php`. Si personalizaste la configuración de conexión, conserva esos valores en `backend/config.php`.
3. En phpMyAdmin selecciona `punto_de_venta` e importa **una sola vez** `App/migrations/002_roles.sql`. No importes el volcado completo sobre una base existente: elimina y recrea tablas.
4. Cierra sesión y entra con tu cuenta Admin actual. Esta migración conserva su contraseña.
5. Desde **Administrar usuarios**, crea una cuenta con rol Cajero y contraseña de al menos ocho caracteres.
6. Prueba que el cajero puede consultar productos, registrar ventas y ver Mis operaciones, pero no dar altas/bajas/cambios ni crear usuarios. Las URL antiguas `.html` se sustituyen por `.php`.

El administrador ve todos los registros; el cajero solo los asociados a su usuario. Los registros anteriores conservan sus datos y son visibles para el administrador, pues no se conoce su autor. El servidor consulta el rol actual en cada petición protegida.

Para una instalación nueva importa `App/punto_de_venta.sql` (compatible con MySQL y MariaDB). Incluye Admin / Admin123 como cuenta inicial; crea las demás cuentas desde el sistema. La migración 002 no se ejecuta en instalaciones nuevas.

La etapa de roles permite crear usuarios y asignar sus permisos. La etapa de administración de cuentas, descrita al final, agrega edición, activación y desactivación.

## Catálogo de florería (primera etapa)

Con respaldo previo, importa una sola vez `App/migrations/003_catalogo_floreria.sql` después de la migración 002 para conservar los datos actuales. No vuelvas a importar el volcado completo sobre la base existente. Las instalaciones nuevas ya incluyen estos campos en el volcado.

Productos y existencias permite crear, editar y buscar flores, arreglos, bases y accesorios; el Cajero solo consulta. Se conservan los códigos numéricos y las existencias enteras para mantener compatibilidad con Ventas. Los productos anteriores quedan sin clasificar y conservan su stock. El precio se expresa por la unidad seleccionada; no se hacen conversiones entre paquetes y tallos.

La modalidad identifica arreglos preparados, por encargo o ambas formas. Antes de actualizar a la etapa de componentes aplica la migración 004 descrita a continuación.

## Componentes y existencias

1. Exporta un respaldo de `punto_de_venta` en phpMyAdmin.
2. Actualiza los archivos con Git y aplica **una sola vez** `App/migrations/004_componentes_existencias.sql` sobre la base existente, después de la 003. No importes el volcado completo sobre datos existentes. La migración conserva productos y registros y convierte las tablas de stock/historial a InnoDB para operaciones completas y simultáneas.
3. Cierra sesión y vuelve a entrar. Para una instalación nueva, el volcado completo ya incluye todos los campos y tablas; no apliques migraciones adicionales.
4. En el catálogo crea materiales individuales (flores, bases o accesorios) con existencias y su unidad. La cantidad inicial se registra al crear el material. Los cambios posteriores se registran desde Entrada o Merma; la edición de la ficha no permite sobrescribir el stock.
5. Crea un producto Arreglos con cero existencias y elige Preparado, Por encargo o Ambas. Las unidades de arreglos se aumentan mediante elaboración, no desde la edición del stock.
6. Usa **Componentes / Preparar** para elegir materiales y cantidades **por una unidad de arreglo**. No se permiten otros arreglos como materiales, cantidades cero ni materiales repetidos. Guardar componentes no descuenta stock. Modificar la lista cambia futuras elaboraciones; los movimientos anteriores conservan su detalle.
7. **Preparar arreglos** (solo Administrador) consume materiales y suma unidades listas. Una venta Disponible de ese arreglo descuenta solo la unidad terminada.
8. En Ventas, busca el código, elige la modalidad y agrega al pedido. Una venta Por encargo descuenta componentes al confirmar la elaboración/venta, sin añadir ni descontar unidades terminadas. No gestiona reservas, pedidos pendientes ni fechas de entrega.
9. Si falta un componente, la operación de esa línea no modifica ninguna existencia ni crea registros parciales. Cada línea del pedido se confirma por separado; si una falla, se conservan las pendientes. Un reintento de la misma línea no la cobra ni descuenta nuevamente. El pedido pendiente se conserva en esa pestaña del navegador y por usuario.
10. En Registros consulta por fecha. Ver movimientos muestra cantidades consumidas o añadidas y el stock resultante; también se muestra tipo y responsable. El Cajero ve solo sus operaciones. Las ventas antiguas no se descuentan retroactivamente ni se les inventan componentes.

Un material usado en una receta no se puede eliminar sin retirarlo antes de sus componentes. El historial nuevo conserva el nombre y código usados en el momento de la operación, incluso si luego se edita o elimina el producto. El stock se consulta al abrir/buscar cada pantalla y se vuelve a verificar al guardar; no hay actualización automática entre pantallas abiertas.

Validación en el entorno de desarrollo: `python3 tests/roles.py` y `python3 tests/inventario.py`. Usan productos temporales y los eliminan al terminar. La prueba de inventario incluye elaboración, ventas de ambas modalidades, stock insuficiente, recuperación de un reenvío y dos ventas concurrentes de la última unidad.

## Entradas y mermas

Esta etapa requiere la migración **005_entradas_mermas.sql** después de la 004. Exporta un respaldo de `punto_de_venta`, actualiza los archivos e importa la migración **una sola vez** en phpMyAdmin. No importes el volcado completo sobre una base existente. Para instalaciones nuevas, `App/punto_de_venta.sql` ya incluye las tablas y columnas necesarias; no apliques además las migraciones.

En **Productos y existencias**, el Administrador dispone de **Nuevo producto**, **Registrar entrada** y **Registrar merma**. El Cajero no ve ni puede acceder a las dos últimas acciones, incluso escribiendo sus direcciones directamente.

- **Entrada:** selecciona un material existente (flores, bases o accesorios), cantidad entera positiva y motivo Compra, Recepción de mercancía u Otro. Aumenta el stock. Los arreglos se suman desde Componentes / Preparar, para consumir correctamente sus materiales.
- **Merma:** selecciona el producto (también puede ser un arreglo listo), cantidad y motivo Marchitez, Daño, Desperdicio u Otro. No puede superar las existencias. Perder un arreglo terminado no devuelve sus componentes.
- **Detalle:** permite agregar una explicación; es obligatorio si el motivo es Otro.
- Cada operación se fecha al guardarse (hora de Ciudad de México), guarda el Administrador responsable y aparece en Registros con su motivo y el detalle del cambio de stock.
- Un reenvío de la misma solicitud no registra el movimiento dos veces. Si falta stock o hay un error, no queda un movimiento parcial.
- La edición de productos permite modificar sus datos, precio y stock mínimo. Las existencias de productos existentes son de solo lectura; usa las acciones de movimiento. Si la cantidad cambió desde que abriste la edición, recarga antes de guardar.

Para probar: crea un material con 10 unidades, registra una entrada de 50 y una merma de 5. Deben quedar 55 y dos movimientos nuevos con sus motivos. Prueba una merma superior a 55: debe rechazarse sin alterar las existencias. `python3 tests/entradas_mermas.py` verifica además permisos, reenvíos, límites y operaciones simultáneas con datos temporales.

## Filtros de existencias

El catálogo permite combinar búsqueda por nombre/código, categoría y existencias. Stock bajo incluye cantidades iguales o menores al stock mínimo, incluidos los agotados. Agotados muestra stock cero. Los arreglos solo por encargo quedan fuera de ambos filtros porque no mantienen unidades terminadas; pueden consultarse con Todas. Un arreglo Preparado o Ambas sin unidades se identifica como Sin arreglos listos.

Los filtros son de consulta para Administrador y Cajero; no cambian las cantidades ni requieren una migración SQL. Limpiar filtros restablece el catálogo completo. Prueba de límites y permisos: `python3 tests/catalogo_filtros.py`.

## Cancelaciones y devoluciones

Con un respaldo previo, aplica **una sola vez** `App/migrations/006_cancelaciones_devoluciones.sql` después de la 005. Conserva todas las ventas y movimientos anteriores. Para instalaciones nuevas, el volcado completo ya incluye estas columnas; no se aplican migraciones adicionales.

El Administrador puede entrar a **Registros → Cancelar / Devolver** en cada venta con unidades pendientes de corregir. El Cajero ve las correcciones acumuladas en sus ventas, pero no puede registrarlas. Cada venta corresponde a una línea del pedido; se corrige la cantidad elegida de esa línea, no todas las líneas automáticamente.

- Elige **Cancelación** o **Devolución**, cantidad y motivo. Se permiten cantidades completas o parciales, hasta las unidades que quedan sin corregir. Se requiere confirmar la revisión de la mercancía.
- **Sin reintegro:** conserva el stock actual. Corresponde cuando no se recibe mercancía utilizable. No se descuenta otra merma: esas unidades ya salieron en la venta original.
- **Reintegrar producto recibido en buen estado:** suma el producto terminado o material individual recibido. Un arreglo por encargo se recibe como arreglo terminado, nunca como flores sueltas; debe estar configurado como Preparado o Ambas para mantener unidades listas.
- **Corregir captura por error:** solo se permite con Cancelación y cuando los productos o materiales realmente no se entregaron ni utilizaron. Revierte la proporción correspondiente del consumo original registrado en la venta. Una receta modificada después no altera esa reconstrucción.
- La venta original conserva su cantidad y muestra cuántas unidades se corrigieron. Se crea otro registro, con la fecha actual, cantidad, motivo, Administrador y vínculo a la venta original. No se borra el historial ni se realizan reembolsos monetarios desde esta función.
- Un reenvío de la misma solicitud no duplica la corrección. Dos solicitudes simultáneas no pueden superar las unidades vendidas. Los reintegros son completos por operación: ante un error no se recupera solo una parte de los componentes.
- Las ventas anteriores a esta migración no tienen identidad/unidad verificable de cada producto. Pueden corregirse Sin reintegro; no se inventa una correspondencia a partir de un código que pudo haberse reutilizado. Comprueba físicamente los materiales antes de registrar cualquier entrada por separado.
- Si el producto fue eliminado o cambió de unidad o categoría, se bloquea el reintegro automático. El historial nuevo mantiene las identidades y unidades originales aun si se edita el código o el nombre.

Las correcciones confirmadas son registros de auditoría y no se editan ni se borran desde la interfaz. Revisa el tratamiento elegido antes de confirmar.

Prueba funcional: `python3 tests/correcciones_ventas.py`, que usa productos temporales y verifica correcciones completas/parciales, reintegros, recetas modificadas, unidades cambiadas, productos eliminados, duplicados, permisos y concurrencia.

## Reportes del Administrador

Acceso desde **Menú → Reportes**, disponible exclusivamente para el Administrador. Esta etapa no agrega columnas ni requiere otra migración si la base ya tiene aplicada la 006.

Elige fechas Desde/Hasta y uno de estos reportes:

- **Ventas netas:** agrupa las ventas cuya fecha está en el periodo. Muestra cantidades vendidas, corregidas y netas; descuenta las correcciones acumuladas hasta el momento de consulta, incluso si ocurrieron fuera del periodo de la venta. Una venta corregida no se cuenta como una venta nueva.
- **Consumo de materiales:** incluye salidas de materiales al preparar arreglos y venderlos por encargo, así como reintegros de capturas corregidas durante las fechas elegidas. No vuelve a contar materiales al vender un arreglo ya preparado. Los consumos y correcciones se atribuyen a sus respectivas fechas; el neto puede ser negativo si se revierte un consumo de un periodo anterior.
- **Mermas:** unidades perdidas y número de operaciones por producto/unidad en el periodo.
- **Movimientos:** detalle por folio, fecha, tipo, producto, cantidad, unidad, responsable, motivo y referencia a la venta original, cuando exista.

Las cantidades se mantienen por producto y unidad. Un cambio de unidad en la ficha no reescribe los datos históricos ni mezcla sus cantidades. Las unidades o nombres que no se guardaron en registros antiguos se muestran como desconocidos; no se inventan a partir de la ficha actual.

**Descargar CSV** exporta la misma vista y periodo seleccionados, con UTF-8 y separador punto y coma. Puede abrirse/importarse en Excel; si aparece todo en una columna, importa el archivo usando `;` como separador. Los campos de texto se protegen para que Excel no los ejecute como fórmulas.

Estos reportes muestran cantidades, no importes monetarios ni ganancias: las ventas actuales todavía no guardan precios históricos. No debe multiplicarse una venta antigua por el precio actual del catálogo.

Validación: `python3 tests/reportes.py` comprueba fechas, ventas corregidas, unidades, consumo, mermas, permisos y CSV. Las pruebas usan datos temporales y los limpian al terminar.

## Administración de cuentas

1. Exporta un respaldo de la base `punto_de_venta`.
2. Actualiza los archivos y aplica **una sola vez** `App/migrations/007_administracion_cuentas.sql` después de la 006. Conserva cuentas, roles y contraseñas y mantiene activas las cuentas existentes. Agrega control de estado y sesiones, y un índice para impedir nombres duplicados. Si había nombres duplicados creados manualmente, resuélvelos antes de aplicar el índice; no elimines cuentas con historial.
3. Cierra sesión y vuelve a iniciar: las sesiones anteriores a esta actualización ya no son válidas. Las instalaciones nuevas incluyen las columnas en el volcado completo y no requieren migraciones adicionales.

Desde **Administrar usuarios**, el Administrador ve la lista, busca por nombre y crea o edita cuentas. Los Cajeros no tienen acceso, incluso por dirección directa.

- **Editar:** cambia el nombre y el rol. Abre Cambiar contraseña solo si necesitas restablecerla: escribe la nueva y repítela. Si dejas ambos campos vacíos, se conserva la actual. Las contraseñas nuevas se guardan con hash; no se muestran las actuales.
- **Desactivar:** bloquea nuevos inicios de sesión e invalida las sesiones existentes en su próxima solicitud. Conserva ventas, movimientos y el identificador del usuario. No se ofrece borrar cuentas.
- **Activar:** permite volver a iniciar sesión. No revive sesiones de antes de la desactivación.
- Guardar cambios de una cuenta invalida sus sesiones anteriores. Si editas tu propia cuenta, vuelves al login con un mensaje y debes usar los datos nuevos.
- No puedes desactivar tu propia cuenta ni quitar el último Administrador activo. La comprobación se realiza también ante cambios simultáneos; no basta con ocultar botones.
- Si otra persona modificó la cuenta desde que la abriste, recarga antes de volver a guardar para no sobrescribir sus cambios.

Las credenciales iniciales existentes siguen funcionando. El login conserva compatibilidad con las contraseñas antiguas y actualiza su almacenamiento cuando corresponde; un hash guardado no sirve como contraseña para iniciar sesión.

Validación: `python3 tests/cuentas.py` prueba edición, contraseñas, sesiones, activación, permisos y solicitudes simultáneas. Las pruebas del último Administrador se ejecutan en una base temporal aislada, sin cambiar el rol ni el estado de la cuenta real Admin.
