# Auditoría de Rendimiento - Plugin vc-flashcards

Fecha del análisis: 2026-07-20 (corregido 2026-07-21)
Alcance: exclusivamente rendimiento (TTFB, LCP, CLS, INP, carga de red, CPU/memoria en servidor y en el navegador). No se evalúa seguridad ni arquitectura salvo cuando impacta directamente en velocidad.

> **Nota de arquitectura (agregada 2026-07-21):** Flashcards, Study Session y Mock Test son módulos intencionalmente independientes en lógica, componentes y estilos. La duplicación visual/estructural entre ellos (CSS, patrones de UI) es una decisión de arquitectura, no deuda técnica, y no debe unificarse. Esta nota no existía al momento del análisis original y corrige la recomendación de H-17 (ver sección correspondiente).

---

## 1. Resumen ejecutivo

El plugin `vc-flashcards` está, en general, bien construido desde el punto de vista de rendimiento en su núcleo de datos: usa `no_found_rows`, `update_meta_cache()` / `update_object_term_cache()` antes de recorrer tarjetas en bucle, transients con versión lógica para invalidar cachés de pools de exámenes y sesiones, y consultas SQL preparadas con `JOIN`/subconsultas en vez de N+1 en los puntos más calientes (historial de examen, "mastered cards", ACS codes). El equipo que lo construyó claramente pensó en el problema de N+1 en varios lugares.

Sin embargo, el análisis completo del archivo `includes/class-vc-flashcards-plugin.php` (3967 líneas), de las plantillas, de los tres módulos de JavaScript (`flashcards.js`, `flashcards-library.js`, `exam.js`) y de las tres hojas de estilo principales (`flashcards.css` 4264 líneas / 122 KB, `flashcards-library.css`, `exam.css`) revela un patrón recurrente: **las funciones de agregación más pesadas del plugin (`get_study_sessions_categories()`, `get_flashcards_library_categories()`) no tienen ninguna capa de caché**, a pesar de que el propio plugin ya implementa infraestructura de caché por versión (`EXAM_POOL_CACHE_VERSION_OPTION`, `SESSION_POOL_CACHE_VERSION_OPTION`) para otras rutas. Estas dos funciones recorren **todas** las flashcards publicadas del sitio en cada render del shortcode principal (`[vc_flashcards_app]` / `[vc_flashcards_library]`), calculando conteos, progreso y metadatos ACS en PHP puro en cada carga de página, para cada usuario, en cada visita.

También se confirmó un recurso roto: `assets/general.css` se encola en tres lugares distintos (`render_flashcards_shortcode`, `render_flashcards_library_shortcode`, `render_exam_shortcode`) pero el archivo no existe en `assets/`. WordPress no valida la existencia del archivo antes de imprimir la etiqueta `<link>`, así que cada carga de estas tres páginas genera una petición HTTP adicional que siempre resuelve en 404.

En el frontend, el widget de ayuda flotante (`help-fab`) se carga con `wp_enqueue_scripts` y `wp_footer` sin comprobar si la página contiene alguno de los shortcodes del plugin, es decir, **se descarga CSS/JS del plugin en absolutamente todas las páginas del sitio**, tengan o no relación con flashcards. El impacto por request es pequeño (unos 8 KB de CSS + 8 KB de JS sin comprimir) pero es trabajo innecesario multiplicado por cada página vista del sitio completo.

En el pipeline de despliegue (`.github/workflows/deploy.yml`) se confirmó que no existe ningún paso de build, minificación o concatenación: el FTP sube los archivos de `assets/` tal cual están en el repositorio. Los tres archivos JS suman ~132 KB sin comprimir y las tres hojas CSS principales suman ~194 KB sin comprimir; sin minificación se pierde de forma conservadora entre 25-40% de ese peso de transferencia (el gzip/brotli del servidor compensa buena parte, pero la minificación reduce trabajo de parseo en el navegador, que gzip no ataca).

Ninguno de los hallazgos es una emergencia de escalabilidad (no hay bucles de miles de iteraciones ni tablas sin índices), pero sí hay una acumulación de "trabajo repetido evitable" que se nota especialmente en el TTFB de las tres pantallas principales del plugin y en el peso de red de la primera carga.

---

## 2. Hallazgos detallados

### 2.1 `includes/class-vc-flashcards-plugin.php`

#### H-01. `get_study_sessions_categories()` sin ninguna capa de caché
- **Archivo y ruta:** `includes/class-vc-flashcards-plugin.php`
- **Clase/función afectada:** `VC_Flashcards_Plugin::get_study_sessions_categories()` (invocada desde `render_flashcards_shortcode()`)
- **Descripción técnica:** Esta función ejecuta, en cada render del shortcode `[vc_flashcards_app]`/`[vc_flashcards]`: un `get_terms()` completo de la taxonomía, un `get_posts()` con `posts_per_page => -1` de **todas** las flashcards publicadas, y luego un bucle PHP que llama `get_the_terms()` y `get_post_meta($card_id, '_vc_flashcard_acs_code', true)` por cada tarjeta para construir conteos de progreso, subtemas y métricas ACS. Aunque el código sí llama a `update_meta_cache()` y `update_object_term_cache()` antes del bucle (evitando N+1 real de SQL), sigue siendo O(n) de trabajo en PHP puro —recorrido, `usort()` por tarjeta, `sprintf()`, `html_entity_decode()`— ejecutado de cero en cada carga de página, para cada usuario. A diferencia de `get_exam_id_pool_for_topic()` (que sí cachea con `set_transient()` + versión lógica), esta función no tiene ningún `get_transient()`/`set_transient()`.
- **Nivel de impacto:** Alto
- **Impacto estimado en el rendimiento:** Con un catálogo de cientos de flashcards, este bloque puede representar la mayor parte del tiempo de generación de HTML (TTFB) del shortcode principal de Study Sessions. Es trabajo idéntico para todos los usuarios (los conteos de "totalCards" no dependen del usuario, sólo "masteredCards" depende del usuario) que se recalcula en cada visita en vez de reutilizarse.
- **Solución recomendada:** Separar el cálculo en dos capas: (1) una parte agnóstica de usuario (conteos totales por categoría/subtema/ACS) cacheada con `set_transient()` e invalidada con el mismo patrón de versión lógica que ya usa `clear_flashcard_pool_caches()`; (2) una parte por usuario (mastered cards) que sólo cruce IDs contra el resultado cacheado, igual que ya hace `get_mastered_flashcard_ids()` con su caché estática en memoria.
- **Ejemplo de código optimizado:**
```php
private function get_study_sessions_categories(int $user_id): array {
    $cache_key = 'vc_flashcards_categories_base_' . $this->get_session_pool_cache_version();
    $base = get_transient($cache_key);

    if (!is_array($base)) {
        $base = $this->build_study_sessions_categories_base(); // todo lo que no depende de $user_id
        set_transient($cache_key, $base, self::SESSION_POOL_CACHE_TTL);
    }

    return $this->apply_user_mastery_to_categories($base, $user_id); // barato: solo cruces de arrays
}
```

#### H-02. `get_flashcards_library_categories()` sin caché (mismo patrón que H-01)
- **Archivo y ruta:** `includes/class-vc-flashcards-plugin.php`
- **Clase/función afectada:** `VC_Flashcards_Plugin::get_flashcards_library_categories()` (invocada desde `render_flashcards_library_shortcode()`)
- **Descripción técnica:** Idéntico patrón que H-01: `get_terms()` + `get_posts(-1)` + bucle con `get_the_terms()` por tarjeta, sin transient. Se ejecuta en cada carga de `[vc_flashcards_library]`.
- **Nivel de impacto:** Alto
- **Impacto estimado en el rendimiento:** Igual naturaleza que H-01; ambos shortcodes duplican esencialmente el mismo trabajo de agregación sin compartir resultado entre sí ni entre requests.
- **Solución recomendada:** Igual que H-01. Idealmente ambas funciones (`get_study_sessions_categories` y `get_flashcards_library_categories`) deberían compartir una única función base cacheada de "conteos por categoría/subtema", ya que ahora mismo recorren el mismo catálogo de tarjetas dos veces con lógica muy similar si un usuario visita ambas pantallas.

#### H-03. Recurso `assets/general.css` inexistente, enqueued en 3 lugares
- **Archivo y ruta:** `includes/class-vc-flashcards-plugin.php`, funciones `render_flashcards_shortcode()` (línea ~1276), `render_flashcards_library_shortcode()` (línea ~1354) y `render_exam_shortcode()` (línea ~3393)
- **Descripción técnica:** Las tres funciones llaman `wp_enqueue_style('vc-general-style', VC_FLASHCARDS_URL . 'assets/general.css', ...)`. Se confirmó con `ls assets/*.css` que el archivo **no existe** en el directorio `assets/`. `wp_enqueue_style()` no valida la existencia del archivo: WordPress igual imprime `<link rel="stylesheet" href=".../assets/general.css?ver=1.0.0">` en el `<head>`, generando una petición HTTP que el servidor resuelve como 404 en cada carga de las tres pantallas principales del plugin.
- **Nivel de impacto:** Medio
- **Impacto estimado en el rendimiento:** Una petición 404 no bloquea el render (es un `<link>` no crítico), pero sí consume una conexión/socket, agrega una entrada más al *waterfall* de red, y en HTTP/1.1 con límite de conexiones concurrentes por host puede retrasar levemente la descarga de otros recursos reales. Es 100% desperdicio: cero beneficio, sólo costo de red.
- **Solución recomendada:** Eliminar las tres llamadas a `wp_enqueue_style('vc-general-style', ...)` si el archivo nunca existió y su contenido ya fue absorbido por otras hojas, o crear el archivo `assets/general.css` si realmente falta contenido compartido que otros selectores esperan.
- **Ejemplo de código optimizado:**
```php
// Antes: se encola sin verificar si aporta algo real.
wp_enqueue_style('vc-general-style', VC_FLASHCARDS_URL . 'assets/general.css', [], ...);

// Después: eliminar la línea, o (si el archivo debe existir) crearlo antes de encolarlo.
if (file_exists(VC_FLASHCARDS_DIR . 'assets/general.css')) {
    wp_enqueue_style('vc-general-style', VC_FLASHCARDS_URL . 'assets/general.css', [], filemtime(...));
}
```

#### H-04. Widget de ayuda (`help-fab`) se carga en TODAS las páginas del frontend, no solo donde hay shortcodes
- **Archivo y ruta:** `includes/class-vc-flashcards-plugin.php`, constructor (líneas ~139-140) y funciones `enqueue_global_help_assets()` / `render_global_help_fab()`
- **Descripción técnica:** El constructor registra `add_action('wp_enqueue_scripts', [$this, 'enqueue_global_help_assets'])` y `add_action('wp_footer', [$this, 'render_global_help_fab'])` sin ninguna condición sobre si la página actual usa alguno de los shortcodes del plugin. La única comprobación es `is_admin()`. Esto significa que `help-fab.css` (9.5 KB) y `help-fab.js` (8.2 KB) se descargan y ejecutan en cada página pública del sitio (home, posts, páginas institucionales, etc.), no solo en el dashboard de flashcards.
- **Nivel de impacto:** Medio
- **Impacto estimado en el rendimiento:** El script se encola con `true` como último argumento de `wp_enqueue_script` (carga en el footer), por lo que **no es render-blocking** en el sentido clásico; no bloquea el parseo del `<head>`. Aun así, es peso y JS de más ejecutándose (`DOMContentLoaded` listener, `querySelector`, listeners de teclado/click a nivel `document`) en páginas que nunca usarán el widget, con impacto acumulado en INP y en el uso de red de todo el sitio, no solo del plugin.
- **Solución recomendada:** Decidir de forma explícita en qué páginas debe aparecer el FAB de feedback (por ejemplo, solo en las páginas del dashboard/plantilla de flashcards) y condicionar el `enqueue`/`render` a eso, en vez de a "cualquier página no-admin".
- **Ejemplo de código optimizado:**
```php
public function enqueue_global_help_assets(): void {
    if (is_admin() || !$this->should_show_help_fab()) {
        return;
    }
    // ... resto igual
}

private function should_show_help_fab(): bool {
    // Ejemplo: solo en páginas que ya cargan el dashboard de flashcards.
    return is_page('dashboard') || is_singular('page') && has_shortcode(get_post()->post_content ?? '', 'vc_flashcards_app');
}
```

#### H-05. `get_flashcard_ids_for_term()` sin `no_found_rows`, a diferencia de su gemela `query_flashcard_ids_for_term()`
- **Archivo y ruta:** `includes/class-vc-flashcards-plugin.php`, función `get_flashcard_ids_for_term()` (línea ~3336), usada por `get_exam_categories()`, `get_category_cards()` (código muerto, ver H-07) y `get_session_progress_updates()`
- **Descripción técnica:** El `WP_Query` interno no incluye `'no_found_rows' => true`, a diferencia de la función casi idéntica `query_flashcard_ids_for_term()` (línea ~3704) que sí la tiene. Sin `no_found_rows`, WordPress ejecuta una segunda consulta `SQL_CALC_FOUND_ROWS`/`COUNT(*)` innecesaria, ya que el resultado de conteo nunca se usa (solo se usan los IDs).
- **Nivel de impacto:** Bajo
- **Impacto estimado en el rendimiento:** Una consulta COUNT adicional por cada topic/subtopic consultado. En `render_exam_shortcode()` se llama una vez por cada uno de los 3 topics padre (General/Airframe/Powerplant) en `get_exam_categories()`, y en `ajax_complete_session()` se llama una vez por cada término tocado por la sesión recién completada. El costo por consulta es bajo pero es 100% evitable.
- **Solución recomendada:** Agregar `'no_found_rows' => true` (y considerar `'fields' => 'ids'` ya está presente) para igualarla a `query_flashcard_ids_for_term()`.
- **Ejemplo de código optimizado:**
```php
private function get_flashcard_ids_for_term(int $term_id): array {
    $query = new WP_Query([
        'post_type'      => self::POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true, // <-- faltaba
        'tax_query'      => [[ /* ... */ ]],
    ]);

    return array_map('intval', $query->posts);
}
```

#### H-06. `data-categories` serializa todo el árbol de categorías (incluyendo métricas ACS) dentro de un atributo HTML
- **Archivo y ruta:** `templates/shortcode-app.php` (línea ~25), alimentado por `get_study_sessions_categories()`
- **Descripción técnica:** `wp_json_encode($categories)` se inyecta completo como valor del atributo `data-categories` del contenedor raíz. El payload incluye, por cada categoría y subtema, conteos, progreso y el array `acsCodes` (que puede tener decenas de entradas por categoría, cada una con `topics` como array). Esto se parsea de nuevo en `flashcards.js` (`parseCategories()`) apenas carga la página.
- **Nivel de impacto:** Bajo
- **Impacto estimado en el rendimiento:** Aumenta el peso del HTML inicial (afecta directamente al tiempo de descarga/parseo del documento y por lo tanto indirectamente al LCP) proporcionalmente al catálogo de ACS codes. No es crítico con catálogos pequeños/medianos, pero escala mal si el número de ACS codes por categoría crece.
- **Solución recomendada:** Mover el payload a un `<script type="application/json">` en vez de un atributo (evita el doble-escapado HTML de `esc_attr()` sobre JSON, que es ligeramente más pesado de parsear) o, mejor aún, cargar `acsCodes` de forma perezosa vía AJAX solo cuando el usuario abre el modal "Study by ACS Code", en vez de incluirlo en el payload inicial de todas las categorías.

#### H-07. Código muerto: `get_category_cards()` / `get_flashcards_library_categories_legacy()`
- **Archivo y ruta:** `includes/class-vc-flashcards-plugin.php`, funciones `get_category_cards()` (línea ~2718) y `get_flashcards_library_categories_legacy()` (línea ~1788)
- **Descripción técnica:** Se verificó con búsqueda global en todo el plugin que ninguna de las dos funciones se invoca desde ningún otro punto del código (ni shortcodes, ni AJAX, ni templates). No tiene costo en tiempo de ejecución (PHP no ejecuta código muerto), pero es mantenimiento y superficie de confusión: alguien podría reintroducir una llamada a esta ruta no cacheada y sin las optimizaciones que sí tiene `get_study_sessions_categories()`.
- **Nivel de impacto:** Bajo (housekeeping, no rendimiento en producción)
- **Impacto estimado en el rendimiento:** Ninguno en runtime actual. Riesgo latente si se reactiva sin las mismas protecciones de caché.
- **Solución recomendada:** Eliminar ambas funciones si en efecto no se usan, o documentarlas como "reservadas para uso futuro" si el equipo planea reactivarlas.

#### H-08. CSV import (`import_csv_file` / `upsert_imported_flashcard` / `ensure_topic_and_subtopic`): trabajo por fila sin batching ni transacción
- **Archivo y ruta:** `includes/class-vc-flashcards-plugin.php`, función `import_csv_file()` (línea ~902) y las funciones que invoca por cada fila: `upsert_imported_flashcard()`, `ensure_topic_and_subtopic()`, `get_media_url_by_filename()`
- **Descripción técnica:** Por cada fila del CSV se ejecutan, de forma secuencial: hasta 1 consulta a `wp_posts` para resolver el adjunto por nombre de archivo (`get_media_url_by_filename`), `wp_insert_post`/`wp_update_post` (que dispara varios hooks internos de WordPress), 9 llamadas a `update_post_meta()`, 1-2 llamadas a `get_terms()` dentro de `get_term_by_name_and_parent()` (una por topic, otra por subtopic si aplica) y `wp_set_object_terms()`. Ninguna de estas operaciones está agrupada en una transacción de base de datos ni hay un cache "en memoria" de términos ya creados durante la misma ejecución del import (por ejemplo, si 500 filas usan el mismo topic "Airframe", se repite la búsqueda `get_term_by_name_and_parent('Airframe', 0)` 500 veces).
- **Nivel de impacto:** Medio
- **Impacto estimado en el rendimiento:** Este flujo corre una sola vez por import (no en cada request de usuario final), así que no afecta Core Web Vitals de los visitantes, pero sí determina cuánto tarda el admin en importar CSVs grandes (los archivos de prueba incluidos, `test-data-240.csv` y `test-data-340.csv`, ya representan 240-340 filas; con catálogos más grandes el tiempo de import crece linealmente y puede acercarse al límite de `max_execution_time` de PHP en hosting compartido).
- **Solución recomendada:** Cachear en un array local (por ejecución) los `term_id` ya resueltos por nombre+parent para no repetir `get_terms()` por cada fila que comparte topic/subtopic; envolver el import completo (o bloques de N filas) en `$wpdb->query('START TRANSACTION')` / `COMMIT` para reducir el overhead de autocommit por fila.
- **Ejemplo de código optimizado:**
```php
private array $import_term_cache = [];

private function upsert_term_by_name(string $name, int $parent = 0): int {
    $cache_key = strtolower($name) . '|' . $parent;
    if (isset($this->import_term_cache[$cache_key])) {
        return $this->import_term_cache[$cache_key];
    }

    $term_id = /* ... lógica actual ... */;
    $this->import_term_cache[$cache_key] = $term_id;
    return $term_id;
}
```

#### H-09. `get_exam_id_pool_for_topic()` y `get_flashcards_for_session()`: buen uso de caché, mencionado como referencia positiva
- **Archivo y ruta:** `includes/class-vc-flashcards-plugin.php`, funciones `get_exam_id_pool_for_topic()` (línea ~3669) y `get_flashcards_for_session()` (línea ~2335)
- **Descripción técnica:** A diferencia de H-01/H-02, estas dos funciones sí cachean su resultado con `set_transient()` + `EXAM_POOL_CACHE_TTL`/`SESSION_POOL_CACHE_TTL` (600 segundos) y una versión lógica (`get_exam_pool_cache_version()`/`get_session_pool_cache_version()`) que se incrementa en `clear_flashcard_pool_caches()` cuando cambia una flashcard o sus términos, evitando tener que buscar y borrar transients por prefijo (patrón costoso en MySQL sin objeto-caché persistente).
- **Nivel de impacto:** N/A (hallazgo positivo, no requiere corrección)
- **Impacto estimado en el rendimiento:** Este patrón es el que debería replicarse en H-01 y H-02.
- **Solución recomendada:** Ninguna; usar como modelo para corregir H-01/H-02.

---

### 2.2 `includes/class-vc-flashcards-feedback-admin.php`

#### H-10. `get_items()` limitado correctamente, sin paginación real
- **Archivo y ruta:** `includes/class-vc-flashcards-feedback-admin.php`, función `get_items()` (línea ~147)
- **Descripción técnica:** La consulta usa `LIMIT %d` con un límite fijo de 100 filas (`$limit = 100`), lo cual evita una tabla sin límite. Sin embargo, no existe paginación real (no hay `OFFSET`, ni parámetro de página en la URL del admin): si el widget de feedback recibe más de 100 mensajes, los más antiguos simplemente dejan de ser visibles en la lista sin ningún indicador ni forma de navegarlos.
- **Nivel de impacto:** Bajo
- **Impacto estimado en el rendimiento:** No hay problema de rendimiento de consulta (el LIMIT ya protege contra una tabla grande), es más una limitación funcional que eventualmente podría inducir a un admin a pedir "aumentar el límite" ad-hoc, lo que sí sería un riesgo de rendimiento futuro si alguien sube el `100` a un valor alto sin agregar paginación.
- **Solución recomendada:** Agregar paginación real (`paged` por GET + `OFFSET`) en vez de aumentar el límite fijo si el volumen de feedback crece.

#### H-11. `get_item()` con `SELECT *`
- **Archivo y ruta:** `includes/class-vc-flashcards-feedback-admin.php`, función `get_item()` (línea ~162)
- **Descripción técnica:** Usa `SELECT * FROM {$wpdb->prefix}vc_flashcard_feedback WHERE id = %d`, mientras que `get_items()` sí selecciona columnas explícitas. Al ser una consulta por `id` (clave primaria) que trae una sola fila, el costo real es mínimo, pero rompe la consistencia con el resto del archivo y trae columnas que la plantilla de detalle no necesariamente usa todas (aunque en este caso sí las usa casi todas).
- **Nivel de impacto:** Bajo
- **Impacto estimado en el rendimiento:** Insignificante en esta tabla (una fila, por PK). Se documenta por consistencia con el resto de la auditoría, no por urgencia.
- **Solución recomendada:** Cambiar a columnas explícitas por buena práctica, sin urgencia real.

---

### 2.3 Templates (`templates/admin/*.php`, `templates/exam/*.php`, `templates/partials/*.php`, `templates/shortcode-*.php`)

#### H-12. Nada bloqueante detectado en templates, pero confirman el patrón de H-06
- **Archivo y ruta:** `templates/shortcode-flashcards.php` no serializa un payload JSON tan grande como `shortcode-app.php` (los datos de `categories` se usan solo para pintar HTML server-side, no se re-serializan a JS), por lo que este template está en mejor forma que `shortcode-app.php` respecto a H-06.
- **Descripción técnica:** Se revisaron completos `templates/exam/*.php`, `templates/partials/*.php`, `templates/admin/*.php` y los tres `templates/shortcode-*.php`. No se encontraron bucles anidados costosos, ni `get_post_meta()`/`get_the_terms()` sueltos dentro de foreach de templates (toda esa lógica ya vive en el PHP de la clase principal, que es donde están los hallazgos reales). Los templates son en su mayoría marcado estático con interpolación de datos ya calculados.
- **Nivel de impacto:** Bajo / informativo
- **Solución recomendada:** No aplica corrección; se documenta para dejar constancia de que se revisó completo.

---

### 2.4 JavaScript (`assets/exam.js`, `assets/flashcards.js`, `assets/flashcards-library.js`, `assets/help-fab.js`, `assets/reference-modal.js`, `assets/view-state.js`)

#### H-13. Peso combinado de JS sin build/minificación
- **Archivo y ruta:** `assets/exam.js` (40.8 KB / 1079 líneas), `assets/flashcards.js` (62.8 KB / 1685 líneas), `assets/flashcards-library.js` (28.5 KB / 884 líneas)
- **Descripción técnica:** Los tres controladores principales están escritos en JS plano (IIFE), sin ningún paso de minificación, tree-shaking o concatenación. Se confirmó revisando `.github/workflows/deploy.yml` que el despliegue es un FTP directo del repositorio sin ningún job de build (no hay `npm install`, `webpack`, `esbuild`, `terser` ni similar).
- **Nivel de impacto:** Medio
- **Impacto estimado en el rendimiento:** Los tres scripts se encolan con `true` como último argumento (carga en footer), por lo que **no bloquean el render inicial** del documento; esto es un punto a favor real que evita catalogarlos como "render-blocking" en sentido estricto. Aun así, el navegador debe descargar, parsear y compilar ~132 KB de JS sin comprimir (el servidor normalmente aplica gzip/brotli en tránsito, pero el parseo/compilación en el hilo principal ocurre sobre el tamaño descomprimido). Minificar reduciría el tamaño de transferencia entre 25-40% adicional sobre lo que ya logra la compresión HTTP, y reduciría el trabajo de parseo del engine de JS.
- **Solución recomendada:** Incorporar un paso de build simple (esbuild o Terser vía un script npm) antes del FTP deploy, generando versiones `.min.js`/`.min.css` y apuntando los `wp_enqueue_script/style` a esos archivos en producción (se puede condicionar por `WP_DEBUG` para mantener los archivos legibles en desarrollo).

#### H-14. `flashcards.js`: DOM cacheado correctamente, sin problemas de re-render detectados
- **Archivo y ruta:** `assets/flashcards.js`
- **Descripción técnica:** Se confirmó que todas las referencias DOM (`root.querySelector(...)`) se resuelven una sola vez al inicializar (`initStudySessionsApp`), no dentro de los manejadores de eventos ni de funciones que se ejecutan repetidamente como `renderCard()`. No hay `setInterval`/`setTimeout` de polling. Los listeners se registran una vez sobre nodos estáticos del template (no hay fugas de listeners por recreación de nodos en bucle, salvo los botones de respuesta que sí se recrean por pregunta, lo cual es inevitable dado que su contenido cambia).
- **Nivel de impacto:** N/A (hallazgo positivo)
- **Solución recomendada:** Ninguna.

#### H-15. `exam.js`: `setInterval` de 1 segundo durante toda la duración del examen (hasta 15 minutos)
- **Archivo y ruta:** `assets/exam.js`, función `startTimer()` (línea ~520)
- **Descripción técnica:** El cronómetro del examen usa `setInterval(tickTimer, 1000)` mientras dura la sesión activa (hasta `timeLimitSeconds = 900`, es decir, hasta 900 ticks). Cada tick actualiza `textContent` de un nodo y hace `classList.toggle()` dos veces. Es un patrón estándar y razonable para un timer visible; no hay lecturas de layout (`offsetWidth`/`getBoundingClientRect`) dentro del tick, por lo que no genera *layout thrashing*.
- **Nivel de impacto:** Bajo / informativo
- **Impacto estimado en el rendimiento:** Insignificante (una escritura de texto por segundo). Se documenta porque el prompt de auditoría pedía identificar temporizadores explícitamente, no porque represente un problema real.
- **Solución recomendada:** Ninguna acción necesaria; opcionalmente podría bajarse a un tick cada 1s solo cuando la pestaña está visible (`document.visibilityState`) para ahorrar CPU en pestañas en segundo plano, pero es una optimización menor.

#### H-16. `wp_localize_script` con diccionarios de labels relativamente grandes en cada shortcode
- **Archivo y ruta:** `includes/class-vc-flashcards-plugin.php`, dentro de `render_flashcards_shortcode()`, `render_flashcards_library_shortcode()` y `render_exam_shortcode()`
- **Descripción técnica:** Cada shortcode registra un objeto `vcStudySessionsData` / `vcFlashcardsLibraryData` / `vcExamData` con 15-30 strings de traducción incrustados directamente en el HTML vía `wp_localize_script` (que WordPress imprime como un `<script>` inline antes del script real). Esto es pequeño en bytes (unos pocos KB), pero es HTML adicional no cacheable por CDN de forma independiente al resto de la página, y duplica exactamente los mismos labels en cada carga de página en vez de vivir en un archivo JS estático versionable.
- **Nivel de impacto:** Bajo
- **Impacto estimado en el rendimiento:** Marginal; unos pocos KB de HTML no cacheable por request. Se documenta por completitud, no representa una prioridad real de optimización.
- **Solución recomendada:** No es urgente. Si se buscara exprimir cada KB, podría moverse el diccionario de labels a un JSON estático cacheable y dejar `wp_localize_script` solo con `ajaxUrl`/`nonce` (que sí deben ser dinámicos).

---

### 2.5 CSS (`assets/flashcards.css`, `assets/flashcards-library.css`, `assets/exam.css`, `assets/help-fab.css`, `assets/reference-modal.css`)

#### H-17. 122 KB sin minificar en `flashcards.css`, dividido en 3 hojas (nota: duplicación intencional, no corregir)
- **Archivo y ruta:** `assets/flashcards.css` (4264 líneas / 121.8 KB), `assets/flashcards-library.css` (1762 líneas / 45.3 KB), `assets/exam.css` (1744 líneas / 36.4 KB)
- **Descripción técnica:** Cada uno de los tres módulos (`vc-study-sessions-*`, `vc-flashcards-library-*`, `vc-exam-*`) define su propio conjunto casi paralelo de variables CSS, tarjetas de estadísticas, modales de configuración y barras de progreso, con nombres de clase distintos pero estructura visual equivalente (grid de 3 columnas para stats, tarjetas de categoría, modal de cantidad de tarjetas, etc.). No se encontró una hoja base compartida entre los tres módulos más allá de `reference-modal.css` y `help-fab.css` (que sí están correctamente aislados y reutilizados).
- **Nivel de impacto:** Medio
- **Impacto estimado en el rendimiento:** El usuario que visita el dashboard completo (Study Sessions + Flashcards Library + Mock Test, que conviven en el mismo panel según los templates) termina descargando las tres hojas completas (~194 KB sin comprimir combinadas) aunque gran parte de las reglas sea visualmente parecida entre módulos. La duplicación no afecta tiempo de render por sí sola (CSS no usado no cuesta tiempo de layout), pero sí peso de red y trabajo de parseo de CSSOM.
- **Corrección (2026-07-21):** Esta duplicación es una **decisión de arquitectura intencional**, confirmada por el equipo: Flashcards, Study Session y Mock Test deben mantenerse como módulos completamente independientes en lógica, componentes y estilos, incluso cuando el diseño visual coincida. **No se recomienda extraer un `base.css` compartido ni fusionar estas hojas.** El único punto de esta sección que sigue siendo válido es aplicar minificación a cada hoja por separado (ver H-13/H-18), sin tocar su independencia.
- **Solución recomendada:** Ninguna acción de unificación. Si en el futuro el equipo decide compartir código entre módulos, debe evaluarse explícitamente caso por caso antes de tocar la arquitectura — no asumir que la similitud visual implica que deban fusionarse.

#### H-18. Ausencia total de minificación/build para CSS (mismo hallazgo que H-13, aplicado a CSS)
- **Archivo y ruta:** Todas las hojas en `assets/*.css`
- **Descripción técnica:** Confirmado en `.github/workflows/deploy.yml`: no hay paso de build. Los tres wp_enqueue_style ya usan `filemtime()` como versión de caché (`?ver=<timestamp>`), lo cual es correcto para *cache busting*, pero no compensa el hecho de que el archivo servido es el mismo CSS sin minificar que vive en el repositorio.
- **Nivel de impacto:** Medio
- **Impacto estimado en el rendimiento:** Similar a H-13: la compresión gzip/brotli del servidor ya reduce mucho el peso de transferencia de texto repetitivo como CSS, pero minificar (quitar comentarios, espacios, saltos de línea) reduce adicionalmente el tamaño antes de comprimir y el tiempo de parseo del CSSOM en el navegador.
- **Solución recomendada:** Igual que H-13: agregar un paso de build (cssnano, esbuild o similar) antes del deploy.

#### H-19. Uso extenso de selectores `:has()` (35 apariciones en `flashcards.css`)
- **Archivo y ruta:** `assets/flashcards.css` (35 usos), `assets/flashcards-library.css` (8 usos), `assets/exam.css` (2 usos)
- **Descripción técnica:** El selector relacional `:has()` (por ejemplo, `.vc-dashboard-content:has(.vc-study-sessions-app[data-active-view="detail"])`) es soportado por navegadores modernos, pero es estructuralmente más costoso de evaluar que un selector de clase simple porque obliga al motor de estilos a observar cambios en descendientes para decidir si un ancestro coincide. En este plugin se usa deliberadamente para reaccionar a cambios del atributo `data-active-view` que el JS cambia en cada transición de vista (home → detail → session → summary), lo que significa que el navegador debe re-evaluar esas reglas en cada cambio de vista, no solo en la carga inicial.
- **Nivel de impacto:** Bajo
- **Impacto estimado en el rendimiento:** Con la cantidad de nodos que maneja este plugin (decenas, no miles), el costo real de recalculo de estilos por `:has()` es imperceptible en la práctica. Se documenta como hallazgo de bajo impacto porque **si el catálogo de la página crece** (por ejemplo, si este patrón se replica en una página con cientos de tarjetas visibles simultáneamente), el costo de recomputo de estilos en cada interacción escalaría más rápido que con selectores de clase directos.
- **Solución recomendada:** No es urgente cambiarlo hoy. Si se detectan problemas de INP en cambios de vista en dispositivos de gama baja, reemplazar los `:has()` más calientes (los que reaccionan a `data-active-view`) por clases explícitas que el propio JS ya podría aplicar directamente al contenedor padre en `showView()`, ya que el JS ya sabe en qué vista está.

#### H-20. Sin `content-visibility` en listas largas (historial, subtemas, lista de ACS codes)
- **Archivo y ruta:** `assets/flashcards.css` (lista `.vc-study-sessions-acs-code-list`), `assets/flashcards-library.css` (`.vc-flashcards-session-history-list`), `assets/exam.css` (`.vc-exam-history-list`)
- **Descripción técnica:** Ninguna de las listas que se generan dinámicamente en JS (historial de sesiones, lista de códigos ACS, lista de subtemas) usa la propiedad `content-visibility: auto`, que permite al navegador saltar el cálculo de layout/paint de contenido fuera de pantalla.
- **Nivel de impacto:** Bajo
- **Impacto estimado en el rendimiento:** Con los límites actuales del plugin (historial limitado a 5 registros vía `EXAM_HISTORY_LIMIT`, ACS codes normalmente decenas, no cientos), el beneficio real de `content-visibility` hoy es mínimo. Se documenta como oportunidad preventiva si el límite de historial o el catálogo de ACS codes creciera en el futuro.
- **Solución recomendada:** Aplicar `content-visibility: auto` con un `contain-intrinsic-size` razonable a `.vc-study-sessions-acs-code-list` en particular, ya que es la lista con mayor potencial de crecimiento (un código ACS por cada área de examen FAA, potencialmente decenas).

---

## 3. Top 20 problemas priorizados

1. **H-01** — `get_study_sessions_categories()` recalcula todo el catálogo de tarjetas en cada carga sin transient, a diferencia del resto del plugin que sí cachea agregaciones pesadas.
2. **H-02** — Mismo problema que H-01 en `get_flashcards_library_categories()`, para el shortcode de Flashcards Library.
3. **H-13** — Sin build/minificación de JS (~132 KB combinados entre los 3 controladores principales).
4. **H-18** — Sin build/minificación de CSS (~194 KB combinados entre las 3 hojas principales).
5. **H-17** — CSS sin minificar en las 3 hojas de los módulos (Study Sessions / Flashcards Library / Mock Test). La similitud visual entre módulos es intencional (arquitectura desacoplada) y no debe unificarse; el punto pendiente es solo minificación, no fusión.
6. **H-04** — El widget de ayuda (`help-fab`) carga en todas las páginas del sitio, no solo donde hay shortcodes del plugin.
7. **H-03** — `assets/general.css` no existe; genera una petición 404 en tres pantallas distintas.
8. **H-08** — Import CSV sin caché de términos por ejecución ni transacción por lotes; escala mal con catálogos grandes.
9. **H-06** — `data-categories` serializa todo el árbol de categorías + ACS codes dentro de un atributo HTML en cada carga.
10. **H-05** — `get_flashcard_ids_for_term()` sin `no_found_rows`, genera una consulta COUNT innecesaria.
11. **H-19** — Uso extenso de `:has()` reactivo a cambios de vista (35 casos en `flashcards.css`).
12. **H-20** — Sin `content-visibility` en listas dinámicas (historial, ACS codes, subtemas).
13. **H-16** — Diccionarios de traducción vía `wp_localize_script` no cacheables de forma independiente.
14. **H-10** — Lista de feedback en admin sin paginación real más allá del `LIMIT 100` fijo.
15. **H-07** — Código muerto (`get_category_cards()`, `get_flashcards_library_categories_legacy()`) sin costo actual pero riesgo latente.
16. **H-11** — `SELECT *` en `get_item()` del admin de feedback (impacto insignificante, se documenta por consistencia).
17. **H-15** — Timer de examen con tick cada segundo durante la sesión (impacto insignificante, documentado por completitud).
18. **H-09** — (Hallazgo positivo) Buen patrón de caché en pools de examen/sesión, usar como referencia para corregir H-01/H-02.
19. **H-14** — (Hallazgo positivo) DOM correctamente cacheado en `flashcards.js`, sin problemas de re-render.
20. **H-12** — (Hallazgo positivo/informativo) Templates PHP revisados completos sin bucles costosos ocultos.

---

## 4. Plan de optimización por fases

### Fase rápida (horas)
- Eliminar o restaurar `assets/general.css` (H-03).
- Agregar `'no_found_rows' => true` a `get_flashcard_ids_for_term()` (H-05).
- Condicionar la carga del widget `help-fab` a las páginas donde realmente aplica (H-04).
- Eliminar el código muerto `get_category_cards()` / `get_flashcards_library_categories_legacy()` si se confirma con el equipo que no hay plan de reactivarlo (H-07).
- Revisar y, si aplica, corregir el `SELECT *` de `get_item()` en el admin de feedback (H-11).

### Fase media (días)
- Implementar la capa de caché con transient + versión lógica para `get_study_sessions_categories()` y `get_flashcards_library_categories()`, replicando el patrón ya usado en `get_exam_id_pool_for_topic()` (H-01, H-02). Esto requiere separar cuidadosamente la parte "agnóstica de usuario" de la parte "dependiente del usuario" para no cachear datos personales por error.
- Agregar un paso de build (esbuild/Terser + cssnano o similar) al pipeline de `.github/workflows/deploy.yml` para minificar JS y CSS antes del FTP deploy (H-13, H-18).
- Optimizar el import de CSV: cache de términos por ejecución + transacción por lotes (H-08).
- Mover el payload `data-categories` a `<script type="application/json">` o diferir la carga de `acsCodes` a un endpoint AJAX bajo demanda (H-06).
- Agregar paginación real a la lista de feedback en el admin (H-10).

### Fase avanzada (estructural, requiere pruebas cuidadosas)
- ~~Refactorizar las tres hojas CSS para extraer un `base.css` compartido~~ — **descartado**: Flashcards, Study Session y Mock Test son módulos intencionalmente independientes; la duplicación visual entre `flashcards.css`, `flashcards-library.css` y `exam.css` es una decisión de arquitectura, no deuda técnica (ver corrección en H-17). No unificar sin decisión explícita del equipo.
- Evaluar reemplazar los selectores `:has()` más "calientes" (los que reaccionan a cambios de `data-active-view` en cada transición de vista) por clases que el propio JS aplique directamente, si telemetría real de INP en dispositivos de gama baja lo justifica (H-19). Esta optimización es interna a cada módulo y no requiere compartir código entre ellos.
- Añadir `content-visibility: auto` a listas dinámicas si el volumen de datos (historial, ACS codes) crece más allá de los límites actuales (H-20).

---

## 5. Mejora estimada

Estas cifras son estimaciones cualitativas basadas en los hallazgos concretos de este análisis, no mediciones de un profiler real (Query Monitor, New Relic, WebPageTest) sobre el sitio en producción. Se recomienda validar con mediciones reales antes/después de cada cambio.

- **TTFB en `[vc_flashcards_app]` y `[vc_flashcards_library]`:** al cachear `get_study_sessions_categories()` y `get_flashcards_library_categories()` (H-01, H-02), la parte de PHP que hoy se recalcula en cada request pasaría a leerse de un transient en la mayoría de las visitas (TTL configurable, ej. 600s como el resto del plugin). Reducción esperada: probablemente de 2x a 5x en el tiempo de generación de esa sección específica del HTML, dependiendo del tamaño del catálogo de flashcards del sitio. En catálogos pequeños (decenas de tarjetas) el efecto será poco perceptible; en catálogos grandes (cientos/miles) puede ser la diferencia entre una carga fluida y una notablemente lenta.
- **Peso de red en la primera carga de cada shortcode:** minificar JS+CSS (H-13, H-18) más eliminar la petición 404 de `general.css` (H-03) debería reducir el peso total transferido en un 20-35% aproximado sobre lo que ya logra la compresión del servidor, y elimina por completo una petición HTTP innecesaria por página.
- **Páginas fuera del plugin (resto del sitio):** desactivar la carga incondicional del `help-fab` (H-04) elimina ~17 KB sin comprimir de CSS+JS y la ejecución de su script en cada página del sitio que no usa flashcards, lo cual no es dramático mas sí es 100% de ahorro en páginas donde antes era 100% desperdicio.
- **INP en cambios de vista (home/detail/session/summary):** el efecto de simplificar `:has()` (H-19) es marginal con los volúmenes de datos actuales del plugin; se espera una mejora perceptible solo si el catálogo de tarjetas o el número de vistas simultáneas crece significativamente respecto al uso actual. La minificación por hoja (H-13/H-18) sigue aportando, mantenida por separado en cada módulo.
- **Tiempo de importación de CSV:** cachear resolución de términos y usar transacciones por lote (H-08) debería reducir el tiempo total de import en catálogos grandes de forma aproximadamente lineal al porcentaje de filas que comparten topic/subtopic (en catálogos típicos de aviación con pocos topics y muchas filas por topic, esto podría representar una reducción significativa, potencialmente de más de la mitad del tiempo de import en archivos de cientos de filas).

En conjunto, ninguno de estos cambios representa una reescritura arquitectónica: son ajustes localizados, la mayoría reutilizando patrones que el propio plugin ya implementa correctamente en otras partes (`get_exam_id_pool_for_topic`, `get_mastered_flashcard_ids`), lo que reduce el riesgo de regresión al aplicarlos.
